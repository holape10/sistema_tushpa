<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Campañas del comercio (Navidad, campaña escolar, Día de la Madre…): qué comprar para la próxima, según lo que se vendió
 * en las mismas fechas del año pasado. Si el negocio no tiene ventas del año pasado, se estima con lo que vende ahora
 * (últimos 30 días) por los días que dura la campaña. Sugiere comprar lo que falta para vender igual: lo vendido menos el
 * stock de hoy y lo que ya se vendió en la campaña de este año.
 */
class Campanas
{
    /** Desde cuántos días antes se avisa en el Inicio */
    public const AVISO_DIAS = 45;

    /** Días de ventas recientes para estimar cuando no hay datos del año pasado */
    private const DIAS_RITMO = 30;

    /** clave => [nombre, ícono] */
    public const LISTA = [
        'san_valentin' => ['San Valentín', '💘'],
        'escolar' => ['Campaña escolar', '🎒'],
        'madre' => ['Día de la Madre', '💐'],
        'padre' => ['Día del Padre', '👔'],
        'fiestas_patrias' => ['Fiestas Patrias', '🇵🇪'],
        'criolla' => ['Canción Criolla y Halloween', '🎃'],
        'navidad' => ['Navidad y Año Nuevo', '🎄'],
    ];

    /** Fechas de la campaña en un año: [inicio, fin] */
    public static function fechas(string $clave, int $anio): array
    {
        $domingo = fn (int $mes, int $n) => Carbon::create($anio, $mes, 1)->modify(['', 'first', 'second', 'third'][$n].' sunday of this month')->startOfDay();

        [$inicio, $fin] = match ($clave) {
            'san_valentin' => [Carbon::create($anio, 2, 1), Carbon::create($anio, 2, 14)],
            'escolar' => [Carbon::create($anio, 2, 15), Carbon::create($anio, 3, 20)],
            'madre' => [$domingo(5, 2)->subDays(14), $domingo(5, 2)],
            'padre' => [$domingo(6, 3)->subDays(14), $domingo(6, 3)],
            'fiestas_patrias' => [Carbon::create($anio, 7, 15), Carbon::create($anio, 7, 29)],
            'criolla' => [Carbon::create($anio, 10, 20), Carbon::create($anio, 10, 31)],
            'navidad' => [Carbon::create($anio, 12, 1), Carbon::create($anio, 12, 31)],
            default => throw new \InvalidArgumentException("No existe la campaña {$clave}."),
        };

        return [$inicio->startOfDay(), $fin->endOfDay()];
    }

    /** La campaña que está en curso o empieza dentro de AVISO_DIAS días (clave, inicio, fin) o null */
    public static function proxima(?Carbon $hoy = null): ?array
    {
        $hoy ??= now();
        $candidatas = [];
        foreach (array_keys(self::LISTA) as $clave) {
            foreach ([$hoy->year, $hoy->year + 1] as $anio) {
                [$inicio, $fin] = self::fechas($clave, $anio);
                if ($fin->gte($hoy) && $inicio->copy()->subDays(self::AVISO_DIAS)->lte($hoy)) {
                    $candidatas[] = ['clave' => $clave, 'inicio' => $inicio, 'fin' => $fin];
                }
            }
        }

        return collect($candidatas)->sortBy(fn ($c) => $c['inicio']->timestamp)->first();
    }

    /** La de este año si no terminó; si ya pasó, la del año que viene */
    public static function siguienteFecha(string $clave, ?Carbon $hoy = null): array
    {
        $hoy ??= now();
        [$inicio, $fin] = self::fechas($clave, $hoy->year);

        return $fin->lt($hoy) ? self::fechas($clave, $hoy->year + 1) : [$inicio, $fin];
    }

    /**
     * Qué comprar para la campaña (las fechas de este año o del siguiente).
     *
     * @return array{clave: string, inicio: Carbon, fin: Carbon, base: string, desde_pasado: Carbon, hasta_pasado: Carbon, vendido_pasado: float, inversion: float, productos: Collection<int, object>}
     */
    public static function plan(int $sucursal, string $clave, ?Carbon $hoy = null): array
    {
        $hoy ??= now();
        [$inicio, $fin] = self::siguienteFecha($clave, $hoy);
        [$desdePasado, $hastaPasado] = self::fechas($clave, $inicio->year - 1);
        $dias = $inicio->diffInDays($fin) + 1;

        $pasado = self::vendido($sucursal, $desdePasado, $hastaPasado);
        $base = $pasado->isNotEmpty() ? 'anio_pasado' : 'ritmo';
        if ($base === 'ritmo') {
            // Sin ventas del año pasado: lo que vende por día ahora, por los días de la campaña
            $pasado = self::vendido($sucursal, $hoy->copy()->subDays(self::DIAS_RITMO)->startOfDay(), $hoy->copy()->endOfDay())
                ->map(function ($p) use ($dias) {
                    $p->cantidad = round($p->cantidad / self::DIAS_RITMO * $dias, 2);
                    $p->total = round($p->total / self::DIAS_RITMO * $dias, 2);

                    return $p;
                });
        }
        // Lo que ya se vendió en la campaña de este año (si ya empezó)
        $yaVendido = $inicio->lte($hoy) ? self::vendido($sucursal, $inicio, $hoy->copy()->endOfDay())->pluck('cantidad', 'id') : collect();
        $almacen = Kardex::almacenPredeterminado($sucursal);
        $stock = $almacen ? DB::table('producto_stock')->where('id_almacen', $almacen->id_almacen)->whereIn('IdProducto', $pasado->keys())->pluck('stock', 'IdProducto') : collect();

        $productos = $pasado->map(function ($p) use ($yaVendido, $stock) {
            $p->ya_vendido = (float) ($yaVendido[$p->id] ?? 0);
            $p->stock = max((float) ($stock[$p->id] ?? 0), 0);
            $p->comprar = max(ceil($p->cantidad - $p->ya_vendido - $p->stock), 0);
            $p->inversion = round($p->comprar * $p->costo, 2);

            return $p;
        })->sortByDesc(fn ($p) => [$p->comprar > 0, $p->total])->values();

        return [
            'clave' => $clave, 'inicio' => $inicio, 'fin' => $fin, 'base' => $base, 'desde_pasado' => $desdePasado, 'hasta_pasado' => $hastaPasado,
            'vendido_pasado' => round($productos->sum('total'), 2), 'inversion' => round($productos->sum('inversion'), 2), 'productos' => $productos,
        ];
    }

    /** Total vendido en la sucursal entre dos fechas (para el aviso del Inicio) */
    public static function totalVendido(int $sucursal, Carbon $desde, Carbon $hasta): float
    {
        return round((float) self::vendido($sucursal, $desde, $hasta)->sum('total'), 2);
    }

    /**
     * Unidades (base) y soles vendidos por producto de reventa entre dos momentos, sin anuladas ni notas.
     *
     * @return Collection<int, object{id: int, nombre: string, unidad: string, costo: float, cantidad: float, total: float}>
     */
    private static function vendido(int $sucursal, Carbon $desde, Carbon $hasta): Collection
    {
        return DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->where('c.id_empresa_negocio', $sucursal)->whereNull('c.ccabaj')->whereNotIn('c.tdocod', ['07', '08'])
            ->whereBetween('c.fecha_hora', [$desde, $hasta])->where('p.promocion', 0)
            ->groupBy('d.IdProducto', 'p.pronom', 'p.umecod', 'p.costo')
            ->selectRaw('d.IdProducto as id, p.pronom as nombre, p.umecod as unidad, p.costo, SUM(d.cdecan * COALESCE(NULLIF(d.cpe_det_factor, 0), 1)) as cantidad, SUM(d.cdecan * d.cdepuni) as total')
            ->get()->map(fn ($p) => (object) ['id' => (int) $p->id, 'nombre' => $p->nombre, 'unidad' => $p->unidad ?: 'NIU', 'costo' => (float) $p->costo,
                'cantidad' => (float) $p->cantidad, 'total' => (float) $p->total])
            ->keyBy('id');
    }
}
