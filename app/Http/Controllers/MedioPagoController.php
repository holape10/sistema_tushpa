<?php
namespace App\Http\Controllers;

use App\Models\MedioPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedioPagoController extends Controller
{
    public function index()
    {
        $mediosPagos = MedioPago::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->get();
        return view('empresas.mediospagos.index', compact('mediosPagos'));
    }

    public function create()
    {
        return view('empresas.mediospagos.create');
    }

    public function store(Request $request)
    {
        $request->validate(['nom_med_pag' => 'required|string|max:255'], [], ['nom_med_pag' => 'Nombre']);

        MedioPago::create([
            'nom_med_pag'        => $request->nom_med_pag,
            'tipo_medio'         => $request->tipo_medio,
            'predeterminado'     => $request->predeterminado ? '1' : '0',
            'IdEmpresa'          => Auth::user()->IdEmpresa,
            'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
        ]);

        return redirect()->route('mediospagos.index')->with('success', 'Medio de pago creado.');
    }

    public function edit(MedioPago $medioPago)
    {
        return view('empresas.mediospagos.edit', compact('medioPago'));
    }

    public function update(Request $request, MedioPago $medioPago)
    {
        $request->validate(['nom_med_pag' => 'required|string|max:255'], [], ['nom_med_pag' => 'Nombre']);
        $medioPago->update($request->only(['nom_med_pag', 'tipo_medio']));
        return redirect()->route('mediospagos.index')->with('success', 'Medio de pago actualizado.');
    }

    public function destroy(MedioPago $medioPago)
    {
        $medioPago->delete();
        return back()->with('success', 'Medio de pago eliminado.');
    }
}