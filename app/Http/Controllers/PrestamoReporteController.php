<?php

namespace App\Http\Controllers;

use App\Support\Prestamos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reportes de préstamos: cuadre de caja del día, clientes que deben, morosidad por antigüedad,
 * ganancias por mes y rendimiento de cada cobrador.
 */
class PrestamoReporteController extends Controller
{
    public function index(Request $request): View
    {
        $u = Auth::user();
        abort_unless($u->esAdmin() || $u->tieneModulo('/prestamos/reportes'), 403, 'No tienes acceso a los reportes de préstamos.');
        $suc = (int) $u->id_empresa_negocio;
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->fecha) ? $request->fecha : now()->toDateString();
        $cartera = Prestamos::cartera($suc);
        $usuario = DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as usuario");

        // 1) Cuadre del día
        $pagos = DB::table('prestamo_pagos as g')->join('prestamos as p', 'p.pre_id', '=', 'g.pre_id')->join('prestamo_clientes as k', 'k.pcl_id', '=', 'p.pcl_id')
            ->join('cliente as c', 'c.clicod', '=', 'k.clicod')->leftJoin('users as u', 'u.IdUsuario', '=', 'g.IdUsuario')
            ->where('g.id_empresa_negocio', $suc)->where('g.tipo', 'PAGO')->whereBetween('g.fecha_hora', [$fecha.' 00:00:00', $fecha.' 23:59:59'])
            ->orderBy('g.fecha_hora')->get(['g.*', 'p.numero', 'c.clinom', $usuario]);
        $validos = $pagos->where('anulado', 0);
        $desembolsos = DB::table('prestamos as p')->join('prestamo_clientes as k', 'k.pcl_id', '=', 'p.pcl_id')->join('cliente as c', 'c.clicod', '=', 'k.clicod')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.IdUsuario')->where('p.id_empresa_negocio', $suc)->where('p.estado', '!=', 'ANULADO')->where('p.fecha', $fecha)
            ->get(['p.pre_id', 'p.numero', 'p.monto', 'p.entregado', 'p.medio_entrega', 'p.renueva_pre_id', 'c.clinom', $usuario]);
        $medios = collect(Prestamos::MEDIOS)->mapWithKeys(fn ($m) => [$m => [
            'entra' => round($validos->where('medio', $m)->sum(fn ($g) => $g->monto + $g->mora), 2),
            'sale' => round($desembolsos->where('medio_entrega', $m)->sum('entregado'), 2),
        ]])->filter(fn ($v) => $v['entra'] || $v['sale']);
        $porUsuario = $validos->groupBy('usuario')->map(fn ($g, $n) => ['nombre' => $n ?: '—', 'cobros' => $g->count(), 'monto' => round($g->sum('monto'), 2), 'mora' => round($g->sum('mora'), 2)])->values();

        // 2) Morosidad por antigüedad
        $tramos = collect(['1 a 3 cuotas' => [1, 3], '4 a 7 cuotas' => [4, 7], '8 a 15 cuotas' => [8, 15], 'Más de 15' => [16, 9999]])
            ->map(fn ($r) => ['n' => $cartera->whereBetween('vencidas', $r)->count(), 'saldo' => round($cartera->whereBetween('vencidas', $r)->sum('saldo'), 2)]);
        $saldoTotal = max(0.01, $cartera->sum('saldo'));

        // 3) Ganancias de los últimos 6 meses
        $meses = collect(range(5, 0))->map(function ($i) use ($suc) {
            $ini = now()->subMonthsNoOverflow($i)->startOfMonth();
            $fin = $ini->copy()->endOfMonth();
            $pag = DB::table('prestamo_pagos as g')->join('prestamos as p', 'p.pre_id', '=', 'g.pre_id')->where('g.id_empresa_negocio', $suc)->where('g.anulado', 0)->where('g.tipo', 'PAGO')
                ->whereBetween('g.fecha_hora', [$ini, $fin])->selectRaw('COALESCE(SUM(g.monto),0) as cobrado, COALESCE(SUM(g.monto * p.interes / NULLIF(p.total,0)),0) as interes, COALESCE(SUM(g.mora),0) as mora')->first();

            return ['mes' => ucfirst($ini->locale('es')->isoFormat('MMM YYYY')), 'cobrado' => round((float) $pag->cobrado + (float) $pag->mora, 2),
                'interes' => round((float) $pag->interes, 2), 'mora' => round((float) $pag->mora, 2),
                'prestado' => round((float) DB::table('prestamos')->where('id_empresa_negocio', $suc)->where('estado', '!=', 'ANULADO')->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->sum('entregado'), 2),
                'nuevos' => DB::table('prestamos')->where('id_empresa_negocio', $suc)->where('estado', '!=', 'ANULADO')->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->count()];
        });

        // 4) Cobradores: cartera, lo cobrado en el día y del mes
        $mes = now()->startOfMonth();
        $cobMes = DB::table('prestamo_pagos as g')->join('prestamos as p', 'p.pre_id', '=', 'g.pre_id')->where('g.id_empresa_negocio', $suc)->where('g.anulado', 0)
            ->where('g.tipo', 'PAGO')->where('g.fecha_hora', '>=', $mes)->groupBy('p.cobrador_id')->select('p.cobrador_id', DB::raw('SUM(g.monto + g.mora) as t'))->pluck('t', 'cobrador_id');
        $cobradores = $cartera->groupBy(fn ($p) => $p->cobrador_id ?: 0)->map(fn ($g, $id) => [
            'nombre' => $g->first()->cobrador ?: 'Sin cobrador', 'prestamos' => $g->count(), 'saldo' => round($g->sum('saldo'), 2),
            'al_dia' => $g->where('estado', 'AL_DIA')->count(), 'morosos' => $g->where('estado', 'MOROSO')->count(),
            'cobrado_dia' => round($validos->whereIn('pre_id', $g->pluck('pre_id'))->sum(fn ($x) => $x->monto + $x->mora), 2),
            'pendiente_dia' => round($g->sum('cobrar_hoy'), 2), 'cobrado_mes' => round((float) ($cobMes[$id ?: ''] ?? 0), 2),
        ])->sortByDesc('saldo')->values();

        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $suc)->value('nombre_comercial');

        return view('empresas.prestamos.reportes', [
            'fecha' => $fecha, 'negocio' => $negocio, 'pagos' => $pagos, 'desembolsos' => $desembolsos, 'medios' => $medios, 'porUsuario' => $porUsuario,
            'cobradoDia' => round($validos->sum(fn ($g) => $g->monto + $g->mora), 2), 'prestadoDia' => round($desembolsos->sum('entregado'), 2),
            'deudores' => $cartera->sortByDesc('vencidas')->values(), 'tramos' => $tramos,
            'morosidad' => round($cartera->whereIn('estado', ['MOROSO'])->sum('saldo') / $saldoTotal * 100, 1),
            'atraso' => round($cartera->where('vencidas', '>', 0)->sum('saldo') / $saldoTotal * 100, 1),
            'meses' => $meses, 'cobradores' => $cobradores, 'esAdmin' => $u->esAdmin(),
            'cfg' => Prestamos::config($suc), 'capitalCalle' => round($cartera->sum('capital_pendiente'), 2),
            'gananciaTotal' => Prestamos::ganancia($suc, '2000-01-01', now()->toDateString()),
        ]);
    }
}
