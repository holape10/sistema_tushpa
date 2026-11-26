<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pedidos sugeridos por proveedor: lo que se está acabando (según lo que se vende por día y el stock mínimo) agrupado
 * por el proveedor que lo vende más barato, listo para mandarle el pedido por WhatsApp. Junta lo que ya calcula el
 * sistema: Requerimientos::sugerencias (qué falta) y ProveedoresPrecios (a quién le compro más barato).
 */
class PedidosProveedor
{
    /** Para cuántos días se puede pedir */
    public const DIAS = [3, 7, 15, 30];

    /**
     * @return Collection<int, object{id: ?int, proveedor: string, telefono: ?string, items: Collection<int, object>, total: float}>
     */
    public static function armar(int $sucursal, int $dias = 7): Collection
    {
        $almacen = Kardex::almacenPredeterminado($sucursal);
        if (! $almacen) {
            return collect();
        }
        // Lo que falta para tener el mínimo y para vender esos días (lo que ya hay se descuenta)
        $faltan = Requerimientos::sugerencias($sucursal, (int) $almacen->id_almacen)
            ->map(function ($p) use ($dias) {
                $necesita = max($p->minimo, $p->consumo_dia * $dias) - max($p->stock, 0);
                $p->pedir = $p->unidad === 'NIU' ? (float) ceil($necesita - 0.0001) : round(ceil($necesita * 100) / 100, 2);

                return $p;
            })
            ->filter(fn ($p) => $p->pedir > 0 && ($p->consumo_dia > 0 || $p->minimo > 0))->values();
        if ($faltan->isEmpty()) {
            return collect();
        }

        $comparados = ProveedoresPrecios::comparar($sucursal, $faltan->pluck('id')->all())->keyBy('id');
        $telefonos = DB::table('proveedor')->whereIn('prov_id', $comparados->pluck('mejor.id')->filter()->unique())->pluck('prov_num_con', 'prov_id');

        return $faltan->groupBy(fn ($p) => $comparados[$p->id]->mejor->id ?? ($comparados[$p->id]->mejor->proveedor ?? 'SIN'))
            ->map(function (Collection $items, $clave) use ($comparados, $telefonos) {
                $mejor = $comparados[$items->first()->id]->mejor ?? null;
                $items = $items->map(function ($p) use ($comparados) {
                    $costo = (float) ($comparados[$p->id]->mejor->costo ?? $p->costo);

                    return (object) ['id' => $p->id, 'nombre' => $p->nombre, 'unidad' => $p->unidad, 'stock' => $p->stock, 'por_dia' => $p->consumo_dia,
                        'pedir' => $p->pedir, 'costo' => $costo, 'subtotal' => round($p->pedir * $costo, 2)];
                })->sortBy('nombre')->values();

                return (object) [
                    'id' => $mejor->id ?? null,
                    'proveedor' => $mejor->proveedor ?? 'Sin proveedor registrado',
                    'telefono' => $mejor && $mejor->id ? self::celular((string) ($telefonos[$mejor->id] ?? '')) : null,
                    'items' => $items,
                    'total' => round($items->sum('subtotal'), 2),
                ];
            })
            // Los que tienen proveedor primero; los que nunca se compraron al final
            ->sortBy(fn ($g) => [$g->id ? 0 : 1, $g->proveedor])->values();
    }

    /** "51987654321" para wa.me (null si no parece un celular) */
    public static function celular(string $telefono): ?string
    {
        $d = preg_replace('/\D/', '', $telefono);

        return strlen($d) === 9 ? '51'.$d : (strlen($d) >= 11 ? $d : null);
    }

    /** El mensaje del pedido para el proveedor */
    public static function mensaje(object $grupo, string $negocio): string
    {
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
        $lineas = ['Hola 👋, de '.$negocio.' le hago el siguiente pedido:', ''];
        foreach ($grupo->items as $i) {
            $lineas[] = '• '.$num($i->pedir).' '.$i->nombre;
        }
        $lineas[] = '';
        $lineas[] = '¿Me confirma precio y cuándo lo puede traer? Gracias.';

        return implode("\n", $lineas);
    }
}
