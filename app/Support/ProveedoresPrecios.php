<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ¿A qué proveedor le compro más barato? Por producto, el último costo por unidad que dio cada proveedor en las compras
 * registradas (últimos 6 meses, en soles), cuál es el más barato y cuánto se ahorraría por unidad si la última compra no
 * fue con él. Sale de las compras que ya se registran: no hay que escribir nada nuevo.
 */
class ProveedoresPrecios
{
    public const DIAS = 180;

    /**
     * @param  list<int>|null  $productos  solo estos productos (null = todos)
     * @return Collection<int, object{id: int, nombre: string, unidad: string, proveedores: Collection<int, object>, mejor: object, ultimo: object, ahorro: float}>
     */
    public static function comparar(int $sucursal, ?array $productos = null, string $buscar = ''): Collection
    {
        $filas = DB::table('compras_detalle as d')->join('compras_cabecera as c', 'c.com_cab_id', '=', 'd.com_cab_id')
            ->join('productos as p', 'p.IdProducto', '=', 'd.pro_id')->leftJoin('proveedor as v', 'v.prov_id', '=', 'c.prov_id')
            ->where('c.id_empresa_negocio', $sucursal)->where('c.est_compra', 'Registrado')->where('c.com_fec', '>=', now()->subDays(self::DIAS)->toDateString())
            ->when($productos !== null, fn ($q) => $q->whereIn('d.pro_id', $productos ?: [0]))
            ->when(trim($buscar) !== '', fn ($q) => $q->where('p.pronom', 'like', '%'.trim($buscar).'%'))
            ->orderByDesc('c.com_fec')->orderByDesc('c.com_cab_id')
            ->get(['d.pro_id', 'p.pronom', 'p.umecod', 'c.prov_id', 'c.prov_num', 'v.prov_raz', 'c.com_fec', 'c.mon_id', 'c.tip_cam',
                DB::raw('d.precio_costo / COALESCE(NULLIF(d.factor, 0), 1) as costo')]);

        return $filas->groupBy('pro_id')->map(function (Collection $compras) {
            $p = $compras->first();
            // Lo más reciente de cada proveedor (las compras en dólares, al tipo de cambio de esa compra)
            $proveedores = $compras->groupBy(fn ($c) => $c->prov_id ?: $c->prov_num)->map(fn ($g) => (object) [
                'id' => $g->first()->prov_id ? (int) $g->first()->prov_id : null,
                'proveedor' => trim((string) ($g->first()->prov_raz ?: $g->first()->prov_num)) ?: 'SIN PROVEEDOR',
                'costo' => round((float) $g->first()->costo * ($g->first()->mon_id === 'USD' ? max((float) $g->first()->tip_cam, 1) : 1), 2),
                'fecha' => (string) $g->first()->com_fec,
            ])->sortBy('costo')->values();
            $ultimo = $proveedores->sortByDesc('fecha')->first();
            $mejor = $proveedores->first();

            return (object) [
                'id' => (int) $p->pro_id, 'nombre' => $p->pronom, 'unidad' => $p->umecod ?: 'NIU', 'proveedores' => $proveedores,
                'mejor' => $mejor, 'ultimo' => $ultimo, 'ahorro' => round(max($ultimo->costo - $mejor->costo, 0), 2),
            ];
        })->sortByDesc('ahorro')->values();
    }

    /**
     * El más barato de cada producto (para la búsqueda de Compras), solo si lo compraste a más de un proveedor.
     *
     * @param  list<int>  $productos
     * @return array<int, array{proveedor: string, costo: float, fecha: string}>
     */
    public static function mejores(int $sucursal, array $productos): array
    {
        return self::comparar($sucursal, $productos)->filter(fn ($c) => $c->proveedores->count() > 1)
            ->mapWithKeys(fn ($c) => [$c->id => ['proveedor' => $c->mejor->proveedor, 'costo' => $c->mejor->costo, 'fecha' => $c->mejor->fecha]])->all();
    }
}
