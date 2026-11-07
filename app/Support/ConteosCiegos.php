<?php

namespace App\Support;

use App\Http\Controllers\InventarioController;
use App\Models\Almacen;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventario ciego (como el cierre ciego de caja): el almacenero cuenta sin ver cuánto dice el sistema; el sistema
 * guarda lo que había en ese momento. El administrador ve las diferencias (y cuánto valen) y decide si ajusta el stock
 * o descarta el conteo. Al ajustar se aplica la diferencia sobre el stock de ahora (lo vendido mientras tanto no se pierde).
 */
class ConteosCiegos
{
    public const ESTADOS = [
        'PENDIENTE' => ['Por revisar', 'amber'],
        'APLICADO' => ['Ajustado', 'emerald'],
        'DESCARTADO' => ['Descartado', 'slate'],
    ];

    /** ¿Ve las diferencias y decide? Solo el administrador (quien cuenta nunca ve el stock del sistema) */
    public static function revisa(User $user): bool
    {
        return $user->esAdmin();
    }

    /**
     * Guarda lo contado (solo los productos que se contaron). Devuelve el id.
     *
     * @param  array<int|string, mixed>  $contados  cantidad por IdProducto
     */
    public static function guardar(User $user, int $almacen, array $contados, ?string $observaciones): int
    {
        $sucursal = (int) $user->id_empresa_negocio;
        if (! DB::table('almacenes')->where('id_almacen', $almacen)->where('id_empresa_negocio', $sucursal)->exists()) {
            throw new \RuntimeException('Almacén no válido.');
        }
        $contados = collect($contados)->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v) && (float) $v >= 0)->map(fn ($v) => round((float) $v, 3));
        if ($contados->isEmpty()) {
            throw new \RuntimeException('Pon lo que contaste de al menos un producto.');
        }
        $productos = Producto::where('id_empresa_negocio', $sucursal)->whereIn('promocion', Compras::TIPOS_CON_STOCK)
            ->whereIn('IdProducto', $contados->keys())->get(['IdProducto', 'costo'])->keyBy('IdProducto');
        if ($productos->count() !== $contados->count()) {
            throw new \RuntimeException('Hay productos que no son de esta sucursal o no manejan stock.');
        }

        return DB::transaction(function () use ($user, $almacen, $contados, $productos, $sucursal, $observaciones) {
            $sistema = DB::table('producto_stock')->where('id_almacen', $almacen)->whereIn('IdProducto', $contados->keys())->pluck('stock', 'IdProducto');
            $id = DB::table('conteos_ciegos')->insertGetId(['id_empresa_negocio' => $sucursal, 'id_almacen' => $almacen, 'IdUsuario' => $user->IdUsuario,
                'estado' => 'PENDIENTE', 'observaciones' => $observaciones ?: null, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($contados as $producto => $cantidad) {
                DB::table('conteos_ciegos_detalle')->insert(['conteo_id' => $id, 'IdProducto' => $producto, 'contado' => $cantidad,
                    'sistema' => (float) ($sistema[$producto] ?? 0), 'costo' => (float) $productos[$producto]->costo]);
            }

            return $id;
        });
    }

    public static function deSucursal(User $user, int $id, bool $bloquear = false): object
    {
        $c = DB::table('conteos_ciegos')->where('id', $id)->where('id_empresa_negocio', $user->id_empresa_negocio)
            ->when($bloquear, fn ($q) => $q->lockForUpdate())->first();
        if (! $c) {
            throw new \RuntimeException('El conteo no existe.');
        }

        return $c;
    }

    /** @return Collection<int, object> las líneas con su diferencia (contado − sistema) y cuánto vale */
    public static function detalle(int $id): Collection
    {
        return DB::table('conteos_ciegos_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->where('d.conteo_id', $id)->orderBy('p.pronom')->get(['d.*', 'p.pronom', 'p.umecod'])
            ->map(function ($l) {
                $l->diferencia = round((float) $l->contado - (float) $l->sistema, 3);
                $l->valor = round($l->diferencia * (float) $l->costo, 2);

                return $l;
            })
            ->sortBy(fn ($l) => $l->valor)->values();
    }

    /** El administrador ajusta el stock con la diferencia del conteo (sobre el stock de ahora) y queda como inventario */
    public static function aplicar(User $user, int $id): int
    {
        return DB::transaction(function () use ($user, $id) {
            $c = self::deSucursal($user, $id, true);
            if ($c->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Este conteo ya se revisó.');
            }
            $almacen = Almacen::findOrFail($c->id_almacen);
            $lineas = self::detalle($id)->filter(fn ($l) => abs($l->diferencia) > 0.0001);
            $invId = null;
            if ($lineas->isNotEmpty()) {
                $ahora = DB::table('producto_stock')->where('id_almacen', $almacen->id_almacen)->whereIn('IdProducto', $lineas->pluck('IdProducto'))->pluck('stock', 'IdProducto');
                $items = $lineas->map(fn ($l) => ['IdProducto' => (int) $l->IdProducto, 'costo' => null,
                    'cantidad' => max(round((float) ($ahora[$l->IdProducto] ?? 0) + $l->diferencia, 3), 0)])->values()->all();
                $productos = Producto::whereIn('IdProducto', $lineas->pluck('IdProducto'))->get()->keyBy('IdProducto');
                $invId = InventarioController::procesar($almacen, now()->toDateString(), 'INVENTARIO CIEGO N° '.$id, 'CIEGO', $items, $productos);
            }
            DB::table('conteos_ciegos')->where('id', $id)->update(['estado' => 'APLICADO', 'inv_cab_id' => $invId,
                'revisado_por' => $user->IdUsuario, 'revisado_at' => now(), 'updated_at' => now()]);

            return (int) $invId;
        });
    }

    public static function descartar(User $user, int $id): void
    {
        DB::transaction(function () use ($user, $id) {
            $c = self::deSucursal($user, $id, true);
            if ($c->estado !== 'PENDIENTE') {
                throw new \RuntimeException('Este conteo ya se revisó.');
            }
            DB::table('conteos_ciegos')->where('id', $id)->update(['estado' => 'DESCARTADO', 'revisado_por' => $user->IdUsuario,
                'revisado_at' => now(), 'updated_at' => now()]);
        });
    }
}
