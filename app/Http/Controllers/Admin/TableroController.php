<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Central\Cliente;
use App\Models\Central\Marca;
use App\Models\Central\Superadmin;
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
        // Filtros: por usuario del panel y por marca (todo el tablero); solo las que faltan enviar y buscar empresa (la lista)
        $f = ['usuario' => $yo->es_dueno ? (int) $request->get('usuario') : 0, 'marca' => $yo->es_dueno ? (string) $request->get('marca') : '',
            'pendientes' => $request->boolean('pendientes'), 'buscar' => trim((string) $request->get('buscar'))];
        $clientes = Cliente::query()->where('estado', '!=', 'ELIMINADO')
            ->when(! $yo->es_dueno, fn ($q) => $q->where('creado_por', $yo->id))
            ->when($f['usuario'], fn ($q) => $q->where('creado_por', $f['usuario']))
            ->when($f['marca'] === 'tushpa', fn ($q) => $q->whereNull('marca_id'))
            ->when($f['marca'] !== '' && $f['marca'] !== 'tushpa', fn ($q) => $q->where('marca_id', (int) $f['marca']))
            ->orderBy('razon_social')->get();

        // "Actualizar ahora": vuelve a leer cada empresa sin esperar los 10 minutos
        $datos = TableroComprobantes::resumen($clientes, $desde, $hasta, $request->boolean('fresco'));

        // La lista de empresas: solo las que tienen comprobantes por enviar (las que más deben, primero) y/o la buscada
        $lista = collect($datos['empresas'])
            ->when($f['pendientes'], fn ($c) => $c->filter(fn ($e) => $e['pendientes'] > 0)->sortByDesc('pendientes'))
            ->when($f['buscar'] !== '', fn ($c) => $c->filter(fn ($e) => str_contains(mb_strtoupper($e['cliente']->razon_social.' '.$e['cliente']->ruc), mb_strtoupper($f['buscar']))))
            ->values()->all();

        return view('admin.tablero', ['empresas' => $lista] + $datos + compact('periodo', 'desde', 'hasta', 'nombre', 'f') + [
            'empresasActivas' => $clientes->count(),
            'conPendientes' => collect($datos['empresas'])->filter(fn ($e) => $e['pendientes'] > 0)->count(),
            'usuarios' => $yo->es_dueno ? Superadmin::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'marca_id']) : collect(),
            'marcas' => $yo->es_dueno ? Marca::orderBy('nombre')->get(['id', 'nombre']) : collect(),
        ]);
    }
}
