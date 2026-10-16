<?php

namespace App\Support\Antiguo;

use App\Http\Controllers\SucursalController;
use App\Models\Producto;
use App\Models\User;
use App\Support\Kardex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial del sistema antiguo: ventas (facturas, boletas, notas de venta, notas de crédito/débito) con su detalle,
 * medios de pago y estado SUNAT; compras con su detalle; cuentas por cobrar/pagar con sus pagos.
 *
 * Las tablas del sistema nuevo tienen casi las mismas columnas que las antiguas: se copia cada columna que existe en
 * ambas y se reenlazan los IDs (cliente, producto, proveedor, medio de pago, nota → comprobante que modifica).
 * - No mueve stock (el stock actual se importa aparte como inventario inicial).
 * - Lo ACEPTADO por SUNAT queda aceptado y no se reenvía.
 * - Los correlativos de la sucursal continúan después del último número importado.
 * - No duplica: un comprobante con la misma serie y número (o compra del mismo proveedor y documento) se salta.
 */
class Historial
{
    private const LOTE = 500;

    /** Lo que es venta: factura, boleta, notas de crédito/débito y nota de venta (vales, proformas y guías no) */
    public const VENTAS = ['01', '03', '07', '08', '13'];

    /** Presentación antigua (fila de productos con tipo = 2) => su producto principal antiguo */
    private array $padreDe = [];

    /** "IdProducto|factor" => id_presentacion nueva */
    private array $presentaciones = [];

    private array $destino = [];

    /** IdCpe_cabecera antiguo => nuevo */
    private array $mapaCpe = [];

    private array $mapaCompra = [];

    private array $resumen = ['ventas' => 0, 'detalle' => 0, 'medios' => 0, 'compras' => 0, 'cuentas' => 0, 'repetidos' => 0];

    /** Comprobantes electrónicos que el sistema antiguo nunca envió a SUNAT (fecha del más antiguo) */
    private array $sinEnviar = ['n' => 0, 'desde' => null];

    /**
     * @param  \Closure  $src  fn(string $tabla) => query builder de la base antigua
     * @param  \Closure  $hay  fn(string $tabla) => bool
     * @param  \Closure  $filtro  fn($query, string $tabla) => query filtrada por la sucursal antigua
     * @param  \Closure  $aviso  fn(string $texto)
     * @param  array  $mapaProductos  IdProducto antiguo => Producto nuevo
     */
    public function __construct(
        private User $user,
        private \Closure $src,
        private \Closure $hay,
        private \Closure $filtro,
        private \Closure $aviso,
        private array $mapaProductos,
    ) {}

    public function importar(): array
    {
        set_time_limit(0);
        // Datos viejos con fechas 0000-00-00 o textos más largos: se aceptan como en el sistema antiguo
        $modo = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
        DB::statement("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
        try {
            $this->cargarPresentaciones();
            if (($this->hay)('cpe_cabecera')) {
                $this->ventas();
                $this->correlativos();
            }
            if (($this->hay)('compras_cabecera')) {
                $this->compras();
            }
            $this->cuentas('cobrar');
            $this->cuentas('pagar');
        } finally {
            DB::statement('SET SESSION sql_mode = ?', [$modo]);
        }

        return $this->resumen;
    }

    /**
     * Estado SUNAT con los nombres del sistema nuevo: "ACEPTADO CON OBS." → OBSERVADO; enviado sin estado (boletas que
     * fueron en un resumen) → ACEPTADO; nunca enviado → PENDIENTE (se avisa). Así lo ya enviado no se puede editar ni reenviar.
     */
    private function estadoSunat(object $c): ?string
    {
        $estado = mb_strtoupper(trim((string) ($c->est_sunat ?? '')));
        if (! in_array($c->tdocod, ['01', '03', '07', '08'], true)) {
            return $estado ?: null;
        }
        if (str_starts_with($estado, 'ACEPTADO CO') || str_starts_with($estado, 'OBSERVADO')) {
            return 'OBSERVADO';
        }
        if ($estado !== '') {
            return $estado;
        }
        if ((int) ($c->enviado ?? 0) === 1 || ! empty($c->ticket)) {
            return 'ACEPTADO';
        }
        if (empty($c->ccabaj)) {
            $this->sinEnviar['n']++;
            $fecha = (string) ($c->ccafem ?? '');
            $this->sinEnviar['desde'] = $this->sinEnviar['desde'] === null || ($fecha !== '' && $fecha < $this->sinEnviar['desde']) ? $fecha : $this->sinEnviar['desde'];
        }

        return 'PENDIENTE';
    }

    /** Las ventas y compras de una presentación (fardo, millar…) apuntan a su producto principal, como en el sistema nuevo */
    private function cargarPresentaciones(): void
    {
        try {
            $this->padreDe = ($this->src)('productos')->where('tipo', 2)->whereNotNull('pro_rel')->pluck('pro_rel', 'IdProducto')->all();
        } catch (\Throwable $e) {
            $this->padreDe = [];
        }
        $ids = collect($this->mapaProductos)->pluck('IdProducto')->unique()->all();
        foreach (array_chunk($ids, 1000) as $parte) {
            foreach (DB::table('producto_presentacion')->whereIn('IdProducto', $parte)->get(['id_presentacion', 'IdProducto', 'factor']) as $pp) {
                $this->presentaciones[$pp->IdProducto.'|'.round((float) $pp->factor, 3)] = $pp->id_presentacion;
            }
        }
    }

    /** Producto nuevo de una línea antigua: el mismo, o el principal si era una presentación */
    private function productoDe($idAntiguo, $idRel = null): ?Producto
    {
        return $this->mapaProductos[$idAntiguo ?? 0]
            ?? $this->mapaProductos[$this->padreDe[$idAntiguo ?? 0] ?? 0]
            ?? $this->mapaProductos[$idRel ?? 0]
            ?? null;
    }

    private function columnas(string $tabla): array
    {
        return $this->destino[$tabla] ??= array_flip(Schema::getColumnListing($tabla));
    }

    /** Columnas de la fila antigua que también existen en la tabla nueva (sin la clave primaria ni las que se fijan aparte) */
    private function copiar(string $tabla, object $fila, array $fijos, array $excluir = []): array
    {
        $cols = $this->columnas($tabla);
        $datos = [];
        foreach ((array) $fila as $col => $valor) {
            // Las columnas que se fijan aparte nunca toman el valor antiguo (IDs de otra base)
            if (! isset($cols[$col]) || array_key_exists($col, $fijos) || in_array($col, $excluir, true) || $valor === null) {
                continue;
            }
            // Fechas vacías del sistema antiguo
            if (is_string($valor) && str_starts_with($valor, '0000-00-00')) {
                continue;
            }
            $datos[$col] = $valor;
        }

        // Un fijo en null = usar el valor por defecto de la columna
        return array_filter($fijos, fn ($v) => $v !== null) + $datos;
    }

    private function aviso(string $t): void
    {
        ($this->aviso)($t);
    }

    // ------------------------------------------------------------------ ventas

    private function ventas(): void
    {
        $user = $this->user;
        $suc = $user->id_empresa_negocio;
        $almacen = Kardex::almacenPredeterminado($suc)?->id_almacen;

        $clientes = DB::table('cliente')->where('rucemp', $user->IdEmpresa)->pluck('clicod', 'clinum');
        $medios = $this->mapaPorNombre('medios_pagos', 'id_med_pag', 'nom_med_pag', DB::table('medios_pagos')->where('id_empresa_negocio', $suc));
        // Pago con un medio que no existe aquí: queda con el medio predeterminado (se avisa)
        $medioPred = DB::table('medios_pagos')->where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->value('id_med_pag');
        $sinMedio = 0;
        $creditos = $this->mapaPorNombre('credito_dias', 'cre_dia_id', 'cre_dia_nom', DB::table('credito_dias')->where('id_empresa_negocio', $suc));
        $existentes = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)
            ->get(['IdCpe_cabecera', 'tdocod', 'serdoc', 'numdoc'])
            ->mapWithKeys(fn ($c) => [$c->tdocod.'|'.$c->serdoc.'|'.(int) $c->numdoc => $c->IdCpe_cabecera])->all();
        $sinDetalle = 0;
        $referencias = [];
        $otros = ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereNotIn('tdocod', self::VENTAS)
            ->groupBy('tdocod')->selectRaw('tdocod, COUNT(*) AS n')->pluck('n', 'tdocod');
        if ($otros->isNotEmpty()) {
            $nombres = ['14' => 'vales', '15' => 'proformas', '16' => 'notas de pedido', '85' => 'vales de salida', '86' => 'vales de ingreso', '09' => 'guías'];
            $this->aviso('No se importaron como ventas (no son comprobantes de venta): '
                .$otros->map(fn ($n, $t) => $n.' '.($nombres[$t] ?? "documentos tipo {$t}"))->implode(', ').'.');
        }

        ($this->filtro)(($this->src)('cpe_cabecera'), 'cpe_cabecera')->whereIn('tdocod', self::VENTAS)->orderBy('IdCpe_cabecera')
            ->chunk(self::LOTE, function ($lote) use ($user, $suc, $almacen, $clientes, $medios, $medioPred, &$sinMedio, $creditos, &$existentes, &$sinDetalle, &$referencias) {
                $nuevos = [];
                foreach ($lote as $c) {
                    $clave = $c->tdocod.'|'.$c->serdoc.'|'.(int) $c->numdoc;
                    if (isset($existentes[$clave])) {
                        $this->mapaCpe[$c->IdCpe_cabecera] = $existentes[$clave];
                        $this->resumen['repetidos']++;

                        continue;
                    }
                    $datos = $this->copiar('cpe_cabecera', $c, [
                        'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc, 'IdUsuario' => $user->IdUsuario,
                        'id_almacen' => $almacen, 'ped_tip' => 'ANTIGUO',
                        'clicod' => $clientes[$c->ccandi ?? ''] ?? null,
                        'cre_dia_id' => isset($c->cre_dia_id) ? ($creditos[$c->cre_dia_id] ?? null) : null,
                    ], ['IdCpe_cabecera', 'id_turno', 'IdUsuario_ven', 'IdUsuarioCob', 'mozo', 'ped_id', 'pis_id', 'mes_id', 'IdCpe_cabecera_ref', 'res_id', 'cuen_ban_id']);
                    $datos['est_sunat'] = $this->estadoSunat($c);
                    $id = DB::table('cpe_cabecera')->insertGetId(array_filter($datos, fn ($v) => $v !== null));
                    $this->mapaCpe[$c->IdCpe_cabecera] = $id;
                    $existentes[$clave] = $id;
                    $nuevos[$c->IdCpe_cabecera] = $id;
                    if (! empty($c->IdCpe_cabecera_ref)) {
                        $referencias[$id] = $c->IdCpe_cabecera_ref;
                    }
                    $this->resumen['ventas']++;
                }
                if (! $nuevos) {
                    return;
                }

                // Detalle de los comprobantes nuevos
                $filas = [];
                $conDetalle = [];
                if (($this->hay)('cpe_detalle')) {
                    foreach (($this->src)('cpe_detalle')->whereIn('IdCpe_cabecera', array_keys($nuevos))->orderBy('IdCpe_detalle')->get() as $d) {
                        $prod = $this->productoDe($d->IdProducto ?? null, $d->IdProducto_rel ?? null);
                        $filas[] = $this->copiar('cpe_detalle', $d, [
                            'IdCpe_cabecera' => $nuevos[$d->IdCpe_cabecera],
                            'IdProducto' => $prod?->IdProducto, 'IdProducto_rel' => $prod?->IdProducto,
                            'id_almacen_pro' => $almacen,
                        ], ['IdCpe_detalle', 'IdCpe_detalle_ref']);
                        $conDetalle[$d->IdCpe_cabecera] = true;
                    }
                }
                $this->insertar('cpe_detalle', $filas);
                $this->resumen['detalle'] += count($filas);
                $sinDetalle += count(array_diff_key($nuevos, $conDetalle));

                // Medios de pago
                if (($this->hay)('venta_medio_pago')) {
                    $vmp = [];
                    foreach (($this->src)('venta_medio_pago')->whereIn('IdCpe_cabecera', array_keys($nuevos))->get() as $m) {
                        $medio = $medios[$m->id_med_pag ?? 0] ?? null;
                        if (! $medio) {
                            $sinMedio++;
                            $medio = $medioPred;
                        }
                        if (! $medio) {
                            continue;
                        }
                        $vmp[] = $this->copiar('venta_medio_pago', $m, [
                            'IdCpe_cabecera' => $nuevos[$m->IdCpe_cabecera], 'id_med_pag' => $medio, 'id_empresa_negocio' => $suc,
                        ], ['ven_med_pag_id', 'id_turno']);
                    }
                    $this->insertar('venta_medio_pago', $vmp);
                    $this->resumen['medios'] += count($vmp);
                }
            });

        // Notas de crédito / débito: apuntan al comprobante que modifican
        foreach ($referencias as $nuevoId => $refAntigua) {
            if (isset($this->mapaCpe[$refAntigua])) {
                DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nuevoId)->update(['IdCpe_cabecera_ref' => $this->mapaCpe[$refAntigua]]);
            }
        }
        if ($this->sinEnviar['n']) {
            $this->aviso("{$this->sinEnviar['n']} comprobantes electrónicos nunca se enviaron a SUNAT en el sistema antiguo (desde el {$this->sinEnviar['desde']}): "
                .'quedaron PENDIENTES en la campanita. Revisa con el cliente si deben enviarse.');
        }
        if ($sinDetalle) {
            $this->aviso("{$sinDetalle} comprobantes no tenían detalle en el sistema antiguo: se importaron con su total, cliente y estado, sin líneas de productos.");
        }
        if ($sinMedio) {
            $this->aviso("{$sinMedio} pagos usaban un medio de pago que no existe en el sistema nuevo: quedaron con el medio predeterminado.");
        }
    }

    /**
     * Los correlativos continúan después del último número importado. Si la sucursal aún no emitió con su serie
     * configurada, adopta la serie del sistema antiguo (así F001 sigue en 318 y no vuelve a 1).
     */
    private function correlativos(): void
    {
        $suc = $this->user->id_empresa_negocio;
        $importados = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->where('ped_tip', 'ANTIGUO')
            ->groupBy('tdocod', 'serdoc')->selectRaw('tdocod, serdoc, MAX(numdoc) AS ultimo, COUNT(*) AS n')->get();
        if ($importados->isEmpty()) {
            return;
        }
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        $cambios = [];

        foreach (SucursalController::SERIES as $clave => $c) {
            $tdocod = (string) ($c['tdocod'] ?? $clave); // la clave '13' llega como número
            if ($tdocod === 'PR') {
                continue;
            }
            $candidatas = $importados->filter(fn ($i) => $i->tdocod === $tdocod && preg_match($c['regex'], $i->serdoc));
            if ($candidatas->isEmpty()) {
                continue;
            }
            $serieActual = $negocio->{$c['serie']};
            $propia = $candidatas->firstWhere('serdoc', $serieActual);
            if ($propia) {
                if ((int) $propia->ultimo > (int) $negocio->{$c['numero']}) {
                    $cambios[$c['numero']] = (int) $propia->ultimo;
                }

                continue;
            }
            // ¿La serie configurada ya se usó con comprobantes del sistema nuevo?
            $usada = DB::table('cpe_cabecera')->where('id_empresa_negocio', $suc)->where('tdocod', $tdocod)
                ->where('serdoc', $serieActual)->where(fn ($w) => $w->whereNull('ped_tip')->orWhere('ped_tip', '!=', 'ANTIGUO'))->exists();
            $mayor = $candidatas->sortByDesc('n')->first();
            if ($usada) {
                $this->aviso("{$c['nombre']}: el sistema antiguo usaba la serie {$mayor->serdoc} y aquí ya se emitió con {$serieActual}; se mantiene {$serieActual}.");

                continue;
            }
            $cambios[$c['serie']] = $mayor->serdoc;
            $cambios[$c['numero']] = (int) $mayor->ultimo;
        }

        if ($cambios) {
            DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->update($cambios);
            $texto = [];
            foreach (SucursalController::SERIES as $clave => $c) {
                if (isset($cambios[$c['numero']])) {
                    $serie = $cambios[$c['serie']] ?? $negocio->{$c['serie']};
                    $texto[] = "{$c['nombre']} sigue en {$serie}-".str_pad($cambios[$c['numero']] + 1, 8, '0', STR_PAD_LEFT);
                }
            }
            $this->aviso('Correlativos actualizados: '.implode(' · ', $texto).'.');
        }
    }

    // ------------------------------------------------------------------ compras

    private function compras(): void
    {
        $user = $this->user;
        $suc = $user->id_empresa_negocio;
        $almacen = Kardex::almacenPredeterminado($suc)?->id_almacen;
        $proveedores = DB::table('proveedor')->where('IdEmpresa', $user->IdEmpresa)->pluck('prov_id', 'prov_ruc');
        $provAntiguos = ($this->hay)('proveedor') ? ($this->src)('proveedor')->pluck('prov_ruc', 'prov_id') : collect();
        $creditos = $this->mapaPorNombre('credito_dias', 'cre_dia_id', 'cre_dia_nom', DB::table('credito_dias')->where('id_empresa_negocio', $suc));
        $existentes = DB::table('compras_cabecera')->where('id_empresa_negocio', $suc)
            ->get(['com_cab_id', 'prov_id', 'tdocod', 'com_doc_ser', 'com_doc_num', 'com_fec', 'total_com'])
            ->mapWithKeys(fn ($c) => [self::claveCompra($c, $c->prov_id) => $c->com_cab_id])->all();

        ($this->filtro)(($this->src)('compras_cabecera'), 'compras_cabecera')->orderBy('com_cab_id')
            ->chunk(self::LOTE, function ($lote) use ($user, $suc, $almacen, $proveedores, $provAntiguos, $creditos, &$existentes) {
                $nuevos = [];
                foreach ($lote as $c) {
                    $ruc = $c->prov_num ?? ($provAntiguos[$c->prov_id ?? 0] ?? null);
                    $prov = $ruc ? ($proveedores[$ruc] ?? null) : null;
                    $clave = self::claveCompra($c, $prov);
                    if (isset($existentes[$clave])) {
                        $this->mapaCompra[$c->com_cab_id] = $existentes[$clave];
                        $this->resumen['repetidos']++;

                        continue;
                    }
                    if (! $prov) {
                        $this->aviso("Compra {$c->com_doc_ser}-{$c->com_doc_num}: el proveedor {$ruc} no existe en el sistema nuevo (importa también \"Proveedores\").");
                    }
                    $id = DB::table('compras_cabecera')->insertGetId($this->copiar('compras_cabecera', $c, [
                        'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $suc, 'IdUsuario' => $user->IdUsuario,
                        'prov_id' => $prov, 'id_almacen' => $almacen, 'id_turno' => null,
                        'cre_dia_id' => isset($c->cre_dia_id) ? ($creditos[$c->cre_dia_id] ?? null) : null,
                        'est_compra' => $c->est_compra ?? 'Registrado',
                    ], ['com_cab_id', 'usu_elimino']));
                    $this->mapaCompra[$c->com_cab_id] = $id;
                    $existentes[$clave] = $id;
                    $nuevos[$c->com_cab_id] = $id;
                    $this->resumen['compras']++;
                }
                if (! $nuevos || ! ($this->hay)('compras_detalle')) {
                    return;
                }
                $filas = [];
                foreach (($this->src)('compras_detalle')->whereIn('com_cab_id', array_keys($nuevos))->get() as $d) {
                    $prod = $this->productoDe($d->pro_id ?? null, $d->IdProducto_rel ?? null);
                    $factor = isset($this->padreDe[$d->pro_id ?? 0]) ? max(0.001, (float) ($d->com_det_factor ?? 1)) : 1;
                    $filas[] = $this->copiar('compras_detalle', $d, [
                        'com_cab_id' => $nuevos[$d->com_cab_id], 'pro_id' => $prod?->IdProducto,
                        'id_almacen_pro' => $almacen, 'IdEmpresa' => $user->IdEmpresa, 'factor' => $factor,
                        'id_presentacion' => $prod && $factor != 1 ? ($this->presentaciones[$prod->IdProducto.'|'.round($factor, 3)] ?? null) : null,
                    ], ['com_det_id']);
                }
                $this->insertar('compras_detalle', $filas);
            });
    }

    /** Proveedor + documento; sin serie ni número (compras internas antiguas) también cuentan la fecha y el total para no confundirlas */
    private static function claveCompra(object $c, $prov): string
    {
        $numero = ltrim((string) ($c->com_doc_num ?? ''), '0');
        $clave = $prov.'|'.$c->tdocod.'|'.($c->com_doc_ser ?? '').'|'.$numero;

        return trim((string) ($c->com_doc_ser ?? '')) === '' && $numero === ''
            ? $clave.'|'.substr((string) ($c->com_fec ?? ''), 0, 10).'|'.number_format((float) ($c->total_com ?? 0), 2, '.', '')
            : $clave;
    }

    // ------------------------------------------------------------------ cuentas por cobrar / pagar

    private function cuentas(string $tipo): void
    {
        $tabla = "cuentas_{$tipo}";
        $detalle = "{$tabla}_detalle";
        $pk = $tipo === 'cobrar' ? 'cue_cob_id' : 'cue_pag_id';
        $fk = $tipo === 'cobrar' ? 'IdCpe_cabecera' : 'com_cab_id';
        $mapa = $tipo === 'cobrar' ? $this->mapaCpe : $this->mapaCompra;
        if (! ($this->hay)($tabla) || ! $mapa) {
            return;
        }
        $suc = $this->user->id_empresa_negocio;
        $ya = DB::table($tabla)->whereIn($fk, array_values($mapa))->pluck($fk)->flip();

        // Deudas sobre documentos que no se importan (ej. proformas al crédito): se listan para registrarlas a mano
        if ($tipo === 'cobrar' && ($this->hay)('cpe_cabecera')) {
            $fuera = ($this->src)('cuentas_cobrar as k')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'k.IdCpe_cabecera')
                ->whereNotIn('c.tdocod', self::VENTAS)->where('k.saldo', '>', 0.009)
                ->get(['c.serdoc', 'c.numdoc', 'c.ccanom', 'c.ccafem', 'k.saldo']);
            if ($fuera->isNotEmpty()) {
                $this->aviso($fuera->count().' deudas de clientes estaban sobre proformas o vales (no se importan) por S/ '.number_format((float) $fuera->sum('saldo'), 2)
                    .'. Regístralas a mano: '.$fuera->map(fn ($f) => "{$f->ccanom} ({$f->serdoc}-{$f->numdoc} del {$f->ccafem}) S/ ".number_format((float) $f->saldo, 2))->implode(' · '));
            }
        }

        foreach (($this->src)($tabla)->whereIn($fk, array_keys($mapa))->get() as $cta) {
            $nuevoDoc = $mapa[$cta->$fk];
            if (isset($ya[$nuevoDoc])) {
                continue;
            }
            // clicod: cliente del comprobante (cobrar) o proveedor de la compra (pagar)
            $clicod = $tipo === 'cobrar'
                ? DB::table('cpe_cabecera')->where('IdCpe_cabecera', $nuevoDoc)->value('clicod')
                : DB::table('compras_cabecera')->where('com_cab_id', $nuevoDoc)->value('prov_id');
            $id = DB::table($tabla)->insertGetId($this->copiar($tabla, $cta, [
                $fk => $nuevoDoc, 'clicod' => $clicod, 'id_empresa_negocio' => $suc, 'IdEmpresa' => $this->user->IdEmpresa,
            ], [$pk]));
            $this->resumen['cuentas']++;

            if (($this->hay)($detalle)) {
                $pagos = [];
                foreach (($this->src)($detalle)->where($pk, $cta->$pk)->get() as $p) {
                    $pagos[] = $this->copiar($detalle, $p, [$pk => $id], [$tipo === 'cobrar' ? 'cue_cob_det_id' : 'cue_pag_det_id', 'mov_caj_id', 'id_turno']);
                }
                $this->insertar($detalle, $pagos);
            }
        }
    }

    // ------------------------------------------------------------------ apoyo

    /** Inserta en lotes; cada fila trae solo sus columnas con valor, así que se agrupan por columnas */
    private function insertar(string $tabla, array $filas): void
    {
        $grupos = [];
        foreach ($filas as $f) {
            ksort($f);
            $grupos[implode(',', array_keys($f))][] = $f;
        }
        foreach ($grupos as $grupo) {
            foreach (array_chunk($grupo, self::LOTE) as $parte) {
                DB::table($tabla)->insert($parte);
            }
        }
    }

    /** IDs antiguos => nuevos de un catálogo pequeño, relacionados por nombre (medios de pago, formas de pago) */
    private function mapaPorNombre(string $tabla, string $id, string $nombre, $destino): array
    {
        if (! ($this->hay)($tabla)) {
            return [];
        }
        $nuevos = $destino->get([$id, $nombre])->mapWithKeys(fn ($r) => [mb_strtoupper(trim((string) $r->$nombre)) => $r->$id]);

        return ($this->src)($tabla)->get([$id, $nombre])
            ->mapWithKeys(fn ($r) => [$r->$id => $nuevos[mb_strtoupper(trim((string) $r->$nombre))] ?? null])->filter()->all();
    }
}
