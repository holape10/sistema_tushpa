<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Avícola (distribuidora de pollo vivo, pollería y granja):
 * - Venta por peso: pesadas de jabas con aves (peso bruto) − tara de las jabas − peso de las aves muertas = peso neto,
 *   por el precio del día del kilo. Emite su comprobante (contado o crédito) y la mortalidad queda como merma.
 * - Cuenta corriente del cliente (sus ventas al crédito) con abonos que pagan primero lo más antiguo.
 * - Jabas prestadas a cada cliente: salen con la venta y se devuelven después.
 * - Ingreso de pollo comprado (por peso, como una compra) o sacado de un lote de la granja propia.
 * - Beneficio: el pollo vivo se pela y trozea; sale el vivo y entran sus partes con el costo repartido; rendimiento %.
 * - Mermas del rubro: mortalidad, pérdida de peso (deshidratación), beneficio y decomiso.
 */
class Avicola
{
    public const CATEGORIA = 'AVÍCOLA';

    public const TIPOS_MERMA = [
        'MORTALIDAD' => ['🐔', 'Aves muertas'],
        'DESHIDRATACION' => ['💧', 'Pérdida de peso (deshidratación)'],
        'BENEFICIO' => ['🔪', 'Merma del beneficio (pluma, sangre, vísceras)'],
        'DECOMISO' => ['🚫', 'Decomiso (no apto para la venta)'],
        'OTRO' => ['❓', 'Otro motivo'],
    ];

    /** Productos típicos que se crean con un clic (nombre, unidad, ¿se vende vivo?) */
    public const PRODUCTOS_TIPICOS = [
        ['POLLO VIVO', 'KGM'], ['GALLINA VIVA', 'KGM'], ['POLLO BENEFICIADO', 'KGM'], ['PECHUGA DE POLLO', 'KGM'], ['PIERNA DE POLLO', 'KGM'],
        ['ALA DE POLLO', 'KGM'], ['MENUDENCIA (HÍGADO Y MOLLEJA)', 'KGM'], ['PATAS DE POLLO', 'KGM'], ['CABEZA Y PESCUEZO', 'KGM'],
        ['HUEVO (KILO)', 'KGM'], ['HUEVO (JABA DE 30)', 'NIU'],
    ];

    /** Una venta que no lleva comprobante emitido aquí, pero sí se anuló desde el Panel de Ventas, se excluye por esto */
    public static function anulada(?object $cab): bool
    {
        return ! $cab || (bool) $cab->ccabaj;
    }

    public static function config(int $suc): object
    {
        $c = DB::table('avi_config')->where('id_empresa_negocio', $suc)->first();
        if ($c) {
            return $c;
        }
        DB::table('avi_config')->insert(['id_empresa_negocio' => $suc, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('avi_config')->where('id_empresa_negocio', $suc)->first();
    }

    public static function categoria(User $user): int
    {
        $suc = (int) $user->id_empresa_negocio;

        return (int) (DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', self::CATEGORIA)->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nom' => self::CATEGORIA, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc,
                'tip_pro_id' => DB::table('tipo_producto')->where('id_empresa_negocio', $suc)->value('tip_pro_id') ?? 1,
                'cat_acom' => 0, 'visible' => 1, 'predeterminado' => 0]));
    }

    /**
     * Los productos del rubro (categoría AVÍCOLA), con su precio del día (el precio de venta) y su stock.
     *
     * @return Collection<int, object>
     */
    public static function productos(int $suc): Collection
    {
        $cat = DB::table('categorias')->where('id_empresa_negocio', $suc)->where('cat_nom', self::CATEGORIA)->value('cat_id');
        if (! $cat) {
            return collect();
        }

        return DB::table('productos as p')->leftJoin('producto_stock as s', 's.IdProducto', '=', 'p.IdProducto')
            ->where('p.id_empresa_negocio', $suc)->where('p.cat_id', $cat)->where('p.proest', 'Activo')
            ->groupBy('p.IdProducto', 'p.pronom', 'p.umecod', 'p.propun', 'p.costo')
            // Primero lo que se vende vivo (lo del día a día), luego las partes
            ->orderByRaw("p.pronom LIKE 'POLLO%VIV%' DESC")->orderByRaw("p.pronom LIKE '%VIV%' DESC")->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.umecod', 'p.propun', 'p.costo', DB::raw('COALESCE(SUM(s.stock), 0) as stock')]);
    }

    /** Crea los productos típicos que aún no existen (en la categoría AVÍCOLA, sin precio: se pone el precio del día) */
    public static function crearTipicos(User $user): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $cat = self::categoria($user);
        $creados = 0;
        foreach (self::PRODUCTOS_TIPICOS as $i => [$nombre, $ume]) {
            if (DB::table('productos')->where('id_empresa_negocio', $suc)->where('pronom', $nombre)->exists()) {
                continue;
            }
            Producto::create(['procod' => 'AVI-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'-'.$suc, 'pronom' => $nombre, 'umecod' => $ume,
                'costo' => 0, 'propun' => 0, 'promocion' => 0, 'cat_id' => $cat, 'stock_min' => 0, 'proest' => 'Activo',
                'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc]);
            $creados++;
        }

        return $creados;
    }

    private static function producto(User $user, int $id): Producto
    {
        $p = Producto::where('IdProducto', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $p) {
            throw new \RuntimeException('El producto no existe en esta sucursal.');
        }

        return $p;
    }

    // ------------------------------------------------------------------ precio del día

    /**
     * Precio del día de cada producto: queda como su precio de venta (así el Punto Venta y la venta por peso cobran
     * ese precio sin pedir permiso) y en el historial.
     *
     * @param  array<int|string, mixed>  $precios  IdProducto => precio
     */
    public static function fijarPrecios(User $user, array $precios): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $cambiados = 0;
        DB::transaction(function () use ($user, $suc, $precios, &$cambiados) {
            foreach ($precios as $id => $precio) {
                if ($precio === null || $precio === '') {
                    continue;
                }
                $precio = round((float) $precio, 2);
                $p = self::producto($user, (int) $id);
                if ($precio < 0 || abs((float) $p->propun - $precio) < 0.001) {
                    continue;
                }
                DB::table('avi_precios')->insert(['fecha' => now()->toDateString(), 'IdProducto' => $p->IdProducto, 'precio' => $precio,
                    'anterior' => $p->propun, 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc, 'created_at' => now()]);
                DB::table('productos')->where('IdProducto', $p->IdProducto)->update(['propun' => $precio, 'updated_at' => now()]);
                Bitacora::registrar('producto.editar', "Precio del día de {$p->pronom}: S/ ".number_format($precio, 2).' el kilo',
                    ['propun' => ['etiqueta' => 'Precio de venta', 'antes' => number_format((float) $p->propun, 2, '.', ''), 'despues' => number_format($precio, 2, '.', '')]],
                    ['referencia' => ['producto', $p->IdProducto]]);
                $cambiados++;
            }
        });

        return $cambiados;
    }

    // ------------------------------------------------------------------ pesadas

    /**
     * Suma las pesadas: jabas, aves y peso bruto; la tara son las jabas por el peso de una jaba vacía; al neto se le
     * resta también el peso de las aves muertas (no se cobran / no se pagan).
     *
     * @param  list<array{jabas?: int|string, aves?: int|string, peso?: float|string}>  $pesadas
     * @return array{jabas: int, aves: int, bruto: float, tara: float, neto: float, pesadas: list<array{jabas: int, aves: int, peso: float}>}
     */
    public static function calcular(array $pesadas, float $taraJaba, float $pesoMuertas = 0): array
    {
        $lista = collect($pesadas)->map(fn ($p) => ['jabas' => max(0, (int) ($p['jabas'] ?? 0)), 'aves' => max(0, (int) ($p['aves'] ?? 0)),
            'peso' => round(max(0, (float) ($p['peso'] ?? 0)), 3)])->filter(fn ($p) => $p['peso'] > 0)->values();
        if ($lista->isEmpty()) {
            throw new \RuntimeException('Agrega al menos una pesada con su peso.');
        }
        $jabas = (int) $lista->sum('jabas');
        $bruto = round((float) $lista->sum('peso'), 3);
        $tara = round($jabas * $taraJaba, 3);
        $neto = round($bruto - $tara - max(0, $pesoMuertas), 3);
        if ($neto <= 0) {
            throw new \RuntimeException('El peso neto sale en cero o negativo: revisa las pesadas, las jabas y el peso de las aves muertas.');
        }

        return ['jabas' => $jabas, 'aves' => (int) $lista->sum('aves'), 'bruto' => $bruto, 'tara' => $tara, 'neto' => $neto, 'pesadas' => $lista->all()];
    }

    // ------------------------------------------------------------------ venta por peso

    /**
     * Venta de pollo por peso: emite el comprobante por los kilos netos al precio del día (contado o crédito), anota las
     * pesadas y las jabas que se lleva (y las que devuelve) y la mortalidad como merma (las aves muertas salen del stock).
     *
     * @param  array<string, mixed>  $d  cliente (tdicod, clinum, clinom, clidir), IdProducto, pesadas, aves_muertas, peso_muertas,
     *                                   precio, tdocod, credito, fecVen, id_med_pag, mon_med_pag, jabas_devueltas, ruta, fecEmi
     * @return array{venta: int, comprobante: int}
     */
    public static function vender(User $user, array $d): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $cfg = self::config($suc);
        $p = self::producto($user, (int) $d['IdProducto']);
        $pesoMuertas = round(max(0, (float) ($d['peso_muertas'] ?? 0)), 3);
        $c = self::calcular($d['pesadas'] ?? [], (float) $cfg->tara_jaba, $pesoMuertas);
        $precio = round((float) ($d['precio'] ?? $p->propun), 2);
        if ($precio <= 0) {
            throw new \RuntimeException("Pon el precio del día de {$p->pronom}.");
        }
        $total = round($c['neto'] * $precio, 2);
        $credito = ! empty($d['credito']);
        if ($credito && in_array(trim((string) $d['clinum']), ['', '00000000'], true)) {
            throw new \RuntimeException('Para vender al crédito elige al cliente (DNI o RUC).');
        }

        return DB::transaction(function () use ($user, $suc, $d, $p, $c, $precio, $total, $credito, $pesoMuertas) {
            $datos = [
                'tdocod' => $d['tdocod'], 'fecEmi' => $d['fecEmi'] ?? now()->toDateString(),
                'estadopago' => $credito ? Comprobante::creditoDe($user)
                    : (int) DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id'),
                'fecVen' => $credito ? (($d['fecVen'] ?? null) ?: now()->addDays(7)->toDateString()) : null,
                'tdicod' => $d['tdicod'], 'clinum' => $d['clinum'], 'clinom' => $d['clinom'], 'clidir' => $d['clidir'] ?? null,
                'observaciones' => mb_substr('Bruto '.self::kg($c['bruto']).' − tara '.self::kg($c['tara'])
                    .($pesoMuertas > 0 ? ' − muertas '.self::kg($pesoMuertas) : '').' = neto '.self::kg($c['neto']), 0, 100),
                'items' => [['id' => $p->IdProducto, 'cantidad' => $c['neto'], 'precio' => $precio,
                    'descripcion' => mb_substr($p->pronom.($c['jabas'] ? " · {$c['jabas']} jabas" : '').($c['aves'] ? " · {$c['aves']} aves" : ''), 0, 150)]],
                'id_med_pag' => $credito ? [] : ($d['id_med_pag'] ?? []),
                'mon_med_pag' => $credito ? [] : ($d['mon_med_pag'] ?? [$total]),
                'paga' => $credito ? 0 : (float) ($d['paga'] ?? $total),
            ];
            if (! $credito && empty($datos['id_med_pag'])) {
                $datos['id_med_pag'] = [(int) DB::table('medios_pagos')->where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->value('id_med_pag')];
                $datos['mon_med_pag'] = [$total];
            }
            $cabId = VentaDirecta::registrar($user, $datos, 'AVICOLA');
            $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $cabId)->first(['clicod', 'ccanom']);

            $ventaId = DB::table('avi_ventas')->insertGetId([
                'IdCpe_cabecera' => $cabId, 'clicod' => $cab->clicod, 'clinom' => mb_substr((string) $cab->ccanom, 0, 150), 'IdProducto' => $p->IdProducto,
                'jabas' => $c['jabas'], 'aves' => $c['aves'], 'peso_bruto' => $c['bruto'], 'tara' => $c['tara'],
                'aves_muertas' => max(0, (int) ($d['aves_muertas'] ?? 0)), 'peso_muertas' => $pesoMuertas, 'peso_neto' => $c['neto'],
                'precio' => $precio, 'total' => $total, 'credito' => $credito, 'jabas_devueltas' => max(0, (int) ($d['jabas_devueltas'] ?? 0)),
                'ruta' => mb_substr(trim((string) ($d['ruta'] ?? '')), 0, 60) ?: null,
                'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc, 'fecha' => now(),
            ]);
            self::guardarPesadas('VENTA', $ventaId, $c['pesadas']);

            // Jabas: se lleva las de esta venta y devuelve las que tenía (si trajo)
            if ($c['jabas'] > 0) {
                self::movimientoJabas($user, (int) $cab->clicod, 'SALIDA', $c['jabas'], $ventaId);
            }
            if (($dev = (int) ($d['jabas_devueltas'] ?? 0)) > 0) {
                self::movimientoJabas($user, (int) $cab->clicod, 'DEVOLUCION', $dev, $ventaId);
            }
            // Las aves muertas salieron del stock pero no se cobran: son merma
            if ($pesoMuertas > 0) {
                self::merma($user, ['tipo' => 'MORTALIDAD', 'origen' => 'VENTA', 'ref_id' => $ventaId, 'IdProducto' => $p->IdProducto,
                    'aves' => (int) ($d['aves_muertas'] ?? 0), 'kg' => $pesoMuertas, 'sale_stock' => true, 'observacion' => 'Murieron en la entrega a '.$cab->ccanom]);
            }

            return ['venta' => $ventaId, 'comprobante' => $cabId];
        });
    }

    /** @param  list<array{jabas: int, aves: int, peso: float}>  $pesadas */
    private static function guardarPesadas(string $origen, int $id, array $pesadas): void
    {
        foreach ($pesadas as $p) {
            DB::table('avi_pesadas')->insert(['origen' => $origen, 'ref_id' => $id, 'jabas' => $p['jabas'], 'aves' => $p['aves'], 'peso_bruto' => $p['peso']]);
        }
    }

    public static function kg(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.').' kg';
    }

    // ------------------------------------------------------------------ jabas

    public static function movimientoJabas(User $user, int $clicod, string $tipo, int $cantidad, ?int $ventaId = null, ?string $obs = null): void
    {
        if ($cantidad === 0) {
            return;
        }
        $signo = match ($tipo) {
            'SALIDA' => 1, 'DEVOLUCION' => -1, default => 1,
        };
        DB::table('avi_jabas')->insert(['clicod' => $clicod, 'tipo' => $tipo, 'cantidad' => $signo * $cantidad, 'venta_id' => $ventaId,
            'observacion' => $obs ? mb_substr($obs, 0, 150) : null, 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $user->id_empresa_negocio, 'fecha' => now()]);
    }

    /**
     * Jabas que tiene cada cliente (las de ventas anuladas no cuentan): clicod => saldo.
     *
     * @return array<int, int>
     */
    public static function saldoJabas(int $suc, ?int $clicod = null): array
    {
        return DB::table('avi_jabas as j')->leftJoin('avi_ventas as v', 'v.id', '=', 'j.venta_id')
            ->leftJoin('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'v.IdCpe_cabecera')
            ->where('j.id_empresa_negocio', $suc)->when($clicod, fn ($q) => $q->where('j.clicod', $clicod))
            ->where(fn ($q) => $q->whereNull('j.venta_id')->orWhereNull('c.ccabaj'))
            ->groupBy('j.clicod')->selectRaw('j.clicod, SUM(j.cantidad) as saldo')->pluck('saldo', 'clicod')
            ->map(fn ($s) => (int) $s)->all();
    }

    // ------------------------------------------------------------------ cuenta corriente

    /**
     * Saldo de cada cliente: sus ventas al crédito pendientes (clicod => total, saldo, vencido, desde).
     *
     * @return Collection<int, object>
     */
    public static function cuentaCorriente(int $suc, ?int $clicod = null): Collection
    {
        return DB::table('cuentas_cobrar as cc')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
            ->where('cc.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->where('cc.saldo', '>', 0)
            ->whereNotIn('cc.estado_cob', ['PAGADO', 'ANULADO'])
            ->when($clicod, fn ($q) => $q->where('cc.clicod', $clicod))
            ->groupBy('cc.clicod')
            ->selectRaw('cc.clicod, SUM(cc.saldo) as saldo, SUM(CASE WHEN cc.fec_ven < ? THEN cc.saldo ELSE 0 END) as vencido, MIN(c.ccafem) as desde, COUNT(*) as documentos',
                [now()->toDateString()])
            ->get()->keyBy('clicod');
    }

    /**
     * Abono del cliente: paga primero sus deudas más antiguas (cada parte queda como pago de su cuenta por cobrar, con
     * su recibo; el efectivo entra a la caja del turno).
     *
     * @return array{pagado: float, documentos: int}
     */
    public static function abonar(User $user, int $clicod, int $medioId, float $monto, ?string $operacion = null): array
    {
        $monto = round($monto, 2);
        if ($monto <= 0) {
            throw new \RuntimeException('Escribe cuánto abona el cliente.');
        }

        return DB::transaction(function () use ($user, $clicod, $medioId, $monto, $operacion) {
            $cuentas = DB::table('cuentas_cobrar as cc')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->where('cc.id_empresa_negocio', $user->id_empresa_negocio)->where('cc.clicod', $clicod)->whereNull('c.ccabaj')
                ->where('cc.saldo', '>', 0)->whereNotIn('cc.estado_cob', ['PAGADO', 'ANULADO'])
                ->orderBy('c.ccafem')->orderBy('cc.cue_cob_id')->get(['cc.cue_cob_id', 'cc.saldo']);
            $deuda = round((float) $cuentas->sum('saldo'), 2);
            if ($monto > $deuda + 0.001) {
                throw new \RuntimeException('El abono (S/ '.number_format($monto, 2).') es mayor a lo que debe (S/ '.number_format($deuda, 2).').');
            }
            $resto = $monto;
            $documentos = 0;
            foreach ($cuentas as $cu) {
                if ($resto <= 0) {
                    break;
                }
                $parte = round(min($resto, (float) $cu->saldo), 2);
                Cuentas::registrarPago($user, 'cobrar', (int) $cu->cue_cob_id, ['fecha' => now()->toDateString(),
                    'medios' => [['id' => $medioId, 'monto' => $parte]], 'num_oper' => $operacion, 'comentario' => 'Abono a cuenta (avícola)']);
                $resto = round($resto - $parte, 2);
                $documentos++;
            }

            return ['pagado' => $monto, 'documentos' => $documentos];
        });
    }

    // ------------------------------------------------------------------ ingreso de pollo

    /**
     * Ingreso de pollo por peso. Comprado: se registra como una compra (stock, costo y cuenta por pagar si es al crédito).
     * De un lote de la granja propia: entra al stock con el costo del lote por kilo. Las aves muertas en el viaje no se
     * pagan y quedan como merma (sin stock: nunca entraron).
     *
     * @param  array<string, mixed>  $d
     */
    public static function ingresar(User $user, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $cfg = self::config($suc);
        $p = self::producto($user, (int) $d['IdProducto']);
        $pesoMuertas = round(max(0, (float) ($d['peso_muertas'] ?? 0)), 3);
        $c = self::calcular($d['pesadas'] ?? [], (float) $cfg->tara_jaba, $pesoMuertas);
        $lote = ! empty($d['lote_id']) ? DB::table('avi_lotes')->where('id', (int) $d['lote_id'])->where('id_empresa_negocio', $suc)->first() : null;
        if (! empty($d['lote_id']) && ! $lote) {
            throw new \RuntimeException('El lote no existe.');
        }
        $costoKg = $lote ? (float) (AvicolaGranja::resumen($lote)['costo_kg'] ?? 0) : round((float) ($d['costo_kg'] ?? 0), 4);
        if (! $lote && $costoKg <= 0) {
            throw new \RuntimeException('Pon a cuánto te venden el kilo.');
        }
        $total = round($c['neto'] * $costoKg, 2);

        return DB::transaction(function () use ($user, $suc, $d, $p, $c, $lote, $costoKg, $total, $pesoMuertas) {
            $compraId = null;
            if (! $lote) {
                $almacen = Kardex::almacenPredeterminado($suc);
                $compraId = Compras::guardar($user, [
                    // Sin documento del proveedor: "otros" con un número del momento (8 dígitos)
                    'tdocod' => ($d['tdocod'] ?? '') ?: '00', 'serie' => mb_substr(($d['serie'] ?? '') ?: 'S/N', 0, 4),
                    'numero' => mb_substr(($d['numero'] ?? '') ?: now()->format('dHis'), 0, 8),
                    'fecEmi' => now()->toDateString(), 'fecIng' => now()->toDateString(),
                    'estadopago' => ! empty($d['credito']) ? Comprobante::creditoDe($user)
                        : (int) DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id'),
                    'fecVen' => ! empty($d['credito']) ? now()->addDays(7)->toDateString() : null,
                    'moneda' => 'PEN', 'id_almacen' => $almacen?->id_almacen, 'prov_tdicod' => $d['prov_tdicod'] ?? '6',
                    'prov_num' => $d['prov_num'], 'prov_nom' => $d['prov_nom'], 'actualizar_costo' => true,
                    'observaciones' => mb_substr("{$c['jabas']} jabas · {$c['aves']} aves · bruto ".self::kg($c['bruto']).' − tara '.self::kg($c['tara']), 0, 200),
                    'items' => [['id' => $p->IdProducto, 'tip_igv' => $d['tip_igv'] ?? '20', 'cantidad' => $c['neto'], 'costo' => $costoKg]],
                ]);
            } else {
                $almacen = Kardex::almacenPredeterminado($suc);
                if (! $almacen) {
                    throw new \RuntimeException('La sucursal no tiene almacén.');
                }
                // Producción propia (tabla 12 de SUNAT: 19 = entrada de producción)
                Kardex::registrar($p->IdProducto, $almacen->id_almacen, $c['neto'], 'I', ['cod_tip_ope' => '19', 'costo' => $costoKg,
                    'descripcion' => 'INGRESO DEL LOTE '.$lote->codigo]);
            }

            $id = DB::table('avi_ingresos')->insertGetId([
                'com_cab_id' => $compraId, 'proveedor' => $lote ? 'GRANJA · LOTE '.$lote->codigo : mb_substr((string) $d['prov_nom'], 0, 150),
                'IdProducto' => $p->IdProducto, 'jabas' => $c['jabas'], 'aves' => $c['aves'], 'peso_bruto' => $c['bruto'], 'tara' => $c['tara'],
                'aves_muertas' => max(0, (int) ($d['aves_muertas'] ?? 0)), 'peso_muertas' => $pesoMuertas, 'peso_neto' => $c['neto'],
                'costo_kg' => $costoKg, 'total' => $total, 'lote_id' => $lote?->id, 'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc, 'fecha' => now(),
            ]);
            self::guardarPesadas('INGRESO', $id, $c['pesadas']);
            if ($pesoMuertas > 0 || (int) ($d['aves_muertas'] ?? 0) > 0) {
                self::merma($user, ['tipo' => 'MORTALIDAD', 'origen' => 'INGRESO', 'ref_id' => $id, 'IdProducto' => $p->IdProducto,
                    'aves' => (int) ($d['aves_muertas'] ?? 0), 'kg' => $pesoMuertas, 'sale_stock' => false, 'costo' => 0,
                    'observacion' => 'Llegaron muertas'.($lote ? ' del lote '.$lote->codigo : ' de '.$d['prov_nom'])]);
            }

            return $id;
        });
    }

    // ------------------------------------------------------------------ beneficio

    /**
     * Beneficio: sale el pollo vivo (kg) y entran sus partes (pollo pelado, pechuga, menudencia…) con el costo del vivo
     * repartido por kilo. Lo que falta (pluma, sangre) es la merma del beneficio; el rendimiento es lo que salió / lo vivo.
     *
     * @param  array<string, mixed>  $d  IdProducto, aves, kg_vivo, partes [[IdProducto, kg]], observacion
     */
    public static function beneficiar(User $user, array $d): int
    {
        $suc = (int) $user->id_empresa_negocio;
        $vivo = self::producto($user, (int) $d['IdProducto']);
        $kgVivo = round((float) $d['kg_vivo'], 3);
        $partes = collect($d['partes'] ?? [])->map(fn ($x) => ['id' => (int) ($x['IdProducto'] ?? 0), 'kg' => round((float) ($x['kg'] ?? 0), 3)])
            ->filter(fn ($x) => $x['id'] && $x['kg'] > 0)->values();
        if ($kgVivo <= 0 || $partes->isEmpty()) {
            throw new \RuntimeException('Pon los kilos de pollo vivo y lo que salió de cada parte.');
        }
        $kgSalida = round((float) $partes->sum('kg'), 3);
        if ($kgSalida > $kgVivo + 0.001) {
            throw new \RuntimeException('Lo que salió ('.self::kg($kgSalida).') no puede pesar más que el pollo vivo ('.self::kg($kgVivo).').');
        }
        if ($partes->contains(fn ($x) => $x['id'] === $vivo->IdProducto)) {
            throw new \RuntimeException('El pollo vivo no puede ser también una de las partes.');
        }
        $almacen = Kardex::almacenPredeterminado($suc);
        if (! $almacen) {
            throw new \RuntimeException('La sucursal no tiene almacén.');
        }

        return DB::transaction(function () use ($user, $suc, $d, $vivo, $kgVivo, $partes, $kgSalida, $almacen) {
            $costoTotal = round($kgVivo * (float) $vivo->costo, 2);
            $costoKg = $kgSalida > 0 ? round($costoTotal / $kgSalida, 4) : 0;
            $id = DB::table('avi_beneficios')->insertGetId([
                'IdProducto' => $vivo->IdProducto, 'aves' => max(0, (int) ($d['aves'] ?? 0)), 'kg_vivo' => $kgVivo, 'kg_salida' => $kgSalida,
                'rendimiento' => round($kgSalida / $kgVivo * 100, 2), 'costo_total' => $costoTotal,
                'observacion' => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 150) ?: null,
                'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $suc, 'fecha' => now(),
            ]);
            // Tabla 12 de SUNAT: 10 = salida a producción, 19 = entrada de producción
            Kardex::registrar($vivo->IdProducto, $almacen->id_almacen, $kgVivo, 'E', ['cod_tip_ope' => '10', 'costo' => $vivo->costo, 'descripcion' => 'BENEFICIO N° '.$id]);
            foreach ($partes as $x) {
                $parte = self::producto($user, $x['id']);
                DB::table('avi_beneficio_partes')->insert(['beneficio_id' => $id, 'IdProducto' => $parte->IdProducto, 'kg' => $x['kg'], 'costo_kg' => $costoKg]);
                Kardex::registrar($parte->IdProducto, $almacen->id_almacen, $x['kg'], 'I', ['cod_tip_ope' => '19', 'costo' => $costoKg, 'descripcion' => 'BENEFICIO N° '.$id]);
                DB::table('productos')->where('IdProducto', $parte->IdProducto)->update(['costo' => $costoKg]);
            }
            if ($kgVivo - $kgSalida > 0.0005) {
                // La merma ya está en el costo de las partes: aquí solo se anota cuánto se perdió
                self::merma($user, ['tipo' => 'BENEFICIO', 'origen' => 'BENEFICIO', 'ref_id' => $id, 'IdProducto' => $vivo->IdProducto,
                    'aves' => 0, 'kg' => round($kgVivo - $kgSalida, 3), 'sale_stock' => false, 'costo' => 0,
                    'observacion' => 'Pluma, sangre y vísceras del beneficio N° '.$id]);
            }

            return $id;
        });
    }

    // ------------------------------------------------------------------ mermas

    /**
     * Anota una merma; si sale del stock, saca los kilos del almacén (tabla 12 de SUNAT: 13 = mermas) con su costo.
     *
     * @param  array<string, mixed>  $d  tipo, origen, ref_id, IdProducto, aves, kg, sale_stock, costo, observacion
     */
    public static function merma(User $user, array $d): int
    {
        if (! isset(self::TIPOS_MERMA[$d['tipo'] ?? ''])) {
            throw new \RuntimeException('Elige el tipo de merma.');
        }
        $p = ! empty($d['IdProducto']) ? self::producto($user, (int) $d['IdProducto']) : null;
        $kg = round(max(0, (float) ($d['kg'] ?? 0)), 3);
        $aves = max(0, (int) ($d['aves'] ?? 0));
        if ($kg <= 0 && $aves <= 0) {
            throw new \RuntimeException('Pon cuántas aves o cuántos kilos se perdieron.');
        }
        $saleStock = ! empty($d['sale_stock']) && $p && $kg > 0;
        $costo = array_key_exists('costo', $d) ? round((float) $d['costo'], 2) : round($kg * (float) ($p?->costo ?? 0), 2);

        return DB::transaction(function () use ($user, $d, $p, $kg, $aves, $saleStock, $costo) {
            $id = DB::table('avi_mermas')->insertGetId([
                'fecha' => $d['fecha'] ?? now()->toDateString(), 'tipo' => $d['tipo'], 'origen' => $d['origen'] ?? 'MANUAL', 'ref_id' => $d['ref_id'] ?? null,
                'IdProducto' => $p?->IdProducto, 'aves' => $aves, 'kg' => $kg, 'costo' => $costo, 'sale_stock' => $saleStock,
                'observacion' => mb_substr(trim((string) ($d['observacion'] ?? '')), 0, 150) ?: null,
                'IdUsuario' => $user->IdUsuario, 'id_empresa_negocio' => $user->id_empresa_negocio,
            ]);
            if ($saleStock) {
                $almacen = Kardex::almacenPredeterminado((int) $user->id_empresa_negocio);
                if (! $almacen) {
                    throw new \RuntimeException('La sucursal no tiene almacén.');
                }
                Kardex::registrar($p->IdProducto, $almacen->id_almacen, $kg, 'E', ['cod_tip_ope' => '13', 'costo' => $p->costo,
                    'descripcion' => 'MERMA: '.self::TIPOS_MERMA[$d['tipo']][1]]);
            }

            return $id;
        });
    }

    /** Anula una merma manual (devuelve al stock lo que había sacado) */
    public static function anularMerma(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $m = DB::table('avi_mermas')->where('id', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)->lockForUpdate()->first();
            if (! $m || $m->estado !== 'ACTIVA') {
                throw new \RuntimeException('Esa merma ya fue anulada.');
            }
            if ($m->origen !== 'MANUAL') {
                throw new \RuntimeException('Esta merma viene de una venta, un ingreso o un beneficio: se corrige desde allí.');
            }
            if ($m->sale_stock) {
                $almacen = Kardex::almacenPredeterminado((int) $user->id_empresa_negocio);
                Kardex::registrar((int) $m->IdProducto, $almacen->id_almacen, (float) $m->kg, 'I', ['cod_tip_ope' => '13', 'descripcion' => 'ANULA MERMA N° '.$id]);
            }
            DB::table('avi_mermas')->where('id', $id)->update(['estado' => 'ANULADA']);
        });
    }
}
