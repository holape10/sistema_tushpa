<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reporte "Descuentos y Cortesías": qué se descontó o se regaló al cobrar, quién lo hizo, quién lo autorizó y por qué.
 * Solo el administrador. Las ventas anuladas no cuentan.
 */
class DescuentosCortesiasController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = Auth::user();
        abort_unless($usuario->esAdmin(), 403, 'Solo el administrador ve los descuentos y cortesías.');
        $suc = (int) $usuario->id_empresa_negocio;

        $desde = Carbon::parse($request->get('desde', now()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        $cajero = (int) $request->get('cajero') ?: null;
        $nombre = fn (string $alias, string $como) => DB::raw("COALESCE(NULLIF({$alias}.apeusu, ''), {$alias}.name) as {$como}");

        $base = fn () => DB::table('cpe_cabecera as c')
            ->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->whereIn('c.tdocod', ['01', '03', '13'])
            ->whereBetween('c.fecha_hora', [$desde, $hasta])
            ->when($cajero, fn ($q) => $q->where('c.IdUsuario', $cajero));

        $descuentos = $base()->where('c.descuento', '>', 0)
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
            ->leftJoin('users as a', 'a.IdUsuario', '=', 'c.IdUsuario_descuento')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'c.mes_id')
            ->orderByDesc('c.fecha_hora')
            ->get(['c.IdCpe_cabecera', 'c.serdoc', 'c.numdoc', 'c.fecha_hora', 'c.ccanom', 'c.ccaitv', 'c.descuento', 'c.descuento_tipo', 'c.descuento_valor',
                'c.descuento_motivo', 'c.IdUsuario_descuento', 'c.IdUsuario', 'm.mes_nom', $nombre('u', 'cajero'), $nombre('a', 'autorizo')])
            ->map(function ($d) {
                $d->consumo = round((float) $d->ccaitv + (float) $d->descuento, 2);
                $d->porcentaje = $d->consumo > 0 ? round($d->descuento / $d->consumo * 100, 1) : 0;
                // Con clave: lo autorizó otro (el administrador) al cajero
                $d->conClave = $d->IdUsuario_descuento && (int) $d->IdUsuario_descuento !== (int) $d->IdUsuario;

                return $d;
            });

        $cortesias = DB::table('cpe_detalle as d')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'd.IdCpe_cabecera')
            ->where('d.cortesia', 1)->where('c.id_empresa_negocio', $suc)->whereNull('c.ccabaj')->whereIn('c.tdocod', ['01', '03', '13'])
            ->whereBetween('c.fecha_hora', [$desde, $hasta])
            ->when($cajero, fn ($q) => $q->where('c.IdUsuario', $cajero))
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'c.mes_id')
            ->orderByDesc('c.fecha_hora')
            ->get(['c.IdCpe_cabecera', 'c.serdoc', 'c.numdoc', 'c.fecha_hora', 'c.ccanom', 'd.cdedes', 'd.cdecan', 'd.precio_lista', 'd.costo',
                'd.cortesia_motivo', 'm.mes_nom', $nombre('u', 'cajero')])
            ->map(function ($c) {
                $c->valor = round((float) $c->cdecan * (float) $c->precio_lista, 2);
                $c->costoTotal = round((float) $c->cdecan * (float) $c->costo, 2);   // cpe_detalle.costo es por unidad

                return $c;
            });

        // Por cajero: cuánto descontó y cuánto regaló
        $porCajero = $descuentos->groupBy('cajero')->map(fn ($g, $n) => ['descuentos' => $g->sum('descuento'), 'veces' => $g->count()])
            ->mergeRecursive($cortesias->groupBy('cajero')->map(fn ($g) => ['cortesias' => $g->sum('valor'), 'items' => $g->sum('cdecan')]))
            ->map(fn ($v, $n) => (object) ['nombre' => $n ?: 'Sin nombre', 'descuentos' => round((float) ($v['descuentos'] ?? 0), 2),
                'veces' => (int) ($v['veces'] ?? 0), 'cortesias' => round((float) ($v['cortesias'] ?? 0), 2), 'items' => (float) ($v['items'] ?? 0)])
            ->sortByDesc(fn ($c) => $c->descuentos + $c->cortesias)->values();

        $cajeros = DB::table('cpe_cabecera as c')->join('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
            ->where('c.id_empresa_negocio', $suc)->where(fn ($q) => $q->where('c.descuento', '>', 0)
            ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('cpe_detalle as d')->whereColumn('d.IdCpe_cabecera', 'c.IdCpe_cabecera')->where('d.cortesia', 1)))
            ->distinct()->orderBy('nombre')->get(['u.IdUsuario', $nombre('u', 'nombre')]);

        return view('empresas.reportes.descuentos_cortesias', [
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'cajero' => $cajero, 'cajeros' => $cajeros,
            'descuentos' => $descuentos, 'cortesias' => $cortesias, 'porCajero' => $porCajero,
            'totalDescuentos' => round($descuentos->sum('descuento'), 2), 'totalCortesias' => round($cortesias->sum('valor'), 2),
            'costoCortesias' => round($cortesias->sum('costoTotal'), 2), 'conClave' => $descuentos->where('conClave', true)->count(),
        ]);
    }
}
