<?php
namespace App\Support;

use App\Models\{Producto, ProductoPresentacion};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Precio de venta vigente: el precio dinámico activo en este momento (por día y hora) o, si no hay, el precio normal.
 * Lo usan todos los puntos de venta y las comandas, para que el mismo producto cueste lo mismo en cualquier caja.
 */
class Precios
{
    public const DIAS = [0 => 'Todos los días', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * @param iterable $productos objetos con IdProducto y propun
     * @return array [IdProducto => precio vigente]
     */
    public static function vigentes(iterable $productos, ?Carbon $ahora = null): array
    {
        $ahora ??= now();
        $precios = [];
        foreach ($productos as $p) {
            $precios[$p->IdProducto] = (float) $p->propun;
        }
        if (!$precios) {
            return [];
        }

        $reglas = DB::table('producto_precio_dinamico')
            ->whereIn('IdProducto', array_keys($precios))->where('activo', 1)
            ->get()->groupBy('IdProducto');

        foreach ($reglas as $id => $suyas) {
            if ($regla = self::reglaActiva($suyas, $ahora)) {
                $precios[$id] = (float) $regla->precio;
            }
        }
        return $precios;
    }

    public static function de(Producto $producto, ?Carbon $ahora = null): float
    {
        return self::vigentes([$producto], $ahora)[$producto->IdProducto];
    }

    /**
     * Regla que manda ahora. Si varias coinciden gana la más específica:
     * primero la de un día concreto sobre "todos los días", luego el rango más corto y al final la última creada.
     */
    public static function reglaActiva(Collection $reglas, Carbon $ahora): ?object
    {
        return $reglas->filter(fn($r) => self::aplica($r, $ahora))
            ->sortBy([
                fn($a, $b) => ((int) $a->dia === 0) <=> ((int) $b->dia === 0),
                fn($a, $b) => self::duracion($a) <=> self::duracion($b),
                fn($a, $b) => $b->id_precio_dinamico <=> $a->id_precio_dinamico,
            ])->first();
    }

    /** ¿La regla rige en este momento? Un rango con fin <= inicio sigue hasta el día siguiente (Jueves 18:00 a 16:00 = hasta el viernes 16:00) */
    public static function aplica(object $r, Carbon $ahora): bool
    {
        $hoy = $ahora->dayOfWeekIso;
        $ayer = $hoy === 1 ? 7 : $hoy - 1;
        $hora = $ahora->format('H:i:s');
        $ini = substr($r->hora_inicio, 0, 8);
        $fin = substr($r->hora_fin, 0, 8);
        $dia = (int) $r->dia;
        $esDia = fn(int $d) => $dia === 0 || $dia === $d;

        if ($ini < $fin) {
            return $esDia($hoy) && $hora >= $ini && $hora < $fin;
        }
        return ($esDia($hoy) && $hora >= $ini) || ($esDia($ayer) && $hora < $fin);
    }

    /** Minutos que dura la regla (inicio = fin cuenta como 24 h) */
    private static function duracion(object $r): int
    {
        [$h1, $m1] = array_map('intval', explode(':', $r->hora_inicio));
        [$h2, $m2] = array_map('intval', explode(':', $r->hora_fin));
        $min = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
        return $min > 0 ? $min : $min + 1440;
    }

    /**
     * Presentaciones activas de varios productos para mostrarlas en las cajas.
     * @return Collection [IdProducto => [['id', 'nombre', 'umecod', 'factor', 'precio', 'codigo_barra'], ...]]
     */
    public static function presentaciones(iterable $ids): Collection
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        return ProductoPresentacion::whereIn('IdProducto', $ids)->where('estado', 1)
            ->orderBy('factor')->get()
            ->groupBy('IdProducto')
            ->map(fn($g) => $g->map(fn($x) => [
                'id' => $x->id_presentacion, 'nombre' => $x->nombre, 'umecod' => $x->umecod,
                'factor' => (float) $x->factor, 'precio' => (float) $x->precio, 'codigo_barra' => $x->codigo_barra,
            ])->values());
    }
}
