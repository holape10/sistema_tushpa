<?php

namespace App\Http\Controllers;

use App\Support\Colegio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reportes del colegio: morosos (con WhatsApp al responsable), al día, cobranza de cada mes del año, por grado,
 * ingresos por concepto, descuentos y becas, y el detalle de pagos con su comprobante.
 */
class ColegioReporteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja ven los reportes.');
        $suc = (int) Auth::user()->id_empresa_negocio;
        $cfg = Colegio::config($suc);
        $anio = (int) $request->query('anio', $cfg->anio);
        $min = max(1, (int) $request->query('min', 1));
        try {
            $desde = Carbon::parse((string) $request->query('desde', now()->startOfMonth()->toDateString()))->toDateString();
            $hasta = Carbon::parse((string) $request->query('hasta', now()->toDateString()))->toDateString();
        } catch (\Throwable) {
            [$desde, $hasta] = [now()->startOfMonth()->toDateString(), now()->toDateString()];
        }
        $hoy = now()->toDateString();

        $situacion = Colegio::situacion($suc, $anio);
        $alumnos = DB::table('col_matriculas as m')->join('col_alumnos as a', 'a.alu_id', '=', 'm.alu_id')->join('col_grados as g', 'g.grado_id', '=', 'm.grado_id')
            ->where('m.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('m.estado', 'ACTIVA')
            ->get(['a.alu_id', 'a.nombres', 'a.dni', 'g.grado_id', 'g.nivel', 'g.grado', 'g.seccion', 'g.orden', 'm.dscto_pct', 'm.orden_grupo']);
        $responsable = DB::table('col_responsables as r')->join('cliente as c', 'c.clicod', '=', 'r.clicod')->whereIn('r.alu_id', $alumnos->pluck('alu_id'))
            ->orderByDesc('r.paga')->get(['r.alu_id', 'r.tipo', 'c.clinom', 'c.telefono'])->groupBy('alu_id')->map->first();
        $fila = fn ($a) => (object) [
            'id' => $a->alu_id, 'nombre' => $a->nombres, 'grado' => Colegio::etiquetaGrado($a), 'orden' => $a->orden,
            'meses' => $situacion[$a->alu_id]->meses ?? 0, 'vencido' => $situacion[$a->alu_id]->vencido ?? 0, 'pendiente' => $situacion[$a->alu_id]->pendiente ?? 0,
            'ultimo' => $situacion[$a->alu_id]->ultimo_pago ?? null, 'responsable' => $responsable[$a->alu_id] ?? null,
        ];
        $lista = $alumnos->map($fila);
        $morosos = $lista->filter(fn ($a) => $a->meses >= $min)->sortByDesc('vencido')->values();
        $alDia = $lista->filter(fn ($a) => $a->meses === 0 && $a->vencido < 0.01)->sortBy('nombre')->values();

        // Cobranza de cada mes del año (pensiones)
        $cobradoPor = DB::table('col_pagos as p')->join('col_cargos as c', 'c.car_id', '=', 'p.car_id')->where('c.id_empresa_negocio', $suc)
            ->where('p.anulado', 0)->groupBy('p.car_id')->select('p.car_id', DB::raw('SUM(p.monto) as monto'))->pluck('monto', 'p.car_id');
        $pensiones = DB::table('col_cargos as c')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')->where('c.id_empresa_negocio', $suc)
            ->where('m.anio', $anio)->where('c.estado', '!=', 'ANULADO')->get(['c.car_id', 'c.concepto', 'c.periodo', 'c.monto', 'c.descuento', 'c.pagado', 'c.estado', 'c.vence', 'c.alu_id']);
        $porMes = $pensiones->where('concepto', 'PENSION')->groupBy('periodo')->sortKeys()->map(fn ($g, $periodo) => (object) [
            'mes' => Colegio::nombreMes($periodo), 'vencio' => $g->first()->vence < $hoy, 'alumnos' => $g->count(),
            'esperado' => round($g->where('estado', '!=', 'BECADO')->sum(fn ($c) => $c->monto - $c->descuento), 2),
            'cobrado' => round($g->sum(fn ($c) => (float) ($cobradoPor[$c->car_id] ?? 0)), 2),
            'becado' => round($g->where('estado', 'BECADO')->sum(fn ($c) => $c->monto - $c->descuento), 2),
            'pendiente' => round($g->where('estado', 'PENDIENTE')->sum(fn ($c) => $c->monto - $c->descuento - $c->pagado), 2),
            'deben' => $g->where('estado', 'PENDIENTE')->count(),
        ])->values();

        // Por grado
        $gradoDe = $alumnos->pluck('grado_id', 'alu_id');
        $porGrado = $alumnos->groupBy('grado_id')->map(function ($g) use ($pensiones, $gradoDe, $cobradoPor, $situacion) {
            $suyos = $pensiones->filter(fn ($c) => ($gradoDe[$c->alu_id] ?? null) === $g->first()->grado_id && $c->estado !== 'BECADO');

            return (object) ['grado' => Colegio::etiquetaGrado($g->first()), 'orden' => $g->first()->orden, 'alumnos' => $g->count(),
                'esperado' => round($suyos->sum(fn ($c) => $c->monto - $c->descuento), 2),
                'cobrado' => round($suyos->sum(fn ($c) => (float) ($cobradoPor[$c->car_id] ?? 0)), 2),
                'pendiente' => round($suyos->where('estado', 'PENDIENTE')->sum(fn ($c) => $c->monto - $c->descuento - $c->pagado), 2),
                'morosos' => $g->filter(fn ($a) => ($situacion[$a->alu_id]->meses ?? 0) >= 1)->count()];
        })->sortBy('orden')->values();

        // Ingresos y pagos del rango de fechas
        $pagos = DB::table('col_pagos as p')->join('col_cargos as c', 'c.car_id', '=', 'p.car_id')->join('col_alumnos as a', 'a.alu_id', '=', 'c.alu_id')
            ->join('cpe_cabecera as cab', 'cab.IdCpe_cabecera', '=', 'p.IdCpe_cabecera')
            ->where('c.id_empresa_negocio', $suc)->where('p.anulado', 0)->whereBetween('p.fecha', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->orderBy('p.fecha')->get(['p.fecha', 'p.monto', 'p.descuento', 'p.pagador', 'c.concepto', 'c.descripcion', 'a.nombres', 'a.alu_id',
                'cab.IdCpe_cabecera', 'cab.serdoc', 'cab.numdoc', 'cab.ccanom']);
        $porConcepto = $pagos->groupBy('concepto')->map(fn ($g, $k) => (object) ['concepto' => Colegio::CONCEPTOS[$k] ?? $k, 'pagos' => $g->count(),
            'monto' => round($g->sum('monto'), 2), 'descuento' => round($g->sum('descuento'), 2)])->values();

        $becas = DB::table('col_cargos as c')->join('col_alumnos as a', 'a.alu_id', '=', 'c.alu_id')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')
            ->where('c.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('c.estado', 'BECADO')
            ->orderBy('c.periodo')->get(['a.nombres', 'a.alu_id', 'c.descripcion', 'c.motivo', DB::raw('c.monto - c.descuento as monto')]);

        return view('empresas.colegio.reportes', [
            'cfg' => $cfg, 'anio' => $anio, 'min' => $min, 'desde' => $desde, 'hasta' => $hasta, 'ver' => (string) $request->query('ver', 'morosos'),
            'morosos' => $morosos, 'alDia' => $alDia, 'porMes' => $porMes, 'porGrado' => $porGrado, 'pagos' => $pagos, 'porConcepto' => $porConcepto, 'becas' => $becas,
            'resumen' => (object) [
                'alumnos' => $lista->count(), 'alDia' => $alDia->count(), 'morosos' => $lista->filter(fn ($a) => $a->meses >= 1)->count(),
                'vencido' => round($lista->sum('vencido'), 2), 'pendiente' => round($lista->sum('pendiente'), 2),
                'cobradoRango' => round($pagos->sum('monto'), 2),
                'dsctoGrupo' => round($pensiones->where('estado', '!=', 'BECADO')->sum('descuento'), 2),
                'dsctoAdelanto' => round((float) DB::table('col_pagos as p')->join('col_cargos as c', 'c.car_id', '=', 'p.car_id')->join('col_matriculas as m', 'm.mat_id', '=', 'c.mat_id')
                    ->where('c.id_empresa_negocio', $suc)->where('m.anio', $anio)->where('p.anulado', 0)->sum('p.descuento'), 2),
                'becas' => round($becas->sum('monto'), 2), 'nBecas' => $becas->count(),
            ],
        ]);
    }
}
