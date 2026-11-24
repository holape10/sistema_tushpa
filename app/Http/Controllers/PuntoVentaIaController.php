<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Support\Asistente\VentaVoz;
use App\Support\Bitacora;
use App\Support\CatalogoVenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Punto Venta IA: interpreta lo que dice el cajero (VentaVoz) y crea al vuelo el producto que no existe (el cajero
 * solo acepta el nombre y el precio). La venta se registra con el mismo Punto Venta de siempre.
 */
class PuntoVentaIaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja pueden vender.');
    }

    public function interpretar(Request $request): JsonResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'texto' => 'required|string|max:500',
            'detalle' => 'nullable|array|max:200', 'detalle.*.nombre' => 'required|string|max:150',
            'detalle.*.cantidad' => 'required|numeric', 'detalle.*.precio' => 'required|numeric',
            'total' => 'nullable|numeric', 'medios' => 'nullable|array|max:20', 'medios.*' => 'string|max:60',
        ]);
        try {
            return response()->json(['ok' => true] + VentaVoz::interpretar(Auth::user(), $d['texto'], [
                'detalle' => $d['detalle'] ?? [], 'total' => (float) ($d['total'] ?? 0), 'medios' => $d['medios'] ?? [],
            ]));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Busca un producto dicho por voz sin pasar por la IA ("2 jabones": el número lo separa la pantalla) */
    public function buscar(Request $request): JsonResponse
    {
        $this->autorizar();

        return response()->json(VentaVoz::buscarProducto(Auth::user(), (string) $request->validate(['producto' => 'required|string|max:150'])['producto']));
    }

    /** El producto que no existía: se crea con el nombre y precio que aceptó el cajero (Producto, unidad NIU) y vuelve listo para vender */
    public function crearProducto(Request $request): JsonResponse
    {
        $this->autorizar();
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->tieneModulo('/productos'), 403, 'Pide al administrador que cree el producto (no tienes acceso a Productos).');
        $d = $request->validate(['nombre' => 'required|string|min:2|max:150', 'precio' => 'required|numeric|min:0.01|max:999999'],
            [], ['nombre' => 'nombre del producto', 'precio' => 'precio']);
        $nombre = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $d['nombre'])));

        // Si ya existe con ese nombre (lo crearon recién), se usa ese
        $producto = Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->where('pronom', $nombre)->first()
            ?? Producto::create([
                'pronom' => $nombre, 'promocion' => 0, 'umecod' => 'NIU', 'costo' => 0, 'propun' => round((float) $d['precio'], 2),
                'stock_min' => 0, 'control_lote' => false, 'proest' => 'Activo',
                'procod' => mb_substr('P'.time().random_int(10, 99), 0, 20), 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
            ]);
        Bitacora::registrar('producto.crear', "Creó el producto {$nombre} a S/ ".number_format((float) $producto->propun, 2).' desde el Punto Venta IA', null,
            ['referencia' => ['producto', $producto->IdProducto]]);

        return response()->json(['ok' => true, 'producto' => CatalogoVenta::porIds($user, [$producto->IdProducto])->first()]);
    }
}
