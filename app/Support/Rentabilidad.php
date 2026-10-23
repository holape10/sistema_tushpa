<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rentabilidad del negocio a partir de las ventas, las recetas y el kardex:
 *  - Ingeniería de menú: cada plato según cuánto se vende y cuánto deja (estrella, caballo de batalla, enigma, perro).
 *  - Food cost real: el % de lo vendido que se fue en costo, mes a mes y por categoría.
 *  - Teórico vs. real: lo que debió gastarse de cada insumo según las recetas contra lo que faltó en los conteos de inventario.
 *  - Lista de compras: qué insumos y productos se acaban y cuánto comprar.
 *  - Historial de precios: cómo subió el costo de cada insumo y a cuántos platos afecta.
 */
class Rentabilidad
{
    /** Las notas de crédito restan */
    private const SIGNO = "CASE WHEN c.tdocod = '07' THEN -1 ELSE 1 END";

    /** Solo las notas de crédito por devolución o anulación devuelven cantidad */
    private const SIGNO_CANT = "CASE WHEN c.tdocod = '07' AND c.tipnot IN ('01','02','06','07') THEN -1 WHEN c.tdocod IN ('07','08') THEN 0 ELSE 1 END";

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> clase => [nombre, ícono, color, qué hacer] */
    public const CLASES = [
        'ESTRELLA' => ['Estrellas', '⭐', '#059669', 'Se venden mucho y dejan buena ganancia. Cuídalos: misma receta y calidad, y destácalos en la carta.'],
        'CABALLO' => ['Caballos de batalla', '🐴', '#d97706', 'Se venden mucho pero dejan poco. Sube un poco el precio o ajusta la receta (porción, insumo más barato).'],
        'ENIGMA' => ['Enigmas', '🧩', '#4f46e5', 'Dejan buena ganancia pero se venden poco. Promociónalos: que el mozo los recomiende, foto en la carta, combo.'],
        'PERRO' => ['Perros', '🐶', '#dc2626', 'Se venden poco y dejan poco. Evalúa sacarlos de la carta o cambiarlos.'],
    ];

    /** Ventas, costo y ganancia de cada producto vendido en el periodo (con el costo que tenía al venderse) */
    public static function ventasPorProducto(int $suc, string $desde, string $hasta, ?int $categoria = null): Collection
    {
        return DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->leftJoin('categorias as cat', 'cat.cat_id', '=', 'p.cat_id')
            ->where('c.id_empresa_negocio', $suc)->whereBetween('c.ccafem', [$desde, $hasta])->whereNull('c.ccabaj')
            ->when($categoria, fn ($q) => $q->where('p.cat_id', $categoria))
            ->groupBy('p.IdProducto', 'p.pronom', 'p.promocion', 'cat.cat_nom')
            ->select('p.IdProducto', 'p.pronom', 'p.promocion', DB::raw("COALESCE(cat.cat_nom, 'SIN CATEGORÍA') as categoria"),
                DB::raw('SUM('.self::SIGNO_CANT.' * d.cdecan) as cantidad'), DB::raw('SUM('.self::SIGNO.' * d.cdevve) as ventas'),
                DB::raw('SUM('.self::SIGNO_CANT.' * d.costo * d.cdecan) as costo'))
            ->get()
            ->map(fn ($r) => (object) ['id' => (int) $r->IdProducto, 'nombre' => $r->pronom, 'categoria' => $r->categoria, 'tipo' => (int) $r->promocion,
                'cantidad' => round((float) $r->cantidad, 3), 'ventas' => round((float) $r->ventas, 2), 'costo' => round((float) $r->costo, 2),
                'ganancia' => round((float) $r->ventas - (float) $r->costo, 2)])
            ->filter(fn ($r) => $r->cantidad > 0)->values();
    }

    /**
     * Ingeniería de menú (método Kasavana y Smith): es "popular" si vende al menos el 70% de lo que le tocaría
     * si todos vendieran igual, y "rentable" si su ganancia por unidad pasa el promedio ponderado.
     *
     * @return array{platos: Collection, sinCosto: Collection, popularidad_min: float, ganancia_promedio: float}
     */
    public static function ingenieriaMenu(int $suc, string $desde, string $hasta, ?int $categoria = null): array
    {
        $todos = self::ventasPorProducto($suc, $desde, $hasta, $categoria);
        // Sin costo registrado no se puede saber si deja ganancia: se listan aparte para completarlos
        [$conCosto, $sinCosto] = $todos->partition(fn ($p) => $p->costo > 0);

        return self::clasificar($conCosto->values()) + ['sinCosto' => $sinCosto->sortByDesc('ventas')->values()];
    }

    /**
     * Clasifica los productos vendidos (con cantidad, ventas, costo y ganancia).
     *
     * @return array{platos: Collection, popularidad_min: float, ganancia_promedio: float}
     */
    public static function clasificar(Collection $conCosto): array
    {
        $unidades = (float) $conCosto->sum('cantidad');
        $n = $conCosto->count();
        $popularidadMin = $n ? 0.7 / $n * 100 : 0.0;
        $gananciaPromedio = $unidades > 0 ? (float) $conCosto->sum('ganancia') / $unidades : 0.0;

        $platos = $conCosto->map(function ($p) use ($unidades, $popularidadMin, $gananciaPromedio) {
            $p->participacion = $unidades > 0 ? round($p->cantidad / $unidades * 100, 2) : 0;
            $p->ganancia_unidad = round($p->ganancia / $p->cantidad, 2);
            $p->food_cost = $p->ventas > 0 ? round($p->costo / $p->ventas * 100, 1) : null;
            $popular = $p->participacion >= $popularidadMin;
            $rentable = $p->ganancia_unidad >= $gananciaPromedio;
            $p->clase = $popular ? ($rentable ? 'ESTRELLA' : 'CABALLO') : ($rentable ? 'ENIGMA' : 'PERRO');

            return $p;
        })->sortByDesc('ganancia')->values();

        return ['platos' => $platos, 'popularidad_min' => round($popularidadMin, 2), 'ganancia_promedio' => round($gananciaPromedio, 2)];
    }

    /**
     * Food cost real: de cada sol vendido, cuánto fue costo. Mes a mes (los últimos meses) y por categoría en el periodo.
     *
     * @return array{meses: Collection, categorias: Collection, total: object}
     */
    public static function foodCost(int $suc, string $desde, string $hasta, int $meses = 6): array
    {
        $inicio = Carbon::parse($hasta)->startOfMonth()->subMonths($meses - 1)->toDateString();
        $porMes = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $suc)->whereBetween('c.ccafem', [$inicio, $hasta])->whereNull('c.ccabaj')
            ->groupBy(DB::raw("DATE_FORMAT(c.ccafem, '%Y-%m')"))
            ->select(DB::raw("DATE_FORMAT(c.ccafem, '%Y-%m') as mes"), DB::raw('SUM('.self::SIGNO.' * d.cdevve) as ventas'),
                DB::raw('SUM('.self::SIGNO_CANT.' * d.costo * d.cdecan) as costo'),
                DB::raw('SUM(CASE WHEN COALESCE(d.costo, 0) = 0 THEN '.self::SIGNO.' * d.cdevve ELSE 0 END) as sin_costo'))
            ->orderBy('mes')->get()->keyBy('mes');
        $fila = fn ($nombre, $ventas, $costo, $sinCosto = 0) => (object) ['nombre' => $nombre, 'ventas' => round((float) $ventas, 2), 'costo' => round((float) $costo, 2),
            'ganancia' => round((float) $ventas - (float) $costo, 2), 'sin_costo' => round((float) $sinCosto, 2),
            'food_cost' => (float) $ventas > 0 ? round((float) $costo / (float) $ventas * 100, 1) : null];
        $lista = collect(range($meses - 1, 0))->map(function ($i) use ($hasta, $porMes, $fila) {
            $m = Carbon::parse($hasta)->startOfMonth()->subMonths($i);
            $r = $porMes[$m->format('Y-m')] ?? null;

            return $fila(ucfirst($m->locale('es')->translatedFormat('M Y')), $r->ventas ?? 0, $r->costo ?? 0, $r->sin_costo ?? 0);
        });

        $productos = self::ventasPorProducto($suc, $desde, $hasta);
        $categorias = $productos->groupBy('categoria')->map(fn ($g, $c) => $fila($c, $g->sum('ventas'), $g->sum('costo'), $g->where('costo', '<=', 0)->sum('ventas')))
            ->sortByDesc('ventas')->values();

        return ['meses' => $lista, 'categorias' => $categorias,
            'total' => $fila('Total', $productos->sum('ventas'), $productos->sum('costo'), $productos->where('costo', '<=', 0)->sum('ventas'))];
    }

    /**
     * Teórico vs. real de cada insumo: lo que debió salir por las recetas de lo vendido, las mermas anotadas y lo que
     * faltó (o sobró) al contar el inventario. Lo que falta sin explicación es la pérdida: tragos más cargados, robo o
     * desperdicio sin registrar.
     *
     * @return array{insumos: Collection, conteos: int}
     */
    public static function teoricoVsReal(int $suc, string $desde, string $hasta): array
    {
        $signo = "CASE WHEN m.mov_tip = 'E' THEN m.cantidad ELSE -m.cantidad END";
        $porReceta = "m.merma_id IS NULL AND m.inv_cab_id IS NULL AND m.IdProducto_rel IS NOT NULL AND m.IdProducto_rel <> m.IdProducto AND m.cod_tip_ope IN ('01','05') AND m.IdCpe_cabecera IS NOT NULL";
        $porConteo = "m.inv_cab_id IS NOT NULL AND m.cod_tip_ope = '28'";
        $filas = DB::table('movimientos_productos as m')->join('productos as i', 'i.IdProducto', '=', 'm.IdProducto')
            ->where('m.id_empresa_negocio', $suc)->whereBetween('m.fecha_mov', [$desde, $hasta])
            ->where(fn ($w) => $w->whereRaw("($porReceta)")->orWhereNotNull('m.merma_id')->orWhereRaw("($porConteo)"))
            ->groupBy('i.IdProducto', 'i.pronom', 'i.umecod', 'i.ume_equivalente', 'i.factor_equivalente', 'i.costo')
            ->select('i.IdProducto', 'i.pronom', 'i.umecod', 'i.ume_equivalente', 'i.factor_equivalente', 'i.costo',
                DB::raw("SUM(CASE WHEN $porReceta THEN $signo ELSE 0 END) as teorico"),
                DB::raw("SUM(CASE WHEN m.merma_id IS NOT NULL THEN $signo ELSE 0 END) as mermas"),
                // Salida en el conteo = faltó; entrada = sobró
                DB::raw("SUM(CASE WHEN $porConteo THEN $signo ELSE 0 END) as diferencia"))
            ->get()
            ->map(function ($r) {
                $teorico = round((float) $r->teorico, 5);
                $diferencia = round((float) $r->diferencia, 5);
                $costo = (float) $r->costo;

                return (object) ['id' => (int) $r->IdProducto, 'nombre' => $r->pronom, 'umecod' => $r->umecod ?: 'NIU',
                    'ume_equivalente' => $r->ume_equivalente, 'factor' => (float) $r->factor_equivalente, 'costo' => $costo,
                    'teorico' => $teorico, 'mermas' => round((float) $r->mermas, 5), 'diferencia' => $diferencia,
                    'real' => round($teorico + (float) $r->mermas + $diferencia, 5),
                    'perdida' => round($diferencia * $costo, 2),
                    'desviacion' => $teorico > 0 ? round($diferencia / $teorico * 100, 1) : null];
            })
            ->filter(fn ($r) => abs($r->teorico) > 0.00001 || abs($r->diferencia) > 0.00001 || abs($r->mermas) > 0.00001)
            ->sortByDesc('perdida')->values();
        $conteos = DB::table('inventario_cabecera')->where('id_empresa_negocio', $suc)->whereBetween('fecha', [$desde, $hasta])->count();

        return ['insumos' => $filas, 'conteos' => $conteos];
    }

    /**
     * Lista de compras: consumo diario (ventas, recetas y mermas de los últimos días), stock de todos los almacenes,
     * para cuántos días alcanza y cuánto comprar para cubrir los días pedidos (redondeado a unidades enteras).
     */
    public static function listaCompras(int $suc, int $diasConsumo = 14, int $diasCubrir = 7): Collection
    {
        $desde = now()->subDays($diasConsumo)->toDateString();
        $signo = "CASE WHEN m.mov_tip = 'E' THEN m.cantidad ELSE -m.cantidad END";
        $consumo = DB::table('movimientos_productos as m')->where('m.id_empresa_negocio', $suc)->where('m.fecha_mov', '>=', $desde)
            ->where(fn ($w) => $w->whereNotNull('m.merma_id')->orWhere(fn ($v) => $v->whereIn('m.cod_tip_ope', ['01', '05'])->whereNotNull('m.IdCpe_cabecera')))
            ->groupBy('m.IdProducto')->select('m.IdProducto', DB::raw("SUM($signo) as total"))->pluck('total', 'IdProducto');
        $almacenes = DB::table('almacenes')->where('id_empresa_negocio', $suc)->pluck('id_almacen');
        $stock = DB::table('producto_stock')->whereIn('id_almacen', $almacenes)->groupBy('IdProducto')
            ->select('IdProducto', DB::raw('SUM(stock) as stock'))->pluck('stock', 'IdProducto');
        $ultimaCompra = DB::table('compras_detalle as d')->join('compras_cabecera as c', 'c.com_cab_id', '=', 'd.com_cab_id')
            ->leftJoin('proveedor as pr', 'pr.prov_id', '=', 'c.prov_id')->where('c.id_empresa_negocio', $suc)->where('c.est_compra', 'Registrado')
            ->orderBy('c.com_fec')->orderBy('c.com_cab_id')->get(['d.pro_id', 'c.com_fec', 'pr.prov_raz'])->keyBy('pro_id');

        return DB::table('productos')->where('id_empresa_negocio', $suc)->where('proest', 'Activo')->whereIn('promocion', [0, 4])
            ->get(['IdProducto', 'pronom', 'umecod', 'ume_equivalente', 'factor_equivalente', 'costo', 'stock_min', 'promocion'])
            ->map(function ($p) use ($consumo, $stock, $ultimaCompra, $diasConsumo, $diasCubrir) {
                $porDia = max(0, (float) ($consumo[$p->IdProducto] ?? 0)) / $diasConsumo;
                $hay = (float) ($stock[$p->IdProducto] ?? 0);
                $necesita = $porDia * $diasCubrir + (float) $p->stock_min - $hay;
                $comprar = $necesita > 0.0001 ? (float) ceil($necesita - 1e-6) : 0.0;
                $compra = $ultimaCompra[$p->IdProducto] ?? null;

                return (object) ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'umecod' => $p->umecod ?: 'NIU', 'ume_equivalente' => $p->ume_equivalente,
                    'factor' => (float) $p->factor_equivalente, 'insumo' => (int) $p->promocion === 4, 'costo' => (float) $p->costo,
                    'por_dia' => round($porDia, 4), 'stock' => round($hay, 5), 'minimo' => (float) $p->stock_min,
                    'dias' => $porDia > 0 ? round(max(0, $hay) / $porDia, 1) : null, 'comprar' => $comprar, 'importe' => round($comprar * (float) $p->costo, 2),
                    'proveedor' => $compra->prov_raz ?? null, 'ultima_compra' => $compra->com_fec ?? null];
            })
            ->filter(fn ($p) => $p->comprar > 0)
            ->sortBy(fn ($p) => [$p->dias ?? 9999, $p->nombre])->values();
    }

    /** Cambios de costo de los insumos en el periodo: primer y último costo, cuánto subió y a cuántos platos afecta */
    public static function historialPrecios(int $suc, string $desde, string $hasta): Collection
    {
        // Solo lo que se compra (insumos y productos de reventa): el costo de un plato cambia al editar su receta, no por precio
        $cambios = DB::table('costo_historial as h')->join('productos as p', 'p.IdProducto', '=', 'h.IdProducto')
            ->where('h.id_empresa_negocio', $suc)->whereIn('p.promocion', [0, 4])->whereBetween('h.created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderBy('h.id')->get(['h.IdProducto', 'p.pronom', 'p.umecod', 'p.costo', 'h.costo_anterior', 'h.costo_nuevo', 'h.origen', 'h.created_at'])
            ->groupBy('IdProducto');
        $usos = DB::table('producto_receta')->whereIn('IdInsumo', $cambios->keys())->groupBy('IdInsumo')
            ->select('IdInsumo', DB::raw('COUNT(DISTINCT IdProducto) as n'))->pluck('n', 'IdInsumo');

        return $cambios->map(function ($g, $id) use ($usos) {
            $antes = (float) $g->first()->costo_anterior;
            $ahora = (float) $g->last()->costo_nuevo;

            return (object) ['id' => (int) $id, 'nombre' => $g->first()->pronom, 'umecod' => $g->first()->umecod ?: 'NIU',
                'antes' => $antes, 'ahora' => $ahora, 'variacion' => $antes > 0 ? round(($ahora - $antes) / $antes * 100, 1) : null,
                'cambios' => $g->map(fn ($c) => (object) ['fecha' => $c->created_at, 'antes' => (float) $c->costo_anterior, 'ahora' => (float) $c->costo_nuevo,
                    'origen' => $c->origen])->values(),
                'platos' => (int) ($usos[$id] ?? 0)];
        })->sortByDesc(fn ($c) => $c->variacion ?? 0)->values();
    }
}
