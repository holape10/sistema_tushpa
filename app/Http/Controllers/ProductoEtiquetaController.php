<?php

namespace App\Http\Controllers;

use App\Support\Impresion\Etiquetas;
use App\Support\Impresion\Impresion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Imprimir etiquetas con el código de barras de los productos (Productos › Etiquetas): se eligen los productos y cuántas
 * de cada uno, y salen en la ticketera o en una impresora de etiquetas (PDF del tamaño de la etiqueta), o se ven en PDF.
 * El permiso lo da la opción Productos del menú (PermisoDelMenu, rutas productos.*).
 */
class ProductoEtiquetaController extends Controller
{
    /** Máximo de etiquetas por vez */
    private const MAXIMO = 500;

    private function sucursal(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    public function index()
    {
        $impresoras = DB::table('configuracion_impresoras')->where('id_empresa_negocio', $this->sucursal())->where('activo', 1)
            ->orderByDesc('predeterminado')->orderBy('descripcion')->get(['Id', 'descripcion', 'tip_conex_imp', 'columnas']);

        return view('empresas.productos.etiquetas', ['impresoras' => $impresoras, 'tamanos' => Etiquetas::TAMANOS]);
    }

    /** Productos para agregar a la lista (por nombre, código interno o código de barras) */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $productos = DB::table('productos')->where('id_empresa_negocio', $this->sucursal())->where('proest', 'Activo')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('pronom', 'like', "%{$q}%")->orWhere('procod', $q)->orWhere('codigo_barra', $q)))
            ->orderByRaw('codigo_barra IS NULL')->orderBy('pronom')->limit(30)
            ->get(['IdProducto', 'pronom', 'procod', 'codigo_barra', 'propun']);

        return response()->json($productos->map(fn ($p) => ['id' => (int) $p->IdProducto, 'nombre' => $p->pronom, 'codigo_barra' => $p->codigo_barra,
            'procod' => $p->procod, 'precio' => (float) $p->propun])->values());
    }

    public function imprimir(Request $request)
    {
        $d = $request->validate([
            'items' => 'required|array|min:1|max:200', 'items.*.id' => 'required|integer', 'items.*.cantidad' => 'required|integer|min:1|max:'.self::MAXIMO,
            'destino' => 'required|string|max:20', 'tamano' => 'nullable|string|max:10',
            'nombre' => 'nullable|boolean', 'precio' => 'nullable|boolean', 'usar_interno' => 'nullable|boolean',
        ]);
        $cantidades = collect($d['items'])->mapWithKeys(fn ($i) => [(int) $i['id'] => (int) $i['cantidad']]);
        $productos = DB::table('productos')->where('id_empresa_negocio', $this->sucursal())->whereIn('IdProducto', $cantidades->keys())
            ->get(['IdProducto', 'pronom', 'procod', 'codigo_barra', 'propun']);
        // El código de barras; si no tiene, el código interno (si se eligió)
        $etiquetas = Etiquetas::lista($productos->map(fn ($p) => [
            'nombre' => (string) $p->pronom, 'precio' => (float) $p->propun, 'cantidad' => $cantidades[(int) $p->IdProducto] ?? 0,
            'codigo' => (string) ($p->codigo_barra ?: (! empty($d['usar_interno']) ? $p->procod : '')),
        ])->all());
        if (! $etiquetas) {
            return response()->json(['ok' => false, 'mensaje' => 'Ninguno de esos productos tiene código de barras. Marca «usar el código interno si no tiene código de barras».'], 422);
        }
        if (count($etiquetas) > self::MAXIMO) {
            return response()->json(['ok' => false, 'mensaje' => 'Son '.count($etiquetas).' etiquetas: imprime hasta '.self::MAXIMO.' por vez.'], 422);
        }
        $conNombre = (bool) ($d['nombre'] ?? true);
        $conPrecio = (bool) ($d['precio'] ?? true);
        $tamano = (string) ($d['tamano'] ?? '50x25');

        // Ver en PDF (para imprimir desde el navegador)
        if ($d['destino'] === 'pdf') {
            return response(Etiquetas::pdf($etiquetas, $tamano, $conNombre, $conPrecio), 200,
                ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="etiquetas.pdf"']);
        }

        $imp = Impresion::impresora((int) $d['destino'], $this->sucursal());
        abort_unless($imp, 404, 'Esa impresora no existe o está desactivada.');
        if ($imp->tip_conex_imp === 'WINDOWS') {
            // Impresora de etiquetas instalada en Windows: el PDF del tamaño de la etiqueta
            Impresion::encolar($imp, Etiquetas::pdf($etiquetas, $tamano, $conNombre, $conPrecio), 'ETIQUETAS', count($etiquetas).' etiquetas');
        } else {
            Impresion::encolar($imp, Etiquetas::ticketera($imp, $etiquetas, $conNombre, $conPrecio)->bytes(), 'ETIQUETAS', count($etiquetas).' etiquetas');
        }

        return response()->json(['ok' => true, 'mensaje' => count($etiquetas).' etiqueta(s) enviadas a la impresora '.$imp->descripcion.'.']);
    }
}
