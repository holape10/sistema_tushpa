<?php
namespace App\Http\Controllers;

use App\Models\Piso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PisoController extends Controller
{
    public function index()
    {
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.pisos.index', compact('pisos'));
    }

    public function create()
    {
        return view('empresas.pisos.create');
    }

    public function store(Request $request)
    {
        $request->validate(['pis_nom' => 'required|string|max:255'], [], ['pis_nom' => 'Nombre del piso']);

        Piso::create([
            'pis_nom'            => $request->pis_nom,
            'emp_id'             => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('pisos.index')->with('success', 'Piso creado.');
    }

    public function edit(Piso $piso)
    {
        return view('empresas.pisos.edit', compact('piso'));
    }

    public function update(Request $request, Piso $piso)
    {
        $request->validate(['pis_nom' => 'required|string|max:255'], [], ['pis_nom' => 'Nombre del piso']);
        $piso->update(['pis_nom' => $request->pis_nom]);
        return redirect()->route('pisos.index')->with('success', 'Piso actualizado.');
    }

    public function destroy(Piso $piso)
    {
        $piso->delete();
        return back()->with('success', 'Piso eliminado.');
    }
}