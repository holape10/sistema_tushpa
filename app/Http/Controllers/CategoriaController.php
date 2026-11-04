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

    /** La línea elegida; sin elegir, la primera de la sucursal (la columna no acepta vacío en las bases antiguas) */
    private function linea(Request $request): int
    {
        return $request->filled('tip_pro_id')
            ? (int) $request->tip_pro_id
            : (int) (TipoProducto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderBy('tip_pro_id')->value('tip_pro_id') ?? 0);
    }

    public function store(Request $request)
    {
        $request->validate(['cat_nom' => 'required|string|max:50', 'tip_pro_id' => 'nullable|integer'], [], ['cat_nom' => 'Nombre']);

        Categoria::create([
            'cat_nom' => $request->cat_nom,
            'color' => $request->color ?: '#3f4aee',
            'tip_pro_id' => $this->linea($request),
            'IdEmpresa' => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            'visible' => 1,
        ]);

        return redirect()->route('categorias.index')->with('success', 'Categoría creada.');
    }

    /** Solo las categorías de la sucursal del usuario */
    private function propia(Categoria $categoria): void
    {
        abort_unless((int) $categoria->id_empresa_negocio === (int) Auth::user()->id_empresa_negocio, 404);
    }

    public function edit(Categoria $categoria)
    {
        $this->propia($categoria);
        $tipos = TipoProducto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();

        return view('empresas.categorias.edit', compact('categoria', 'tipos'));
    }

    public function update(Request $request, Categoria $categoria)
    {
        $this->propia($categoria);
        $request->validate(['cat_nom' => 'required|string|max:50', 'tip_pro_id' => 'nullable|integer'], [], ['cat_nom' => 'Nombre']);
        $categoria->update(['cat_nom' => $request->cat_nom, 'color' => $request->color ?: $categoria->color, 'tip_pro_id' => $this->linea($request)]);

        return redirect()->route('categorias.index')->with('success', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria)
    {
        $this->propia($categoria);
        $categoria->delete();

        return back()->with('success', 'Categoría eliminada.');
    }
}
