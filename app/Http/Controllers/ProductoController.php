<?php
namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Subcategoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $q = trim($request->get('q'));

        $productos = Producto::where('id_empresa_negocio', $sucursal)
            ->when($q, fn($query) => $query->where('pronom', 'like', "%{$q}%"))
            ->orderBy('pronom')
            ->paginate(15);

        return view('empresas.productos.index', compact('productos', 'q'));
    }

    public function create()
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        return view('empresas.productos.create', compact('categorias', 'subcategorias'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'pronom' => 'required|string|max:150',
            'propun' => 'required|numeric|min:0',
        ], [], ['pronom' => 'Nombre del producto', 'propun' => 'Precio de venta']);

        Producto::create([
            'procod'             => $request->procod ?: 'P'.time(),
            'pronom'             => $request->pronom,
            'umecod'             => $request->umecod ?: 'UNI',
            'costo'              => $request->costo ?: 0,
            'propun'             => $request->propun,
            'cat_id'             => $request->cat_id,
            'subcat_id'          => $request->subcat_id,
            'stock_min'          => $request->stock_min ?: 0,
            'proest'             => 'Activo',
            'IdEmpresa'          => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('productos.index')->with('success', 'Producto creado correctamente.');
    }

    public function edit(Producto $producto)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        return view('empresas.productos.edit', compact('producto', 'categorias', 'subcategorias'));
    }

    public function update(Request $request, Producto $producto)
    {
        $request->validate([
            'pronom' => 'required|string|max:150',
            'propun' => 'required|numeric|min:0',
        ], [], ['pronom' => 'Nombre del producto', 'propun' => 'Precio de venta']);

        $producto->update($request->only(['pronom', 'umecod', 'costo', 'propun', 'cat_id', 'subcat_id', 'stock_min', 'proest']));

        return redirect()->route('productos.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Producto $producto)
    {
        $producto->delete();
        return back()->with('success', 'Producto eliminado.');
    }
}