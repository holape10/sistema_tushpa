<?php

namespace App\Support;

use App\Models\ProductoPresentacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Porciones del día de los platos marcados "controla porciones" (juanes, tamales, sopa del día...):
 * cada día empieza en cero, se anota lo preparado y las ventas lo van restando.
 * Lo enviado a cocina y aún no cobrado queda reservado, así nadie pide la porción que ya no hay.
 * Funciona aunque la sucursal venda sin stock: es un control propio de cada plato.
 */
class Porciones
{
    public static function hoy(): string
    {
        return now()->toDateString();
    }

    /**
     * Cuáles de estos productos controlan porciones.
     *
     * @param  iterable<int>  $ids
     * @return array<int, true>
     */
    public static function controlados(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();

        return $ids->isEmpty() ? [] : DB::table('productos')->whereIn('IdProducto', $ids)->where('controla_porciones', 1)
            ->pluck('IdProducto')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Saldo de hoy (preparado − vendido ± ajustes) de cada producto.
     *
     * @param  array<int>  $ids
     * @return array<int, float>
     */
    public static function saldo(int $sucursal, array $ids): array
    {
        return DB::table('porciones_movimientos')->where('id_empresa_negocio', $sucursal)->where('fecha', self::hoy())
            ->whereIn('IdProducto', $ids)->groupBy('IdProducto')->select('IdProducto', DB::raw('SUM(cantidad) as saldo'))
            ->pluck('saldo', 'IdProducto')->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Porciones reservadas por comandas enviadas a cocina y aún no cobradas (el plato y su entrada).
     *
     * @param  array<int>  $ids
     * @return array<int, float>
     */
    public static function reservado(int $sucursal, array $ids, bool $bloquear = false): array
    {
        $lineas = DB::table('pedidos_detalle as d')->join('pedidos as p', 'p.ped_id', '=', 'd.ped_id')
            ->where('p.id_empresa_negocio', $sucursal)->where('p.ped_est', 'Aperturado')->where('d.estadoitem', '!=', 'Eliminado')
            ->whereRaw('d.ped_det_can - IFNULL(d.item_facturado, 0) > 0')
            ->when($bloquear, fn ($q) => $q->sharedLock())
            ->get(['d.IdProducto', 'd.id_presentacion', 'd.opciones', DB::raw('d.ped_det_can - IFNULL(d.item_facturado, 0) as pendiente')]);
        $factores = ProductoPresentacion::whereIn('id_presentacion', $lineas->pluck('id_presentacion')->filter()->unique())->pluck('factor', 'id_presentacion');
        $buscados = array_flip($ids);
        $reservado = [];
        foreach ($lineas as $l) {
            $porPlato = [(int) $l->IdProducto => (float) ($factores[$l->id_presentacion] ?? 1)] + OpcionesPlato::kardexDe($l->opciones);
            foreach ($porPlato as $id => $cant) {
                if (isset($buscados[$id])) {
                    $reservado[$id] = ($reservado[$id] ?? 0) + (float) $l->pendiente * $cant;
                }
            }
        }

        return $reservado;
    }

    /**
     * Revisa que alcancen las porciones de hoy para lo que se quiere pedir (null si alcanza).
     *
     * @param  array<int, float>  $pedido  IdProducto => porciones
     */
    public static function faltante(int $sucursal, array $pedido, bool $bloquear = false): ?string
    {
        $controlados = array_keys(self::controlados(array_keys($pedido)));
        if (! $controlados) {
            return null;
        }
        if ($bloquear) {
            // Dos mozos enviando el mismo plato a la vez: uno espera al otro
            DB::table('productos')->whereIn('IdProducto', $controlados)->orderBy('IdProducto')->lockForUpdate()->get(['IdProducto']);
        }
        $saldo = self::saldo($sucursal, $controlados);
        $reservado = self::reservado($sucursal, $controlados, $bloquear);
        $nombres = DB::table('productos')->whereIn('IdProducto', $controlados)->pluck('pronom', 'IdProducto');
        foreach ($controlados as $id) {
            $quedan = ($saldo[$id] ?? 0) - ($reservado[$id] ?? 0);
            if ($quedan + 0.001 < $pedido[$id]) {
                return $quedan <= 0
                    ? $nombres[$id].' se agotó por hoy. Si preparan más, anótalo en Restaurante › Gestión de Preparados.'
                    : ($quedan < 1.5 ? 'Solo queda 1 porción de ' : 'Solo quedan '.ControlStock::numero($quedan).' porciones de ').$nombres[$id].' por hoy.';
            }
        }

        return null;
    }

    /**
     * Anota un movimiento de hoy.
     */
    public static function anotar(int $sucursal, int $idProducto, string $tipo, float $cantidad, ?int $cabId = null, ?string $obs = null, ?string $fecha = null): void
    {
        DB::table('porciones_movimientos')->insert([
            'IdProducto' => $idProducto, 'tipo' => $tipo, 'cantidad' => round($cantidad, 2), 'fecha' => $fecha ?? self::hoy(),
            'IdCpe_cabecera' => $cabId, 'IdUsuario' => Auth::id(), 'observacion' => $obs ? mb_substr($obs, 0, 150) : null,
            'id_empresa_negocio' => $sucursal, 'created_at' => now(),
        ]);
    }

    /**
     * Venta: resta las porciones. Si viene de una comanda ya estaban reservadas (se revisó al enviar a cocina);
     * en una venta directa se revisa aquí.
     *
     * @param  array<int, float>  $porciones  IdProducto => porciones vendidas
     */
    public static function vender(int $sucursal, int $cabId, array $porciones, bool $desdeComanda): void
    {
        $porciones = array_intersect_key($porciones, self::controlados(array_keys($porciones)));
        if (! $porciones) {
            return;
        }
        if (! $desdeComanda && ($error = self::faltante($sucursal, $porciones, true))) {
            throw new \RuntimeException($error);
        }
        foreach ($porciones as $id => $cant) {
            self::anotar($sucursal, $id, 'VENTA', -$cant, $cabId);
        }
    }

    /** Anulación de una venta: las porciones vuelven al día en que se vendieron */
    public static function revertir(int $cabId): void
    {
        foreach (DB::table('porciones_movimientos')->where('IdCpe_cabecera', $cabId)->where('tipo', 'VENTA')->get() as $m) {
            self::anotar((int) $m->id_empresa_negocio, (int) $m->IdProducto, 'ANULACION', -(float) $m->cantidad, $cabId, 'Venta anulada', $m->fecha);
        }
    }

    /**
     * Platos controlados con su resumen de hoy.
     *
     * @return Collection<int, object{id: int, nombre: string, preparado: float, vendido: float, ajustes: float, reservado: float, quedan: float, receta: bool}>
     */
    public static function resumen(int $sucursal): Collection
    {
        $platos = DB::table('productos')->where('id_empresa_negocio', $sucursal)->where('controla_porciones', 1)->where('proest', 'Activo')
            ->orderBy('pronom')->get(['IdProducto', 'pronom']);
        $ids = $platos->pluck('IdProducto')->map(fn ($i) => (int) $i)->all();
        $movs = DB::table('porciones_movimientos')->where('id_empresa_negocio', $sucursal)->where('fecha', self::hoy())->whereIn('IdProducto', $ids ?: [0])
            ->groupBy('IdProducto', 'tipo')->select('IdProducto', 'tipo', DB::raw('SUM(cantidad) as total'))->get()->groupBy('IdProducto');
        $reservado = self::reservado($sucursal, $ids);
        $conReceta = DB::table('producto_receta')->whereIn('IdProducto', $ids ?: [0])->distinct()->pluck('IdProducto')->flip();

        return $platos->map(function ($p) use ($movs, $reservado, $conReceta) {
            $t = collect($movs[$p->IdProducto] ?? [])->pluck('total', 'tipo')->map(fn ($v) => (float) $v);
            $preparado = (float) ($t['PREPARADO'] ?? 0);
            $vendido = -((float) ($t['VENTA'] ?? 0) + (float) ($t['ANULACION'] ?? 0));
            $ajustes = (float) ($t['AJUSTE'] ?? 0);
            $res = (float) ($reservado[$p->IdProducto] ?? 0);

            return (object) ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'preparado' => $preparado, 'vendido' => $vendido,
                'ajustes' => $ajustes, 'reservado' => $res, 'quedan' => $preparado - $vendido + $ajustes - $res, 'receta' => isset($conReceta[$p->IdProducto])];
        });
    }
}
