<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reporte "Propinas": cuánto dejaron los clientes y para qué mozo (el que atendió la mesa; si no hubo, el cajero),
 * por medio de pago, para repartirlas. Solo el administrador. Las ventas anuladas no cuentan.
 */
class PropinaController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdmin(), 403, 'Solo el administrador ve las propinas.');
        $suc = (int) $usuario->id_empresa_negocio;
        $desde = Carbon::parse($request->get('desde', now()->toDateString()))->toDateString();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->toDateString();

        $propinas = DB::table('propinas as p')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->leftJoin('users as u', 'u.IdUsuario', '=', DB::raw('COALESCE(c.mozo, c.IdUsuario)'))
            ->leftJoin('medios_pagos as m', 'm.id_med_pag', '=', 'p.id_med_pag')
            ->leftJoin('mesas as me', 'me.mes_id', '=', 'c.mes_id')
            ->where('p.id_empresa_negocio', $suc)->whereNull('c.ccabaj')
            ->whereBetween('p.created_at', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderByDesc('p.created_at')
            ->get(['p.monto', 'p.created_at', 'c.serdoc', 'c.numdoc', 'c.ccaitv', 'm.nom_med_pag', 'me.mes_nom',
                DB::raw('COALESCE(c.mozo, c.IdUsuario) as mozo_id'), DB::raw("COALESCE(NULLIF(u.apeusu, ''), u.name) as mozo")]);

        $porMozo = $propinas->groupBy('mozo_id')->map(fn ($g) => (object) [
            'mozo' => $g->first()->mozo ?: 'SIN MOZO', 'monto' => round((float) $g->sum('monto'), 2), 'cobros' => $g->count(),
            'ventas' => round((float) $g->sum('ccaitv'), 2),
        ])->sortByDesc('monto')->values();
        $porMedio = $propinas->groupBy('nom_med_pag')->map(fn ($g, $medio) => (object) ['medio' => $medio ?: 'PAGO', 'monto' => round((float) $g->sum('monto'), 2)])
            ->sortByDesc('monto')->values();

        return view('empresas.reportes.propinas', [
            'desde' => $desde, 'hasta' => $hasta, 'propinas' => $propinas, 'porMozo' => $porMozo, 'porMedio' => $porMedio,
            'total' => round((float) $propinas->sum('monto'), 2),
        ]);
    }
}
