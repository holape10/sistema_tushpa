<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Certificados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Certificados digitales que se venden en tushpa.app: precios, textos y cuáles se muestran. Solo el dueño. */
class CertificadoController extends Controller
{
    private function autorizar(): void
    {
        abort_unless((bool) Auth::guard('superadmin')->user()->es_dueno, 403, 'Solo el dueño administra los certificados de la web.');
    }

    public function index(): View
    {
        $this->autorizar();

        return view('admin.certificados', ['certificados' => Certificados::db()->orderBy('orden')->orderBy('anios')->get()]);
    }

    public function guardar(Request $request, ?int $id = null): RedirectResponse
    {
        $this->autorizar();
        abort_if($id && ! Certificados::db()->where('id', $id)->exists(), 404);
        $d = $request->validate([
            'nombre' => 'required|string|max:60', 'anios' => 'required|integer|min:1|max:10',
            'precio' => 'required|numeric|min:1|max:99999', 'precio_antes' => 'nullable|numeric|min:0|max:99999|gt:precio',
            'detalle' => 'nullable|string|max:200', 'orden' => 'nullable|integer|min:0|max:999',
        ], ['precio_antes.gt' => 'El precio anterior (tachado) debe ser mayor que el precio.'], ['anios' => 'años', 'precio_antes' => 'precio anterior']);
        $fila = $d + ['destacado' => $request->boolean('destacado'), 'activo' => $request->boolean('activo'), 'orden' => (int) ($d['orden'] ?? 0), 'updated_at' => now()];

        $id ? Certificados::db()->where('id', $id)->update($fila) : Certificados::db()->insert($fila + ['created_at' => now()]);

        return redirect()->route('admin.certificados.index')->with('ok', 'Certificado guardado. Ya se ve en '.config('tenancy.dominio').'.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        $this->autorizar();
        Certificados::db()->where('id', $id)->delete();

        return redirect()->route('admin.certificados.index')->with('ok', 'Certificado eliminado.');
    }
}
