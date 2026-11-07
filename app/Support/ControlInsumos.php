<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Control de insumos: por cada insumo o producto, cuánto se debió gastar según lo vendido (las recetas de los platos
 * vendidos, menos lo devuelto por anulaciones), cuánto se botó con merma registrada y cuánto faltó o sobró en los
 * conteos (inventarios). Lo que falta en el conteo y nadie registró es lo que se pierde sin explicación (robo,
 * desperdicio, porciones más grandes): la desviación es ese faltante frente a lo que se debió gastar.
 */
class ControlInsumos
{
    /** Desde cuánto (en %) una desviación merece revisarse */
    public const ALERTA = 5;

    /**
     * @return Collection<int, object{id: int, nombre: string, unidad: string, costo: float, teorico: float, mermas: float, faltante: float, sobrante: float, neto: float, desviacion: ?float, valor: float}>
     */
    public static function reporte(int $sucursal, int $almacen, string $desde, string $hasta): Collection
    {
        $suma = fn (string $condicion, string $alias) => "COALESCE(SUM(CASE WHEN {$condicion} THEN m.cantidad ELSE 0 END), 0) as {$alias}";

        return DB::table('movimientos_productos as m')->join('productos as p', 'p.IdProducto', '=', 'm.IdProducto')
            ->where('m.id_almacen', $almacen)->where('p.id_empresa_negocio', $sucursal)->whereIn('p.promocion', Compras::TIPOS_CON_STOCK)
            ->whereBetween('m.fecha_mov', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->groupBy('p.IdProducto', 'p.pronom', 'p.umecod', 'p.costo')
            ->select('p.IdProducto', 'p.pronom', 'p.umecod', 'p.costo')
            ->selectRaw(implode(', ', [
                $suma("m.mov_tip = 'E' AND m.IdCpe_cabecera IS NOT NULL AND m.merma_id IS NULL", 'vendido'),
                $suma("m.mov_tip = 'I' AND m.IdCpe_cabecera IS NOT NULL AND m.cod_tip_ope = '05'", 'devuelto'),
                $suma("m.mov_tip = 'E' AND m.merma_id IS NOT NULL", 'merma_salida'),
                $suma("m.mov_tip = 'I' AND m.merma_id IS NOT NULL", 'merma_vuelta'),
                $suma("m.mov_tip = 'E' AND m.inv_cab_id IS NOT NULL", 'faltante'),
                $suma("m.mov_tip = 'I' AND m.inv_cab_id IS NOT NULL", 'sobrante'),
            ]))
            ->get()
            ->map(function ($r) {
                $teorico = max((float) $r->vendido - (float) $r->devuelto, 0);
                $neto = round((float) $r->faltante - (float) $r->sobrante, 3);

                return (object) [
                    'id' => (int) $r->IdProducto, 'nombre' => $r->pronom, 'unidad' => $r->umecod ?: 'NIU', 'costo' => (float) $r->costo,
                    'teorico' => round($teorico, 3), 'mermas' => round(max((float) $r->merma_salida - (float) $r->merma_vuelta, 0), 3),
                    'faltante' => round((float) $r->faltante, 3), 'sobrante' => round((float) $r->sobrante, 3), 'neto' => $neto,
                    'desviacion' => $teorico > 0 ? round($neto / $teorico * 100, 1) : null,
                    'valor' => round($neto * (float) $r->costo, 2),
                ];
            })
            ->filter(fn ($r) => $r->teorico > 0 || $r->mermas > 0 || $r->faltante > 0 || $r->sobrante > 0)
            ->sortByDesc('valor')->values();
    }
}
