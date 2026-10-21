<?php

namespace App\Http\Controllers;

use App\Support\LineaTiempo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Línea de tiempo: qué hizo cada usuario en el día, en orden y en palabras simples (solo el administrador) */
class LineaTiempoController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador ve la línea de tiempo.');
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        try {
            $fecha = Carbon::parse((string) $request->query('fecha', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            $fecha = now()->toDateString();
        }
        $usuario = (int) $request->query('usuario') ?: null;
        $tipo = array_key_exists((string) $request->query('tipo'), LineaTiempo::TIPOS) ? (string) $request->query('tipo') : null;

        $todos = LineaTiempo::eventos($sucursal, $fecha);
        $personas = DB::table('users as u')->leftJoin('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->leftJoin('roles as ro', 'ro.id', '=', 'r.role_id')
            ->whereIn('u.IdUsuario', $todos->pluck('usuario')->filter()->unique())
            ->get(['u.IdUsuario', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as nombre"), 'ro.description as rol'])->keyBy('IdUsuario');

        // Resumen de cada persona: cuántas cosas hizo, cuánto cobró, alertas y de qué hora a qué hora trabajó
        $resumen = $todos->groupBy(fn ($e) => (int) $e['usuario'])->map(fn ($evs, $id) => [
            'id' => $id, 'nombre' => $personas[$id]->nombre ?? 'Sin usuario', 'rol' => $personas[$id]->rol ?? '',
            'acciones' => $evs->count(), 'alertas' => $evs->where('alerta', true)->count(),
            'cobrado' => round($evs->where('tipo', 'venta')->filter(fn ($e) => str_starts_with($e['titulo'], 'Cobró'))->sum('monto'), 2),
            'desde' => substr($evs->first()['hora'], 11, 5), 'hasta' => substr($evs->last()['hora'], 11, 5),
        ])->sortByDesc('acciones')->values();

        $eventos = $todos->when($usuario, fn ($c) => $c->where('usuario', $usuario))->when($tipo, fn ($c) => $c->where('tipo', $tipo))->values();

        return view('empresas.actividad.index', [
            'fecha' => $fecha, 'usuario' => $usuario, 'tipo' => $tipo, 'eventos' => $eventos, 'resumen' => $resumen,
            'personas' => $personas, 'tipos' => LineaTiempo::TIPOS, 'total' => $todos->count(),
            'porTipo' => $todos->when($usuario, fn ($c) => $c->where('usuario', $usuario))->countBy('tipo'),
        ]);
    }
}
