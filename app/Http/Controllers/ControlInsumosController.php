<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Support\ControlInsumos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Control de insumos: lo que se debió gastar según las recetas frente a lo que faltó en los conteos */
class ControlInsumosController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/control-insumos'), 403, 'No tienes permiso para el control de insumos.');
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $d = $request->validate(['desde' => 'nullable|date', 'hasta' => 'nullable|date|after_or_equal:desde', 'almacen' => 'nullable|integer']);
        $desde = $d['desde'] ?? now()->subDays(29)->toDateString();
        $hasta = $d['hasta'] ?? now()->toDateString();
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'La sucursal no tiene almacén.');
        $almacen = $almacenes->firstWhere('id_almacen', (int) ($d['almacen'] ?? 0)) ?? $almacenes->first();

        return view('empresas.control_insumos.index', [
            'filas' => ControlInsumos::reporte($sucursal, (int) $almacen->id_almacen, $desde, $hasta),
            'almacenes' => $almacenes, 'almacen' => $almacen, 'desde' => $desde, 'hasta' => $hasta,
        ]);
    }
}
