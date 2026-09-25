<?php
namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\TipoProducto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoriaController extends Controller
{
    public function index()
    {
        $categorias = Categoria::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->orderBy('cat_nom')->paginate(15);
        return view('empresas.categorias.index', compact('categorias'));
    }

    public function create()
    {
        $tipos = TipoProducto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.categorias.create', compact('tipos'));
    }

    public function store(Request $request)
    {
        $request->validate(['cat_nom' => 'required|string|max:50'], [], ['cat_nom' => 'Nombre']);

        Categoria::create([
            'cat_nom'            => $request->cat_nom,
            'color'              => $request->color ?: '#3f4aee',
            'tip_pro_id'         => $request->tip_pro_id,
            'IdEmpresa'          => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            'visible'            => 1,
        ]);

        return redirect()->route('categorias.index')->with('success', 'Categoría creada.');
    }

    public function edit(Categoria $categoria)
    {
        $tipos = TipoProducto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.categorias.edit', compact('categoria', 'tipos'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $request->validate(['cat_nom' => 'required|string|max:50'], [], ['cat_nom' => 'Nombre']);
        $categoria->update($request->only(['cat_nom', 'color', 'tip_pro_id']));
        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria)
    {
        $categoria->delete();
        return back()->with('success', 'Categoría eliminada.');
    }
}