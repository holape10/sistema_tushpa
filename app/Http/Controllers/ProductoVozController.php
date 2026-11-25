<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Support\Asistente\ProductoVoz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Productos › Crear con voz (IA): interpreta lo dictado y, cuando la persona acepta lo que ve en pantalla, crea cada
 * producto con el mismo guardado del formulario de Nuevo Producto (validaciones, stock inicial al kardex y presentaciones).
 * El permiso lo da la opción Productos del menú (rutas productos.*).
 */
class ProductoVozController extends Controller
{
    public function interpretar(Request $request): JsonResponse
    {
        $texto = (string) $request->validate(['texto' => 'required|string|min:3|max:1500'])['texto'];
        try {
            return response()->json(['ok' => true] + ProductoVoz::interpretar(Auth::user(), $texto));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    /** Crear con foto: la IA lee el paquete; la foto queda para ser la imagen del producto */
    public function foto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => 'required|file|max:12288|extensions:'.ProductoVoz::EXTENSIONES,
            'texto' => 'nullable|string|max:500',
        ], ['foto.extensions' => 'Toma una foto (JPG o PNG).', 'foto.max' => 'La foto pesa más de 12 MB.']);
        set_time_limit(90);
        try {
            return response()->json(['ok' => true] + ProductoVoz::leerFoto(Auth::user(), $request->file('foto'), (string) $request->texto));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    public function crear(Request $request, ProductoController $productos): JsonResponse
    {
        $d = $request->validate([
            'productos' => 'required|array|min:1|max:20',
            'productos.*.nombre' => 'required|string|max:150', 'productos.*.precio' => 'required|numeric|min:0.01|max:999999',
            'productos.*.costo' => 'nullable|numeric|min:0|max:999999', 'productos.*.stock' => 'nullable|numeric|min:0|max:99999999',
            'productos.*.unidad' => 'nullable|exists:unidad_medida,umecod', 'productos.*.categoria' => 'nullable|string|max:50',
            'productos.*.presentaciones' => 'nullable|array|max:20',
            'productos.*.codigo_barra' => 'nullable|string|max:50', 'productos.*.foto' => 'nullable|uuid',
        ], [], ['productos.*.precio' => 'precio', 'productos.*.nombre' => 'nombre']);
        $user = Auth::user();

        $resultados = [];
        foreach ($d['productos'] as $p) {
            $nombre = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $p['nombre'])));
            if (Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->where('pronom', $nombre)->exists()) {
                $resultados[] = ['nombre' => $nombre, 'ok' => false, 'mensaje' => 'Ya existe un producto con ese nombre.'];

                continue;
            }
            $categoria = trim((string) ($p['categoria'] ?? ''));
            $catId = $categoria !== '' ? Categoria::firstOrCreate(
                ['cat_nom' => mb_strtoupper($categoria), 'id_empresa_negocio' => $user->id_empresa_negocio],
                ['IdEmpresa' => $user->IdEmpresa, 'visible' => 1])->cat_id : null;

            // La foto leída por la IA pasa a ser la imagen del producto
            $foto = ProductoVoz::rutaFoto($p['foto'] ?? null);
            // El mismo guardado del formulario de Nuevo Producto
            $sub = Request::create('', 'POST', [
                'promocion' => 0, 'pronom' => $nombre, 'propun' => $p['precio'], 'costo' => $p['costo'] ?? null,
                'umecod' => $p['unidad'] ?? 'NIU', 'cat_id' => $catId, 'stock_inicial' => $p['stock'] ?? null, 'codigo_barra' => $p['codigo_barra'] ?? null,
                'presentaciones' => array_values(array_map(fn ($x) => ['umecod' => $x['unidad'] ?? 'NIU', 'nombre' => $x['nombre'] ?? null,
                    'factor' => $x['factor'] ?? null, 'precio' => $x['precio'] ?? null], $p['presentaciones'] ?? [])),
            ], [], $foto ? ['imagen' => new UploadedFile($foto, 'foto.webp', 'image/webp', null, true)] : []);
            $sub->setUserResolver(fn () => $user);
            $sub->setLaravelSession($request->session());
            try {
                $productos->store($sub);
                if ($foto) {
                    @unlink($foto);
                }
                $resultados[] = ['nombre' => $nombre, 'ok' => true, 'mensaje' => 'Creado'];
            } catch (ValidationException $e) {
                $resultados[] = ['nombre' => $nombre, 'ok' => false, 'mensaje' => collect($e->errors())->flatten()->first()];
            }
        }
        // El aviso de "Registrado" del formulario no hace falta aquí
        $request->session()->forget('success');

        return response()->json(['ok' => collect($resultados)->contains('ok', true), 'resultados' => $resultados]);
    }
}
