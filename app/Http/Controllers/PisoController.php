<?php

namespace App\Http\Controllers;

use App\Models\Piso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PisoController extends Controller
{
    /** Quien tiene la opción en su menú, y solo los pisos de su sucursal (nunca los de otra cambiando el número en la dirección) */
    private function autorizar(?Piso $piso = null): void
    {
        abort_unless(Auth::user()->tieneModulo('/pisos'), 403, 'No tienes permiso para configurar los pisos.');
        abort_unless($piso === null || (int) $piso->id_empresa_negocio === (int) Auth::user()->id_empresa_negocio, 404);
    }

    public function index()
    {
        $this->autorizar();
        $pisos = Piso::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();

        return view('empresas.pisos.index', compact('pisos'));
    }

    public function create()
    {
        $this->autorizar();

        return view('empresas.pisos.create');
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $request->validate(['pis_nom' => 'required|string|max:255'], [], ['pis_nom' => 'Nombre del piso']);

        Piso::create([
            'pis_nom' => $request->pis_nom,
            'emp_id' => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('pisos.index')->with('success', 'Piso creado.');
    }

    public function edit(Piso $piso)
    {
        $this->autorizar($piso);

        return view('empresas.pisos.edit', compact('piso'));
    }

    public function update(Request $request, Piso $piso)
    {
        $this->autorizar($piso);
        $request->validate(['pis_nom' => 'required|string|max:255'], [], ['pis_nom' => 'Nombre del piso']);
        $piso->update(['pis_nom' => $request->pis_nom]);

        return redirect()->route('pisos.index')->with('success', 'Piso actualizado.');
    }

    public function destroy(Piso $piso)
    {
        $this->autorizar($piso);
        $piso->delete();

        return back()->with('success', 'Piso eliminado.');
    }
}
