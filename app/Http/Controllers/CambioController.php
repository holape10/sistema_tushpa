<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Support\Cambios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Cambios de producto: desde el Panel de Ventas (⋮ › Cambio de producto) */
class CambioController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->puedeCobrar(), 403, 'Solo Administrador o Caja hacen cambios de producto.');
        abort_unless(Cambios::disponible(), 404);
    }

    public function create(int $venta): View
    {
        $this->autorizar();
        try {
            $datos = Cambios::venta(Auth::user(), $venta);
        } catch (\RuntimeException $e) {
            return view('empresas.cambios.create', ['error' => $e->getMessage(), 'venta' => null, 'lineas' => collect(), 'medios' => collect()]);
        }

        return view('empresas.cambios.create', $datos + ['error' => null,
            'medios' => MedioPago::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag'])]);
    }

    public function store(Request $request, int $venta): JsonResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'devuelve' => 'required|array|min:1|max:100',
            'devuelve.*.linea' => 'required|integer',
            'devuelve.*.cantidad' => 'required|numeric|min:0|max:99999',
            'lleva' => 'required|array|min:1|max:100',
            'lleva.*.id' => 'required|integer',
            'lleva.*.cantidad' => 'required|numeric|min:0|max:99999',
            'medio' => 'nullable|integer',
            'motivo' => 'nullable|string|max:150',
        ], ['devuelve.required' => 'Elige qué producto devuelve el cliente.', 'lleva.required' => 'Elige el producto que se lleva a cambio.']);
        try {
            $id = Cambios::hacer(Auth::user(), $venta, $d['devuelve'], $d['lleva'], ['medio' => $d['medio'] ?? null, 'motivo' => $d['motivo'] ?? null]);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        $c = Cambios::ver(Auth::user(), $id);

        return response()->json(['ok' => true, 'id' => $id, 'codigo' => $c->codigo, 'diferencia' => (float) $c->diferencia, 'ver' => route('cambios.show', $id),
            'ticket' => $c->IdCpe_diferencia ? url('voucher/'.$c->IdCpe_diferencia).'?formato=ticket' : null]);
    }

    public function show(int $id): View
    {
        $this->autorizar();
        try {
            $c = Cambios::ver(Auth::user(), $id);
        } catch (\RuntimeException) {
            abort(404);
        }

        return view('empresas.cambios.show', ['c' => $c]);
    }
}
