<?php

namespace App\Support;

use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Support\Impresion\Impresion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Caja sin internet. Mientras hay internet, la PC de caja se lleva un "paquete" con todo lo que necesita para vender sola
 * (productos y precios, mesas, medios de pago, impresoras, el encabezado del ticket y su serie propia). Si se cae el
 * internet vende, toma pedidos de mesa e imprime con el agente de esa PC; al volver, sube las ventas y aquí se emiten
 * con el mismo número que ya salió impreso (serie propia de la caja: nunca choca con otra caja ni deja huecos).
 */
class CajaSinInternet
{
    /** Comprobantes que se pueden emitir sin internet => letra de su serie propia */
    public const TIPOS = ['03' => 'B', '01' => 'F', '13' => 'N'];

    /** El nombre de la PC de caja: el elegido en "¿Qué caja?" o el código que la caja guardó en su navegador */
    public static function pc(Request $request): string
    {
        $pc = (string) ($request->cookie(Impresion::COOKIE_EQUIPO) ?: $request->input('pc', ''));
        $pc = mb_strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $pc));
        if ($pc === '') {
            throw new \RuntimeException('Esta PC aún no tiene nombre de caja: elige "¿Qué caja?" arriba o escribe un nombre para esta caja.');
        }

        return mb_substr($pc, 0, 40);
    }

    /**
     * La serie propia de esta caja para cada comprobante (la crea la primera vez: BX01, FX01, NX01…) y el último número usado.
     *
     * @return array<string, array{serie: string, ultimo: int}>
     */
    public static function series(int $suc, string $pc): array
    {
        return DB::transaction(function () use ($suc, $pc) {
            $resultado = [];
            foreach (self::TIPOS as $tdocod => $letra) {
                $fila = DB::table('series_offline')->where('id_empresa_negocio', $suc)->where('pc', $pc)->where('tdocod', $tdocod)->lockForUpdate()->first();
                if (! $fila) {
                    $serie = self::serieLibre($letra);
                    DB::table('series_offline')->insert(['id_empresa_negocio' => $suc, 'pc' => $pc, 'tdocod' => $tdocod, 'serie' => $serie, 'ultimo' => 0, 'created_at' => now()]);
                    $fila = (object) ['serie' => $serie, 'ultimo' => 0];
                }
                // Por si alguna venta subió sin actualizar el contador: el último es el mayor emitido con esa serie
                $emitido = (int) DB::table('cpe_cabecera')->where('tdocod', $tdocod)->where('serdoc', $fila->serie)->max('numdoc');
                $resultado[$tdocod] = ['serie' => $fila->serie, 'ultimo' => max((int) $fila->ultimo, $emitido)];
            }

            return $resultado;
        });
    }

    /** Una serie que no usa ninguna sucursal de la empresa (ni en línea ni sin internet ni en comprobantes antiguos): BX01, BX02… BY01 */
    private static function serieLibre(string $letra): string
    {
        $columnas = ['FseEmpresa', 'BseEmpresa', 'SerNota', 'SerNCF', 'SerNCB', 'SerNDF', 'SerNDB', 'SerProforma', 'SerGuia'];
        $usadas = collect(DB::table('empresa_negocios')->get($columnas))->flatMap(fn ($n) => array_values((array) $n))
            ->merge(DB::table('series_offline')->pluck('serie'))
            ->merge(DB::table('cpe_cabecera')->where('serdoc', 'like', $letra.'%')->distinct()->pluck('serdoc'))
            ->filter()->map(fn ($s) => mb_strtoupper(trim((string) $s)))->flip();
        foreach (['X', 'Y', 'Z', 'W'] as $grupo) {
            for ($n = 1; $n <= 99; $n++) {
                $serie = $letra.$grupo.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                if (! isset($usadas[$serie])) {
                    return $serie;
                }
            }
        }
        throw new \RuntimeException('No hay más series libres para la caja sin internet.');
    }

    /**
     * Revisa el número con el que salió una venta sin internet: la serie debe ser de una caja de la sucursal y el número
     * no debe existir ya. Deja anotado el último usado.
     *
     * @return array{0: string, 1: int}
     */
    public static function validarNumero(User $user, string $tdocod, string $serie, int $numero): array
    {
        $serie = mb_strtoupper(trim($serie));
        $fila = DB::table('series_offline')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('tdocod', $tdocod)->where('serie', $serie)->lockForUpdate()->first();
        if (! $fila || $numero < 1) {
            throw new \RuntimeException("La serie {$serie} no es de una caja sin internet de esta sucursal.");
        }
        if (DB::table('cpe_cabecera')->where('tdocod', $tdocod)->where('serdoc', $serie)->where('numdoc', $numero)->exists()) {
            throw new \RuntimeException("El comprobante {$serie}-{$numero} ya existe.");
        }
        if ($numero > (int) $fila->ultimo) {
            DB::table('series_offline')->where('id', $fila->id)->update(['ultimo' => $numero]);
        }

        return [$serie, $numero];
    }

    /** Todo lo que la caja necesita para vender sola */
    public static function paquete(User $user, string $pc): array
    {
        $suc = (int) $user->id_empresa_negocio;
        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->first();
        $empresa = DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa)->first();
        $turno = Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $suc)->where('estado', 'ABIERTO')->first();

        $productos = Producto::where('productos.id_empresa_negocio', $suc)->where('proest', 'Activo')->whereNotIn('promocion', Producto::NO_VENDIBLES)
            ->orderBy('pronom')->get(['IdProducto', 'procod', 'codigo_barra', 'pronom', 'propun', 'cat_id', 'umecod', 'promocion']);
        $precios = Precios::vigentes($productos);
        $presentaciones = DB::table('producto_presentacion')->whereIn('IdProducto', $productos->pluck('IdProducto'))->where('estado', 1)
            ->get(['id_presentacion', 'IdProducto', 'nombre', 'factor', 'precio'])->groupBy('IdProducto');
        $mesas = DB::table('mesas as m')->leftJoin('pisos as p', 'p.pis_id', '=', 'm.pis_id')->where('m.id_empresa_negocio', $suc)
            ->orderBy('p.pis_id')->orderBy('m.mes_id')->get(['m.mes_id', 'm.mes_nom', 'p.pis_id', 'p.pis_nom']);

        // Impresoras: la de caja de esta PC, la barra (si la tiene) y la de cada categoría para las comandas
        $impresoras = DB::table('configuracion_impresoras')->where('id_empresa_negocio', $suc)->where('activo', 1)
            ->get(['Id', 'descripcion', 'ruta', 'tip_conex_imp', 'columnas']);
        $caja = Impresion::impresoraCaja($suc, $user);
        $barra = Impresion::destinosComandaCaja($suc, $user);
        $restaurante = $mesas->isNotEmpty();

        return [
            'version' => now()->format('YmdHis'), 'generado' => now()->toIso8601String(), 'pc' => $pc,
            'usuario' => ['id' => $user->IdUsuario, 'nombre' => trim((string) ($user->apeusu ?: $user->name))],
            'turno' => $turno ? ['id' => $turno->id_turno, 'numero' => $turno->turno ?? $turno->id_turno, 'apertura' => (string) $turno->apertura] : null,
            'empresa' => [
                'ruc' => $empresa->IdEmpresa, 'razon' => $empresa->NomEmpresa, 'nombre' => $negocio->nombre_comercial ?: $empresa->NomEmpresa,
                'direccion' => (string) $negocio->direccion, 'telefono' => (string) $negocio->telefono,
                'gravado' => $negocio->tip_igv_pred === '10', 'tdocod' => in_array($negocio->tdocod_pred, ['01', '03', '13'], true) ? $negocio->tdocod_pred : '03',
                // De dónde sale la venta (con mesas es restaurante: su tasa de IGV es otra)
                'origen' => $restaurante ? 'PVCOMANDA' : 'PV', 'factor' => Comprobante::factorPara($restaurante ? 'PVCOMANDA' : 'PV', (bool) $negocio->igv_restaurante),
                'factor_mesa' => Comprobante::factorPara('SALON', (bool) $negocio->igv_restaurante),
                'consulta' => ConsultaCpe::direccion(), 'firma' => (string) config('soporte.firma_comprobante'),
                'leyenda' => 'BIENES TRANSFERIDOS EN LA AMAZONIA PARA SER CONSUMIDOS EN LA MISMA. SERVICIOS PRESTADOS EN LA AMAZONIA',
            ],
            'series' => self::series($suc, $pc),
            'contado' => (int) DB::table('credito_dias')->where('id_empresa_negocio', $suc)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id'),
            'medios' => DB::table('medios_pagos')->where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->orderBy('nom_med_pag')
                ->get(['id_med_pag', 'nom_med_pag'])->map(fn ($m) => ['id' => (int) $m->id_med_pag, 'nombre' => $m->nom_med_pag])->values(),
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $suc)->orderBy('cat_nom')
                ->get(['cat_id', 'cat_nom', 'color', 'impresora', 'impresora2'])
                ->map(fn ($c) => ['id' => (int) $c->cat_id, 'nombre' => $c->cat_nom, 'color' => $c->color, 'impresoras' => array_values(array_unique(array_filter([(int) $c->impresora, (int) $c->impresora2])))])->values(),
            'productos' => $productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'codigo' => (string) $p->procod, 'barra' => (string) $p->codigo_barra,
                'nombre' => $p->pronom, 'precio' => (float) ($precios[$p->IdProducto] ?? $p->propun), 'categoria' => (int) $p->cat_id, 'umecod' => $p->umecod,
                'presentaciones' => ($presentaciones[$p->IdProducto] ?? collect())->map(fn ($r) => ['id' => (int) $r->id_presentacion, 'nombre' => $r->nombre,
                    'factor' => (float) $r->factor, 'precio' => (float) $r->precio])->values()])->values(),
            'mesas' => $mesas->map(fn ($m) => ['id' => (int) $m->mes_id, 'nombre' => $m->mes_nom, 'piso' => $m->pis_nom])->values(),
            // Mesas ya abiertas en el sistema con lo que falta cobrar: si se cae el internet se ven ocupadas y se pueden cobrar
            'pedidos' => self::pedidosAbiertos($suc),
            'impresoras' => $impresoras->map(fn ($i) => ['id' => (int) $i->Id, 'nombre' => $i->descripcion, 'ruta' => $i->ruta ?: $i->descripcion,
                'conexion' => $i->tip_conex_imp, 'columnas' => (int) $i->columnas ?: 42])->values(),
            'impresora_caja' => $caja && $caja->tip_conex_imp !== 'WINDOWS' ? (int) $caja->Id : null,
            'destinos_comanda' => $barra,
            'encabezado' => $caja && $caja->tip_conex_imp !== 'WINDOWS' ? base64_encode(Impresion::encabezadoBytes($suc, (int) $caja->columnas ?: 42)) : null,
            'copias' => ['comprobante' => Impresion::copias($suc, 'copias_comprobante'), 'comanda' => Impresion::copias($suc, 'copias_comanda')],
        ];
    }

    /**
     * Pedidos de mesa abiertos con lo que aún falta cobrar (una línea por producto, presentación y precio).
     *
     * @return array<int, array{ped_id: int, mesa: int, mozo: ?int, items: array}>
     */
    public static function pedidosAbiertos(int $suc): array
    {
        $pedidos = DB::table('pedidos')->where('id_empresa_negocio', $suc)->where('ped_est', 'Aperturado')->whereNotNull('mes_id')
            ->where('ped_tip', '!=', 'Hotel')->orderBy('ped_id')->get(['ped_id', 'mes_id', 'mozo'])->unique('mes_id');
        $items = DB::table('pedidos_detalle as d')->leftJoin('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->whereIn('d.ped_id', $pedidos->pluck('ped_id')->all() ?: [0])->where('d.estadoitem', '!=', 'Eliminado')
            ->whereRaw('(d.ped_det_can - IFNULL(d.item_facturado, 0)) > 0')
            ->get(['d.ped_id', 'd.IdProducto', 'd.id_presentacion', 'd.descripcion', 'd.ped_det_pre', 'p.cat_id',
                DB::raw('d.ped_det_can - IFNULL(d.item_facturado, 0) as pendiente')])
            ->groupBy('ped_id');

        return $pedidos->map(fn ($p) => [
            'ped_id' => (int) $p->ped_id, 'mesa' => (int) $p->mes_id, 'mozo' => $p->mozo ? (int) $p->mozo : null,
            'items' => ($items[$p->ped_id] ?? collect())->groupBy(fn ($i) => $i->IdProducto.'|'.$i->id_presentacion.'|'.$i->ped_det_pre)
                ->map(fn ($g) => ['id' => (int) $g->first()->IdProducto, 'nombre' => $g->first()->descripcion, 'precio' => (float) $g->first()->ped_det_pre,
                    'presentacion' => $g->first()->id_presentacion ? (int) $g->first()->id_presentacion : null, 'categoria' => (int) $g->first()->cat_id,
                    'cantidad' => round($g->sum('pendiente'), 2)])->values()->all(),
        ])->filter(fn ($p) => $p['items'])->values()->all();
    }

    /**
     * Un pedido del sistema cobrado sin internet: lo cobrado se descuenta de sus líneas y, si ya no queda nada, se cierra
     * el pedido y se libera la mesa (igual que al cobrar la mesa con internet).
     */
    private static function cerrarPedido(Pedido $pedido, array $items): void
    {
        $lineas = PedidoDetalle::where('ped_id', $pedido->ped_id)->where('estadoitem', '!=', 'Eliminado')
            ->whereRaw('(ped_det_can - IFNULL(item_facturado, 0)) > 0')->orderBy('ped_det_id')->get();
        foreach ($items as $it) {
            $resta = (float) $it['cantidad'];
            foreach ($lineas->where('IdProducto', (int) ($it['id'] ?? 0)) as $linea) {
                $toma = min($resta, (float) $linea->ped_det_can - (float) $linea->item_facturado);
                if ($toma > 0) {
                    $linea->increment('item_facturado', $toma);
                    $resta = round($resta - $toma, 2);
                }
                if ($resta <= 0) {
                    break;
                }
            }
        }
        $quedan = PedidoDetalle::where('ped_id', $pedido->ped_id)->where('estadoitem', '!=', 'Eliminado')
            ->whereRaw('(ped_det_can - IFNULL(item_facturado, 0)) > 0')->exists();
        if (! $quedan) {
            $pedido->update(['ped_est' => 'Cerrado', 'fecha_hora_modificacion' => now()]);
            Mesa::where('mes_id', $pedido->mes_id)->update(['mes_est' => 'Libre']);
            MesasUnidas::liberar($pedido->ped_id);
        } else {
            $pedido->update(['fecha_hora_modificacion' => now()]);
        }
    }

    /**
     * Sube una venta hecha sin internet. Si ya llegó antes (mismo código), devuelve la misma: nunca se duplica.
     *
     * @return array{ok: bool, id: ?int, numero: string, mensaje: ?string}
     */
    public static function registrar(User $user, string $pc, array $v): array
    {
        $numero = $v['serie'].'-'.str_pad((string) $v['numero'], 8, '0', STR_PAD_LEFT);
        $ya = DB::table('ventas_offline')->where('uuid', $v['uuid'])->first();
        if ($ya && $ya->estado === 'OK') {
            return ['ok' => true, 'id' => (int) $ya->IdCpe_cabecera, 'numero' => $numero, 'mensaje' => 'Ya estaba registrada.'];
        }

        try {
            $cabId = DB::transaction(function () use ($user, $v) {
                // El turno en el que se vendió (la plata está en esa caja), aunque ya se haya cerrado
                $turno = Turno::where('id_turno', $v['id_turno'] ?? 0)->where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $user->id_empresa_negocio)->first()
                    ?? Turno::where('IdUsuario', $user->IdUsuario)->where('id_empresa_negocio', $user->id_empresa_negocio)->where('estado', 'ABIERTO')->first();
                if (! $turno) {
                    throw new \RuntimeException('No se encontró el turno de caja de esta venta. Abre tu turno para subirla.');
                }
                $lineas = VentaDirecta::lineas($user, collect($v['items']));
                $mesa = ! empty($v['mesa']) ? DB::table('mesas')->where('mes_id', $v['mesa'])->where('id_empresa_negocio', $user->id_empresa_negocio)->first() : null;
                // La mesa ya estaba abierta en el sistema: se cobra ese pedido (si nadie lo cobró ya con internet)
                $pedido = ! empty($v['pedido']) ? Pedido::where('ped_id', $v['pedido'])->where('id_empresa_negocio', $user->id_empresa_negocio)
                    ->where('ped_est', 'Aperturado')->lockForUpdate()->first() : null;
                $datos = [
                    'tdocod' => $v['tdocod'], 'estadopago' => (int) DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->where('cre_dia_tip', 'CONTADO')->value('cre_dia_id'),
                    'fecEmi' => substr((string) $v['fecha'], 0, 10), 'tdicod' => $v['tdicod'], 'clinum' => $v['clinum'], 'clinom' => $v['clinom'],
                    'clidir' => $v['clidir'] ?? null,
                    'observaciones' => mb_substr(implode(' · ', array_filter([$mesa ? 'MESA '.$mesa->mes_nom : null, trim((string) ($v['observaciones'] ?? ''))])), 0, 100) ?: null,
                    'paga' => $v['paga'] ?? 0, 'id_med_pag' => array_column($v['pagos'], 'medio'), 'mon_med_pag' => array_column($v['pagos'], 'monto'),
                    'op_med_pag' => array_map(fn ($p) => $p['operacion'] ?? null, $v['pagos']),
                    'serie_offline' => ['serie' => $v['serie'], 'numero' => (int) $v['numero']],
                ];

                $cabId = Comprobante::emitir($user, $turno, $datos, $lineas, array_filter([
                    'ped_tip' => $mesa ? 'SALON' : ($v['origen'] ?? 'PV'), 'IdUsuario_ven' => $pedido?->mozo ?? $user->IdUsuario, 'fecha_hora' => $v['fecha'],
                    'mes_id' => $mesa?->mes_id, 'pis_id' => $mesa?->pis_id ?? null, 'mozo' => $v['mozo'] ?? $pedido?->mozo, 'ped_id' => $pedido?->ped_id,
                ], fn ($x) => $x !== null));
                if ($pedido) {
                    self::cerrarPedido($pedido, $v['items']);
                }

                return $cabId;
            });
        } catch (\Throwable $e) {
            if (! $e instanceof \RuntimeException) {
                report($e);
            }
            self::anotar($user, $pc, $v, null, 'ERROR', mb_substr($e->getMessage(), 0, 500));

            return ['ok' => false, 'id' => null, 'numero' => $numero, 'mensaje' => $e->getMessage()];
        }
        self::anotar($user, $pc, $v, $cabId, 'OK', null);

        return ['ok' => true, 'id' => $cabId, 'numero' => $numero, 'mensaje' => null];
    }

    private static function anotar(User $user, string $pc, array $v, ?int $cabId, string $estado, ?string $error): void
    {
        DB::table('ventas_offline')->updateOrInsert(['uuid' => $v['uuid']], [
            'id_empresa_negocio' => $user->id_empresa_negocio, 'pc' => $pc, 'IdUsuario' => $user->IdUsuario, 'id_turno' => $v['id_turno'] ?? null,
            'IdCpe_cabecera' => $cabId, 'tdocod' => $v['tdocod'], 'serie' => $v['serie'], 'numero' => (int) $v['numero'], 'total' => (float) ($v['total'] ?? 0),
            'fecha_venta' => $v['fecha'], 'estado' => $estado, 'error' => $error, 'datos' => json_encode($v), 'updated_at' => now(), 'created_at' => now(),
        ]);
    }
}
