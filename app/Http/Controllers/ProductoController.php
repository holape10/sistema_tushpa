<?php
namespace App\Http\Controllers;

use App\Models\{Producto, Categoria, Subcategoria, UnidadMedida, Combo};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $q = trim($request->get('q'));
        $tipo = $request->get('tipo', '');

        $productos = Producto::where('id_empresa_negocio', $sucursal)
            ->when($q, fn($query) => $query->where('pronom', 'like', "%{$q}%"))
            ->when($tipo !== '', fn($query) => $query->where('promocion', $tipo))
            ->orderBy('pronom')
            ->paginate(15)
            ->withQueryString();

        return view('empresas.productos.index', compact('productos', 'q', 'tipo'));
    }

    public function create()
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        $unidades = UnidadMedida::orderBy('umenom')->get();

        // para armar combos: productos y preparados ya existentes
        $itemsParaCombo = Producto::where('id_empresa_negocio', $sucursal)
            ->whereIn('promocion', [0, 2]) // solo Producto y Preparado entran a un combo
            ->orderBy('pronom')->get();

        return view('empresas.productos.create', compact('categorias', 'subcategorias', 'unidades', 'itemsParaCombo'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pronom'    => 'required|string|max:150',
            'propun'    => 'required|numeric|min:0.01',
            'promocion' => 'required|in:0,2,4,6',
        ], [], [
            'pronom' => 'Nombre del producto',
            'propun' => 'Precio de venta',
            'promocion' => 'Tipo',
        ]);

        if ($request->promocion == 6 && empty($request->combo_items)) {
            return back()->withInput()->withErrors(['combo_items' => 'Un combo debe tener al menos un producto o preparado dentro.']);
        }

        DB::transaction(function () use ($request) {
            $producto = Producto::create([
                'procod'             => $request->procod ?: 'P'.time(),
                'pronom'             => $request->pronom,
                'umecod'             => $request->umecod ?: 'NIU',
                'costo'              => $request->costo ?: 0,
                'propun'             => $request->propun,
                'promocion'          => $request->promocion,
                'cat_id'             => $request->cat_id,
                'subcat_id'          => $request->subcat_id,
                'stock_min'          => $request->stock_min ?: 0,
                'proest'             => 'Activo',
                'IdEmpresa'          => Auth::user()->IdEmpresa,
                'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            ]);

            // si es COMBO, guardamos qué productos/preparados lleva dentro
            if ($request->promocion == 6 && !empty($request->combo_items)) {
                foreach ($request->combo_items as $itemId => $cantidad) {
                    if (!empty($cantidad) && $cantidad > 0) {
                        Combo::create([
                            'IdProducto_rel'  => $producto->IdProducto,
                            'IdProducto_comb' => $itemId,
                            'prod_comb_cant'  => $cantidad,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('productos.index')->with('success', 'Registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        $unidades = UnidadMedida::orderBy('umenom')->get();
        $itemsParaCombo = Producto::where('id_empresa_negocio', $sucursal)
            ->whereIn('promocion', [0, 2])
            ->where('IdProducto', '!=', $producto->IdProducto)
            ->orderBy('pronom')->get();
        $comboActual = $producto->itemsCombo()->pluck('prod_comb_cant', 'IdProducto_comb');

        return view('empresas.productos.edit', compact('producto', 'categorias', 'subcategorias', 'unidades', 'itemsParaCombo', 'comboActual'));
    }

    public function update(Request $request, Producto $producto)
    {
        $request->validate([
            'pronom' => 'required|string|max:150',
            'propun' => 'required|numeric|min:0.01',
        ], [], ['pronom' => 'Nombre del producto', 'propun' => 'Precio de venta']);

        DB::transaction(function () use ($request, $producto) {
            $producto->update($request->only(['pronom', 'umecod', 'costo', 'propun', 'cat_id', 'subcat_id', 'stock_min', 'proest']));

            if ($producto->promocion == 6) {
                $producto->itemsCombo()->delete();
                foreach ($request->combo_items ?? [] as $itemId => $cantidad) {
                    if (!empty($cantidad) && $cantidad > 0) {
                        Combo::create([
                            'IdProducto_rel'  => $producto->IdProducto,
                            'IdProducto_comb' => $itemId,
                            'prod_comb_cant'  => $cantidad,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('productos.index')->with('success', 'Actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        $producto->itemsCombo()->delete();
        $producto->delete();
        return back()->with('success', 'Eliminado.');
    }
}