<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Support\TableroComprobantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Tablero del panel: cuántos comprobantes emitieron todas las empresas en un periodo (el dueño ve todas;
 * los demás usuarios del panel, solo las que crearon).
 */
class TableroController extends Controller
{
    public function index(Request $request): View
    {
        $periodo = array_key_exists($request->get('periodo'), TableroComprobantes::PERIODOS) ? $request->get('periodo') : 'mes';
        [$desde, $hasta, $nombre] = TableroComprobantes::periodo($periodo, $request->get('desde'), $request->get('hasta'));
        if ($request->filled('desde') && $request->filled('hasta')) {
            $periodo = 'rango';
        }

        $yo = Auth::guard('superadmin')->user();
        $clientes = Cliente::query()->where('estado', '!=', 'ELIMINADO')
            ->when(! $yo->es_dueno, fn ($q) => $q->where('creado_por', $yo->id))
            ->orderBy('razon_social')->get();

        $datos = TableroComprobantes::resumen($clientes, $desde, $hasta);

        return view('admin.tablero', $datos + compact('periodo', 'desde', 'hasta', 'nombre') + ['empresasActivas' => $clientes->count()]);
    }
}
