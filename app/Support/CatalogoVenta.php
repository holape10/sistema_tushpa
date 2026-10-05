<?php
namespace App\Support;

use App\Models\{Almacen, Producto, UnidadMedida, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Productos tal como los ven las cajas (Punto Venta, PV Farmacia, PV Móvil y proformas):
 * precio vigente (con precio dinámico), presentaciones, stock del almacén predeterminado, lotes e imagen.
 */
class CatalogoVenta
{
    /**
     * Con $codigo busca el código exacto: código interno, código de barras del producto o de una presentación
     * (en ese caso la presentación llega preseleccionada). Con $q busca por nombre o código.
     */
    public static function buscar(User $user, string $codigo, string $q): array
    {
        $sucursal = $user->id_empresa_negocio;
        $almacenId = self::almacen($sucursal);

        $presentacion = null;
        if ($codigo !== '') {
            $presentacion = DB::table('producto_presentacion as pp')->join('productos as p', 'p.IdProducto', '=', 'pp.IdProducto')
                ->where('p.id_empresa_negocio', $sucursal)->where('pp.estado', 1)->where('pp.codigo_barra', $codigo)
                ->first(['pp.id_presentacion', 'pp.IdProducto']);
        }

        $productos = self::consulta($sucursal, $almacenId)
            ->when($codigo !== '', fn($w) => $w->where(fn($x) => $x->where('productos.procod', $codigo)
                ->orWhere('productos.codigo_barra', $codigo)
                ->when($presentacion, fn($y) => $y->orWhere('productos.IdProducto', $presentacion->IdProducto))))
            ->when($codigo === '', function ($w) use ($q) {
                // Cada palabra debe aparecer en el nombre (sirve para la voz: "inca kola 500")
                foreach (preg_split('/\s+/', $q) as $palabra) {
                    $w->where(fn($x) => $x->where('productos.pronom', 'like', '%' . $palabra . '%')
                        ->orWhere('productos.procod', 'like', $palabra . '%')
                        ->orWhere('productos.codigo_barra', $palabra));
                }
                $w->orderByRaw('productos.procod = ? DESC, productos.pronom LIKE ? DESC', [$q, $q . '%']);
            })
            ->orderBy('productos.pronom')
            ->limit($codigo !== '' ? 1 : 20)
            ->get();

        return self::formatear($productos, $almacenId, $presentacion ? (int) $presentacion->id_presentacion : null, $presentacion ? (int) $presentacion->IdProducto : null);
    }

    /** Los mismos datos para una lista de productos (al abrir una proforma en la caja) */
    public static function porIds(User $user, array $ids): Collection
    {
        $almacenId = self::almacen($user->id_empresa_negocio);
        $productos = self::consulta($user->id_empresa_negocio, $almacenId)->whereIn('productos.IdProducto', $ids ?: [0])->get();
        return collect(self::formatear($productos, $almacenId))->keyBy('id');
    }

    private static function almacen($sucursal): ?int
    {
        return Almacen::where('id_empresa_negocio', $sucursal)->where('predeterminado', 1)->value('id_almacen');
    }

    private static function consulta($sucursal, ?int $almacenId)
    {
        return Producto::leftJoin('producto_stock', function ($join) use ($almacenId) {
                $join->on('productos.IdProducto', '=', 'producto_stock.IdProducto')
                     ->where('producto_stock.id_almacen', $almacenId);
            })
            ->where('productos.id_empresa_negocio', $sucursal)
            ->where('productos.proest', 'Activo')
            ->where('productos.promocion', '!=', 4) // los insumos no se venden
            ->select(['productos.IdProducto', 'productos.procod', 'productos.codigo_barra', 'productos.pronom', 'productos.propun',
                      'productos.umecod', 'productos.promocion', 'productos.control_lote', 'productos.imagenproducto', 'producto_stock.stock']);
    }

    private static function formatear(Collection $productos, ?int $almacenId, ?int $presentacionElegida = null, ?int $deProducto = null): array
    {
        $precios = Precios::vigentes($productos);
        $presentaciones = Precios::presentaciones($productos->pluck('IdProducto'));
        $unidades = UnidadMedida::pluck('umenom', 'umecod');

        // Lotes con stock (farmacia), en el orden en que saldrán: primero el que vence antes
        $hoy = now()->toDateString();
        $lotes = DB::table('producto_lote')->whereIn('IdProducto', $productos->pluck('IdProducto'))
            ->where('id_almacen', $almacenId)->where('stock', '>', 0)->get()->groupBy('IdProducto');

        return $productos->map(function ($p) use ($precios, $presentaciones, $unidades, $lotes, $hoy, $presentacionElegida, $deProducto) {
            $suyos = Lotes::ordenar($lotes[$p->IdProducto] ?? collect());
            $stock = (int) $p->promocion === 0 ? (float) ($p->stock ?? 0) : null;
            $precio = $precios[$p->IdProducto] ?? (float) $p->propun;
            return [
                'id' => $p->IdProducto,
                'codigo' => $p->procod,
                'codigo_barra' => $p->codigo_barra,
                'nombre' => $p->pronom,
                'precio' => $precio,
                'precio_normal' => (float) $p->propun,
                'dinamico' => abs($precio - (float) $p->propun) > 0.001,
                'umecod' => $p->umecod,
                'unidad' => $unidades[$p->umecod] ?? $p->umecod,
                'imagen' => $p->imagenproducto ? asset($p->imagenproducto) : null,
                'presentaciones' => $presentaciones[$p->IdProducto] ?? [],
                // Código de barras de una presentación: la caja la agrega ya elegida
                'presentacion' => $deProducto === (int) $p->IdProducto ? $presentacionElegida : null,
                // Preparados y combos no llevan stock propio
                'stock' => $stock,
                'control_lote' => (bool) $p->control_lote,
                'lotes' => $suyos->map(fn($l) => [
                    'lote' => $l->lote, 'vence' => $l->vencimiento, 'stock' => (float) $l->stock,
                    'dias' => $l->vencimiento ? (int) now()->startOfDay()->diffInDays($l->vencimiento, false) : null,
                    'vencido' => $l->vencimiento && $l->vencimiento < $hoy,
                ])->values(),
                'sin_lote' => $stock !== null ? max(0, round($stock - $suyos->sum('stock'), 3)) : 0,
            ];
        })->values()->all();
    }
}
