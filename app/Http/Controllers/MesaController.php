<?php
namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Piso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MesaController extends Controller
{
    public function index()
    {
        $mesas = Mesa::with('piso')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.mesas.index', compact('mesas'));
    }

    public function create()
    {
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.mesas.create', compact('pisos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'mes_nom' => 'required|string|max:255',
            'pis_id'  => 'required|exists:pisos,pis_id',
        ], [], ['mes_nom' => 'Nombre de la mesa', 'pis_id' => 'Piso']);

        Mesa::create([
            'mes_nom'            => $request->mes_nom,
            'pis_id'             => $request->pis_id,
            'mes_est'            => 'Libre',
            'IdEmpresa'          => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('mesas.index')->with('success', 'Mesa creada.');
    }

    public function edit(Mesa $mesa)
    {
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.mesas.edit', compact('mesa', 'pisos'));
    }

    public function update(Request $request, Mesa $mesa)
    {
        $request->validate([
            'mes_nom' => 'required|string|max:255',
            'pis_id'  => 'required|exists:pisos,pis_id',
        ], [], ['mes_nom' => 'Nombre de la mesa', 'pis_id' => 'Piso']);

        $mesa->update($request->only(['mes_nom', 'pis_id', 'mes_est']));
        return redirect()->route('mesas.index')->with('success', 'Mesa actualizada.');
    }

    public function destroy(Mesa $mesa)
    {
        $mesa->delete();
        return back()->with('success', 'Mesa eliminada.');
    }
}