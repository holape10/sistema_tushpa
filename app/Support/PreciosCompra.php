<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precios cuando sube el costo: al registrar una compra más cara (con "actualizar costo"), los productos de reventa que
 * ahora ganan menos que antes, con el precio que mantiene la ganancia de antes (redondeado hacia arriba a 10 céntimos).
 * El dueño los sube con un toque. Usa el historial de costos (costo_historial) que ya llenan las compras.
 */
class PreciosCompra
{
    /** Ganancia que se asume si el producto no tenía una (vendía al costo o sin costo) */
    public const MARGEN_DEFECTO = 0.25;

    /** Días hacia atrás que se revisan cuando no se mira una compra en particular */
    public const DIAS = 15;

    /**
     * @return Collection<int, object{id: int, nombre: string, antes: float, ahora: float, precio: float, margen_antes: float, margen_ahora: float, sugerido: float}>
     */
    public static function sugerencias(int $sucursal, ?int $comCabId = null): Collection
    {
        return DB::table('costo_historial as h')->join('productos as p', 'p.IdProducto', '=', 'h.IdProducto')
            ->where('h.id_empresa_negocio', $sucursal)->where('p.promocion', 0)->where('p.proest', 'Activo')
            // Lo que el dueño ya revisó (le subió el precio) no se vuelve a sugerir
            ->when(Schema::hasColumn('costo_historial', 'revisado_at'), fn ($q) => $q->whereNull('h.revisado_at'))
            ->when($comCabId, fn ($q) => $q->where('h.com_cab_id', $comCabId), fn ($q) => $q->where('h.created_at', '>=', now()->subDays(self::DIAS)))
            ->orderBy('h.id')->get(['h.IdProducto', 'p.pronom', 'p.propun', 'p.costo', 'h.costo_anterior', 'h.costo_nuevo'])
            // Si cambió varias veces, desde el primer costo hasta el de hoy
            ->groupBy('IdProducto')->map(function ($g) {
                $p = $g->first();
                $antes = (float) $p->costo_anterior;
                $ahora = (float) $p->costo;
                $precio = (float) $p->propun;
                $margenAntes = $precio > 0 && $antes > 0 && $antes < $precio ? ($precio - $antes) / $precio : self::MARGEN_DEFECTO;

                return (object) [
                    'id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'antes' => $antes, 'ahora' => $ahora, 'precio' => $precio,
                    'margen_antes' => round($margenAntes * 100, 1), 'margen_ahora' => $precio > 0 ? round(($precio - $ahora) / $precio * 100, 1) : 0.0,
                    'sugerido' => ceil(round($ahora / (1 - min($margenAntes, 0.9)) * 10, 4)) / 10,
                ];
            })
            ->filter(fn ($s) => $s->ahora > $s->antes && $s->sugerido > $s->precio + 0.001)
            ->sortBy('margen_ahora')->values();
    }

    /**
     * Pone los precios nuevos (solo productos de la sucursal) y lo deja en la bitácora. Devuelve cuántos cambió.
     *
     * @param  array<int, float>  $precios  IdProducto => precio nuevo
     */
    public static function aplicar(User $user, array $precios): int
    {
        $productos = DB::table('productos')->where('id_empresa_negocio', $user->id_empresa_negocio)->whereIn('IdProducto', array_keys($precios))
            ->get(['IdProducto', 'pronom', 'propun'])->keyBy('IdProducto');
        $cambios = [];
        DB::transaction(function () use ($productos, $precios, &$cambios) {
            foreach ($productos as $id => $p) {
                $nuevo = round((float) $precios[$id], 2);
                if ($nuevo > 0 && abs($nuevo - (float) $p->propun) >= 0.005) {
                    DB::table('productos')->where('IdProducto', $id)->update(['propun' => $nuevo, 'updated_at' => now()]);
                    if (Schema::hasColumn('costo_historial', 'revisado_at')) {
                        DB::table('costo_historial')->where('IdProducto', $id)->whereNull('revisado_at')->update(['revisado_at' => now()]);
                    }
                    $cambios[] = $p->pronom.': S/ '.number_format((float) $p->propun, 2).' → S/ '.number_format($nuevo, 2);
                }
            }
        });
        if ($cambios) {
            Bitacora::registrar('producto.precio', 'Subió precios por el costo nuevo: '.mb_strimwidth(implode(' · ', $cambios), 0, 900, '…'));
        }

        return count($cambios);
    }
}
