<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ventas sin costo (casi siempre las importadas del sistema antiguo, que no lo guardaba): en la rentabilidad su utilidad
 * sale igual a la venta. Se completan con el costo actual de cada producto (por la presentación vendida), que es lo más
 * cercano que hay. Solo las líneas con producto que hoy sí tiene costo; las anuladas no cuentan.
 */
class CostosFaltantes
{
    /**
     * @param  array<int, int>  $sucursales
     * @return array{lineas: int, ventas: float}
     */
    public static function contar(array $sucursales): array
    {
        $r = self::consulta($sucursales)->selectRaw('COUNT(*) AS lineas, COALESCE(SUM(d.cdevve), 0) AS ventas')->first();

        return ['lineas' => (int) $r->lineas, 'ventas' => round((float) $r->ventas, 2)];
    }

    /**
     * @param  array<int, int>  $sucursales
     * @return int líneas completadas
     */
    public static function completar(array $sucursales): int
    {
        return self::consulta($sucursales)->update(['d.costo' => DB::raw('ROUND(p.costo * GREATEST(1, COALESCE(d.cpe_det_factor, 1)), 2)')]);
    }

    /** @param  array<int, int>  $sucursales */
    private static function consulta(array $sucursales)
    {
        return DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->whereIn('c.id_empresa_negocio', $sucursales)->whereNull('c.ccabaj')
            ->where(fn ($q) => $q->whereNull('d.costo')->orWhere('d.costo', 0))
            ->where('p.costo', '>', 0);
    }
}
