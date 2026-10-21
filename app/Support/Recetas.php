<?php

namespace App\Support;

use App\Models\Producto;
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
     * Unidades en que se puede escribir un insumo en la receta. Además de las suyas (el kilo en gramos), la "unidad
     * equivalente" del insumo: huevos comprados por CAJA (1 caja = 30 unidades) se escriben en unidades.
     *
     * @return array<string, float> unidad que se puede escribir => cuánto vale en la unidad del insumo
     */
    public static function unidadesPara(string $umeInsumo, ?string $umeEquivalente = null, float $factorEquivalente = 0): array
    {
        $unidades = self::EQUIVALENTES[$umeInsumo] ?? [$umeInsumo => 1];
        if ($umeEquivalente && $umeEquivalente !== $umeInsumo && $factorEquivalente > 0) {
            foreach (self::EQUIVALENTES[$umeEquivalente] ?? [$umeEquivalente => 1] as $ume => $vale) {
                $unidades[$ume] ??= $vale / $factorEquivalente;
            }
        }

        return $unidades;
    }

    public static function nombreUnidad(string $ume): string
    {
        return self::NOMBRES[$ume] ?? mb_strtolower($ume);
    }

    /** Cantidad de la receta llevada a la unidad del insumo (220 g de un insumo en kg = 0.22; 2 huevos de una caja de 30 = 0.0667) */
    public static function enUnidadDelInsumo(float $cantidad, string $umeEscrita, string $umeInsumo, ?string $umeEquivalente = null, float $factorEquivalente = 0): float
    {
        return $cantidad * (self::unidadesPara($umeInsumo, $umeEquivalente, $factorEquivalente)[$umeEscrita] ?? 1);
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
            ->get(['r.id_receta', 'r.IdInsumo', 'i.pronom as nombre', 'r.cantidad', 'r.umecod', 'i.umecod as ume_insumo', 'i.costo as costo_unitario',
                'i.ume_equivalente', 'i.factor_equivalente'])
            ->map(function ($r) {
                $r->cantidad = (float) $r->cantidad;
                $r->costo_unitario = (float) $r->costo_unitario;
                $r->cantidad_base = self::enUnidadDelInsumo($r->cantidad, $r->umecod, $r->ume_insumo, $r->ume_equivalente, (float) $r->factor_equivalente);
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
            ->get(['r.IdProducto', 'r.cantidad', 'r.umecod', 'i.umecod as ume_insumo', 'i.costo', 'i.ume_equivalente', 'i.factor_equivalente'])
            ->groupBy('IdProducto')
            ->map(fn ($lineas) => round($lineas->sum(fn ($r) => self::enUnidadDelInsumo((float) $r->cantidad, $r->umecod, $r->ume_insumo,
                $r->ume_equivalente, (float) $r->factor_equivalente) * (float) $r->costo), 2))
            ->all();
    }

    /**
     * Costo de combos: la suma de lo que lleva (cada plato al costo de su receta; cada producto a su costo).
     *
     * @param  iterable<int>  $ids
     * @return array<int, float>
     */
    public static function costosCombos(iterable $ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $partes = DB::table('combos as c')->join('productos as p', 'p.IdProducto', '=', 'c.IdProducto_comb')
            ->whereIn('c.IdProducto_rel', $ids)->get(['c.IdProducto_rel', 'c.IdProducto_comb', 'c.prod_comb_cant', 'p.costo', 'p.promocion']);
        $recetas = self::costos($partes->whereIn('promocion', [2, Producto::OPCION])->pluck('IdProducto_comb'));

        return $partes->groupBy('IdProducto_rel')->map(fn ($g) => round($g->sum(fn ($c) => (float) $c->prod_comb_cant
            * (float) ($recetas[$c->IdProducto_comb] ?? $c->costo)), 2))->all();
    }

    /** Costo de una unidad de cualquier producto: el plato y la entrada por su receta, el combo por lo que lleva, el resto su costo */
    public static function costoActual(object $producto): float
    {
        $tipo = (int) $producto->promocion;
        $id = (int) $producto->IdProducto;
        if (in_array($tipo, [2, Producto::OPCION], true)) {
            return self::costos([$id])[$id] ?? (float) $producto->costo;
        }
        if ($tipo === 6) {
            return self::costosCombos([$id])[$id] ?? (float) $producto->costo;
        }

        return (float) $producto->costo;
    }

    /**
     * Cuando cambia el costo de insumos o productos, deja al día el costo guardado de los platos, entradas y combos que
     * los usan (para la lista de productos, inventarios y reportes).
     *
     * @param  iterable<int>  $ids
     */
    public static function refrescarCostos(iterable $ids): void
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }
        $platos = DB::table('producto_receta')->whereIn('IdInsumo', $ids)->distinct()->pluck('IdProducto');
        foreach (self::costos($platos) as $id => $costo) {
            DB::table('productos')->where('IdProducto', $id)->update(['costo' => $costo]);
        }
        $combos = DB::table('combos')->whereIn('IdProducto_comb', $ids->merge($platos))->distinct()->pluck('IdProducto_rel');
        foreach (self::costosCombos($combos) as $id => $costo) {
            DB::table('productos')->where('IdProducto', $id)->update(['costo' => $costo]);
        }
    }

    /**
     * Insumos sin precio de compra en las recetas de estos platos: hacen que el food cost salga más bajo de lo real.
     *
     * @param  iterable<int>  $platos
     * @return array<int, array<int, string>> plato => nombres de los insumos sin precio
     */
    public static function sinPrecio(iterable $platos): array
    {
        return DB::table('producto_receta as r')->join('productos as i', 'i.IdProducto', '=', 'r.IdInsumo')
            ->whereIn('r.IdProducto', collect($platos)->all() ?: [0])->where(fn ($q) => $q->whereNull('i.costo')->orWhere('i.costo', '<=', 0))
            ->get(['r.IdProducto', 'i.pronom'])->groupBy('IdProducto')->map(fn ($g) => $g->pluck('pronom')->all())->all();
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
            ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->whereIn('p.promocion', [2, Producto::OPCION])
            ->orderBy('c.cat_nom')->orderBy('p.pronom')
            ->get(['p.IdProducto', 'p.pronom', 'p.propun', 'p.cat_id', 'c.cat_nom', 'p.promocion']);
        $precios = Precios::vigentes($platos);
        $costos = self::costos($platos->pluck('IdProducto'));
        $ingredientes = DB::table('producto_receta')->whereIn('IdProducto', $platos->pluck('IdProducto'))
            ->groupBy('IdProducto')->select('IdProducto', DB::raw('COUNT(*) as n'))->pluck('n', 'IdProducto');
        $sinPrecio = self::sinPrecio($platos->pluck('IdProducto'));

        return $platos->map(function ($p) use ($precios, $costos, $ingredientes, $sinPrecio) {
            $p->precio = (float) ($precios[$p->IdProducto] ?? $p->propun);
            $p->tiene_receta = isset($costos[$p->IdProducto]);
            $p->ingredientes = (int) ($ingredientes[$p->IdProducto] ?? 0);
            $p->costo = $costos[$p->IdProducto] ?? null;
            $p->food_cost = $p->tiene_receta ? self::foodCost($p->costo, $p->precio) : null;
            $p->ganancia = $p->tiene_receta ? round($p->precio - $p->costo, 2) : null;
            $p->estado = self::estado($p->food_cost);
            // Un ingrediente sin precio cuenta como gratis: el food cost no es real hasta completarlo
            $p->sin_precio = $sinPrecio[$p->IdProducto] ?? [];
            if ($p->sin_precio) {
                $p->estado = ['nivel' => 'incompleto', 'color' => '#7c3aed', 'texto' => 'Falta precio'];
            }

            return $p;
        });
    }

    /** Anota un cambio de costo de un insumo o producto (compra o receta) para avisar qué platos cambian */
    public static function registrarCambioCosto(int $idProducto, float $antes, float $nuevo, string $origen, ?int $comCabId, int $sucursal): void
    {
        if (abs($nuevo - $antes) < 0.01) {
            return;
        }
        DB::table('costo_historial')->insert([
            'IdProducto' => $idProducto, 'costo_anterior' => $antes, 'costo_nuevo' => $nuevo, 'origen' => $origen,
            'com_cab_id' => $comCabId, 'id_empresa_negocio' => $sucursal, 'created_at' => now(),
        ]);
    }

    /**
     * Insumos que subieron de costo (desde una fecha o en una compra) y los platos que ahora ganan menos.
     *
     * @return Collection<int, object{insumo: string, antes: float, ahora: float, platos: Collection<int, object>}>
     */
    public static function subidas(int $sucursal, ?int $comCabId = null, int $dias = 15): Collection
    {
        $cambios = DB::table('costo_historial as h')->join('productos as p', 'p.IdProducto', '=', 'h.IdProducto')
            ->where('h.id_empresa_negocio', $sucursal)
            ->when($comCabId, fn ($q) => $q->where('h.com_cab_id', $comCabId), fn ($q) => $q->where('h.created_at', '>=', now()->subDays($dias)))
            ->whereExists(fn ($e) => $e->from('producto_receta as r')->whereColumn('r.IdInsumo', 'h.IdProducto'))
            ->orderBy('h.id')->get(['h.IdProducto', 'p.pronom', 'h.costo_anterior', 'h.costo_nuevo'])
            // Si cambió varias veces, cuenta desde el primer costo hasta el último
            ->groupBy('IdProducto')->map(fn ($g) => (object) [
                'id' => (int) $g->first()->IdProducto, 'insumo' => $g->first()->pronom,
                'antes' => (float) $g->first()->costo_anterior, 'ahora' => (float) $g->last()->costo_nuevo,
            ])->filter(fn ($c) => $c->ahora > $c->antes)->values();
        if ($cambios->isEmpty()) {
            return $cambios;
        }

        $usos = DB::table('producto_receta')->whereIn('IdInsumo', $cambios->pluck('id'))->get(['IdProducto', 'IdInsumo'])->groupBy('IdInsumo');
        $platos = self::platos($sucursal)->keyBy('IdProducto');

        return $cambios->map(function ($c) use ($usos, $platos) {
            $c->platos = collect($usos[$c->id] ?? [])->map(fn ($u) => $platos[$u->IdProducto] ?? null)->filter()->values();

            return $c;
        });
    }

    /** Aviso en palabras simples para después de una compra (null si no subió nada que esté en recetas) */
    public static function avisoDeCompra(int $sucursal, int $comCabId): ?string
    {
        $subidas = self::subidas($sucursal, $comCabId);
        if ($subidas->isEmpty()) {
            return null;
        }
        $partes = $subidas->map(fn ($s) => "Subió {$s->insumo} (S/ ".number_format($s->antes, 2).' → S/ '.number_format($s->ahora, 2).')');
        $platos = $subidas->flatMap(fn ($s) => $s->platos)->unique('IdProducto');
        $altos = $platos->where('estado.nivel', 'alto');

        return $partes->implode(' · ').'. '.($platos->count() === 1 ? '1 plato ahora gana menos' : $platos->count().' platos ahora ganan menos')
            .($altos->isNotEmpty() ? '; ojo con: '.$altos->pluck('pronom')->take(4)->implode(', ').' (food cost alto)' : '')
            .'. Revisa sus precios en Recetas y Food Cost.';
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
