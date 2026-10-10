<?php

namespace App\Support;

use App\Models\EmpresaNegocio;
use App\Models\Producto;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;

/**
 * Carta digital del restaurante: los clientes escanean el QR (general o de su mesa) y ven los platos
 * por categoría con su precio vigente, descripción e imagen. Es pública: no pide sesión.
 */
class Carta
{
    public const COLOR = '#4f46e5';

    /** Sucursal con la carta activa: la pedida o, sin número, la primera que la tenga activa */
    public static function sucursal(?int $id = null): ?EmpresaNegocio
    {
        return EmpresaNegocio::where('carta_activa', 1)->where('estado', 'Activo')
            ->when($id, fn ($q) => $q->where('id_empresa_negocio', $id))
            ->orderBy('id_empresa_negocio')->first();
    }

    /**
     * Categorías visibles con sus productos de venta (sin insumos), en el orden en que se crearon.
     *
     * @return array<int, array{id: int, nombre: string, productos: array<int, array{id: int, nombre: string, descripcion: ?string, precio: float, normal: float, img: ?string, presentaciones: array<int, array{nombre: string, precio: float}>}>}>
     */
    public static function categorias(int $sucursal): array
    {
        $filas = DB::table('productos as p')
            ->join('categorias as c', 'c.cat_id', '=', 'p.cat_id')
            ->where('p.id_empresa_negocio', $sucursal)->where('p.proest', 'Activo')->whereNotIn('p.promocion', Producto::NO_VENDIBLES)
            ->where('c.visible', 1)
            ->orderBy('c.cat_id')->orderBy('p.IdProducto')
            ->get(['p.IdProducto', 'p.pronom', 'p.descripcion', 'p.propun', 'p.imagenproducto', 'p.cat_id', 'c.cat_nom']);
        $precios = Precios::vigentes($filas);
        $presentaciones = DB::table('producto_presentacion')->whereIn('IdProducto', $filas->pluck('IdProducto'))
            ->where('estado', 1)->where('precio', '>', 0)->orderBy('precio')->get(['IdProducto', 'nombre', 'precio'])->groupBy('IdProducto');

        return $filas->filter(fn ($p) => $precios[$p->IdProducto] > 0)
            ->groupBy('cat_id')
            ->map(fn ($productos) => [
                'id' => (int) $productos->first()->cat_id,
                'nombre' => $productos->first()->cat_nom,
                'productos' => $productos->map(fn ($p) => [
                    'id' => (int) $p->IdProducto,
                    'nombre' => $p->pronom,
                    'descripcion' => $p->descripcion,
                    'precio' => $precios[$p->IdProducto],
                    'normal' => (float) $p->propun,
                    'img' => $p->imagenproducto && is_file(public_path($p->imagenproducto)) ? asset($p->imagenproducto) : null,
                    'presentaciones' => ($presentaciones[$p->IdProducto] ?? collect())
                        ->map(fn ($x) => ['nombre' => $x->nombre, 'precio' => (float) $x->precio])->values()->all(),
                ])->values()->all(),
            ])->values()->all();
    }

    /** Enlace público de la carta (con la mesa, si es el QR de una mesa) */
    public static function url(int $sucursal, ?int $mesa = null): string
    {
        return url('/carta/'.$sucursal).($mesa ? '?mesa='.$mesa : '');
    }

    /** Código QR en SVG (sin internet ni JavaScript) */
    public static function qr(string $texto, int $tamano = 260): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd));

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($texto));
    }

    /** Color de la carta: el elegido por la sucursal o el índigo de TUSHPA */
    public static function color(EmpresaNegocio $negocio): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $negocio->carta_color) ? $negocio->carta_color : self::COLOR;
    }
}
