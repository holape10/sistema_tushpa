<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recetas y food cost de los platos (productos Preparados).
 *
 * Food cost = costo de los ingredientes ÷ precio de venta. Ejemplo: un plato de S/ 25 que gasta S/ 7 en ingredientes
 * tiene 28% de food cost: de cada S/ 10 que cobras, S/ 2.80 se van en ingredientes. Lo sano en restaurantes: 25% a 35%.
 */
class Recetas
{
    /** Hasta aquí el food cost está bien; hasta ALERTA conviene revisar; más arriba el plato deja poca ganancia */
    public const BIEN = 35.0;

    public const ALERTA = 45.0;

    /** Food cost con el que se sugiere el precio de venta */
    public const META = 30.0;

    /** Unidades que se pueden escribir en la receta según la unidad del insumo (el kilo se puede escribir en gramos) */
    private const EQUIVALENTES = [
        'KGM' => ['KGM' => 1, 'GRM' => 0.001],
        'GRM' => ['GRM' => 1, 'KGM' => 1000],
        'LTR' => ['LTR' => 1, 'MLT' => 0.001],
        'MLT' => ['MLT' => 1, 'LTR' => 1000],
    ];

    /** Nombres cortos para el usuario */
    public const NOMBRES = ['KGM' => 'kg', 'GRM' => 'g', 'LTR' => 'L', 'MLT' => 'ml', 'NIU' => 'unid.', 'GLL' => 'galón', 'BX' => 'caja'];

    /**
     * @return array<string, float> unidad que se puede escribir => cuánto vale en la unidad del insumo
     */
    public static function unidadesPara(string $umeInsumo): array
    {
        return self::EQUIVALENTES[$umeInsumo] ?? [$umeInsumo => 1];
    }

    public static function nombreUnidad(string $ume): string
    {
        return self::NOMBRES[$ume] ?? mb_strtolower($ume);
    }

    /** Cantidad de la receta llevada a la unidad del insumo (220 g de un insumo en kg = 0.22) */
    public static function enUnidadDelInsumo(float $cantidad, string $umeEscrita, string $umeInsumo): float
    {
        return $cantidad * (self::unidadesPara($umeInsumo)[$umeEscrita] ?? 1);
    }

    /**
     * Ingredientes de un plato con su costo.
     *
     * @return Collection<int, object{id_receta: int, IdInsumo: int, nombre: string, cantidad: float, umecod: string, ume_insumo: string, costo_unitario: float, cantidad_base: float, costo: float}>
     */
    public static function de(int $idProducto): Collection
    {
        return DB::table('producto_receta as r')->join('productos as i', 'i.IdProducto', '=', 'r.IdInsumo')
            ->where('r.IdProducto', $idProducto)->orderBy('r.id_receta')
            ->get(['r.id_receta', 'r.IdInsumo', 'i.pronom as nombre', 'r.cantidad', 'r.umecod', 'i.umecod as ume_insumo', 'i.costo as costo_unitario'])
            ->map(function ($r) {
                $r->cantidad = (float) $r->cantidad;
                $r->costo_unitario = (float) $r->costo_unitario;
                $r->cantidad_base = self::enUnidadDelInsumo($r->cantidad, $r->umecod, $r->ume_insumo);
                $r->costo = round($r->cantidad_base * $r->costo_unitario, 4);

                return $r;
            });
    }

    /**
     * Costo de ingredientes de varios platos (solo los que tienen receta).
     *
     * @param  iterable<int>  $ids
     * @return array<int, float>
     */
    public static function costos(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('producto_receta as r')->join('productos as i', 'i.IdProducto', '=', 'r.IdInsumo')
            ->whereIn('r.IdProducto', $ids)
            ->get(['r.IdProducto', 'r.cantidad', 'r.umecod', 'i.umecod as ume_insumo', 'i.costo'])
            ->groupBy('IdProducto')
            ->map(fn ($lineas) => round($lineas->sum(fn ($r) => self::enUnidadDelInsumo((float) $r->cantidad, $r->umecod, $r->ume_insumo) * (float) $r->costo), 2))
            ->all();
    }

    /** Food cost en % (null si el plato no tiene precio) */
    public static function foodCost(float $costo, float $precio): ?float
    {
        return $precio > 0 ? round($costo / $precio * 100, 1) : null;
    }

    /**
     * Cómo le va al plato, en palabras simples.
     *
     * @return array{nivel: string, color: string, texto: string}
     */
    public static function estado(?float $foodCost): array
    {
        return match (true) {
            $foodCost === null => ['nivel' => 'sin', 'color' => '#94a3b8', 'texto' => 'Sin precio'],
            $foodCost <= self::BIEN => ['nivel' => 'bien', 'color' => '#059669', 'texto' => '¡Muy bien!'],
            $foodCost <= self::ALERTA => ['nivel' => 'ojo', 'color' => '#d97706', 'texto' => 'Revisa'],
            default => ['nivel' => 'alto', 'color' => '#dc2626', 'texto' => 'Ganas poco'],
        };
    }

    /** Precio con el que el plato quedaría en el food cost META (redondeado a 50 céntimos hacia arriba) */
    public static function precioSugerido(float $costo): float
    {
        return $costo > 0 ? ceil($costo / (self::META / 100) * 2) / 2 : 0.0;
    }

    /**
     * Platos de la sucursal con su costo, precio vigente y food cost.
     *
     * @return Collection<int, object>
     */
    public static function platos(int $sucursal): Collection
    {
        $platos = DB::table('productos as p')->leftJoin('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->where('p.promocion', 2)
            ->orderBy('c.cat_nom')->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.propun', 'p.cat_id', 'c.cat_nom']);
        $precios = Precios::vigentes($platos);
        $costos = self::costos($platos->pluck('IdProducto'));
        $ingredientes = DB::table('producto_receta')->whereIn('IdProducto', $platos->pluck('IdProducto'))
            ->groupBy('IdProducto')->select('IdProducto', DB::raw('COUNT(*) as n'))->pluck('n', 'IdProducto');

        return $platos->map(function ($p) use ($precios, $costos, $ingredientes) {
            $p->precio = (float) ($precios[$p->IdProducto] ?? $p->propun);
            $p->tiene_receta = isset($costos[$p->IdProducto]);
            $p->ingredientes = (int) ($ingredientes[$p->IdProducto] ?? 0);
            $p->costo = $costos[$p->IdProducto] ?? null;
            $p->food_cost = $p->tiene_receta ? self::foodCost($p->costo, $p->precio) : null;
            $p->ganancia = $p->tiene_receta ? round($p->precio - $p->costo, 2) : null;
            $p->estado = self::estado($p->food_cost);

            return $p;
        });
    }

    /**
     * Guarda la receta completa de un plato (reemplaza la anterior) y deja su costo en el producto.
     *
     * @param  array<int, array{insumo: int, cantidad: float, umecod: string}>  $lineas
     */
    public static function guardar(int $idProducto, int $sucursal, array $lineas): float
    {
        return DB::transaction(function () use ($idProducto, $sucursal, $lineas) {
            DB::table('producto_receta')->where('IdProducto', $idProducto)->delete();
            $ahora = now();
            foreach ($lineas as $l) {
                DB::table('producto_receta')->insert([
                    'IdProducto' => $idProducto, 'IdInsumo' => $l['insumo'], 'cantidad' => $l['cantidad'], 'umecod' => $l['umecod'],
                    'id_empresa_negocio' => $sucursal, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
            $costo = self::costos([$idProducto])[$idProducto] ?? 0.0;
            DB::table('productos')->where('IdProducto', $idProducto)->update(['costo' => $costo]);

            return $costo;
        });
    }
}
