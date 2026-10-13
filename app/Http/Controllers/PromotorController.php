<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Ventas de Promotores: lo vendido en los pedidos que llevan un promotor (los que envió el propio promotor y
 * los que un mozo o el cajero le incluyeron). Administrador y caja ven a todos; el promotor solo lo suyo.
 */
class PromotorController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = Auth::user();
        $esPromotor = $usuario->tieneRol([13]);
        abort_unless($esPromotor || $usuario->esAdminOCaja(), 403, 'Solo Administrador, Caja o Promotor.');
        $suc = (int) $usuario->id_empresa_negocio;

        $desde = Carbon::parse($request->get('desde', now()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->get('hasta', now()->toDateString()))->endOfDay();
        $promotores = ComandasController::promotores($suc);
        $elegido = $esPromotor ? (int) $usuario->IdUsuario : ((int) $request->get('promotor') ?: null);

        // Comprobantes vigentes con promotor (las notas de crédito restan)
        $ventas = DB::table('cpe_cabecera as c')
            ->leftJoin('pedidos as p', 'p.ped_id', '=', 'c.ped_id')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'c.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'm.pis_id')
            ->leftJoin('users as mz', 'mz.IdUsuario', '=', 'c.mozo')
            ->leftJoin('users as pr', 'pr.IdUsuario', '=', 'c.promotor_id')
            ->where('c.id_empresa_negocio', $suc)->whereNotNull('c.promotor_id')->whereNull('c.ccabaj')
            ->whereBetween('c.fecha_hora', [$desde, $hasta])
            ->when($elegido, fn ($q) => $q->where('c.promotor_id', $elegido))
            ->orderByDesc('c.fecha_hora')
            ->get(['c.IdCpe_cabecera', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.fecha_hora', 'c.ccanom', 'c.ccaitv', 'c.ped_tip', 'c.ped_id', 'c.promotor_id',
                'm.mes_nom', 'pi.pis_nom', DB::raw("COALESCE(NULLIF(mz.apeusu, ''), mz.name) as mozo"), DB::raw("COALESCE(NULLIF(pr.apeusu, ''), pr.name) as promotor")])
            ->map(function ($c) {
                $c->total = ($c->tdocod === '07' ? -1 : 1) * (float) $c->ccaitv;
                $c->lugar = $c->mes_nom ? trim(($c->pis_nom ? $c->pis_nom.' - ' : '').$c->mes_nom) : mb_strtoupper((string) ($c->ped_tip ?: 'Punto de venta'));

                return $c;
            });

        // Pedidos con promotor que aún no se cobran
        $abiertos = DB::table('pedidos as p')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'p.mes_id')
            ->leftJoin('pisos as pi', 'pi.pis_id', '=', 'm.pis_id')
            ->leftJoin('users as mz', 'mz.IdUsuario', '=', 'p.mozo')
            ->leftJoin('users as pr', 'pr.IdUsuario', '=', 'p.promotor_id')
            ->where('p.id_empresa_negocio', $suc)->whereNotNull('p.promotor_id')->where('p.ped_est', 'Aperturado')
            ->when($elegido, fn ($q) => $q->where('p.promotor_id', $elegido))
            ->orderBy('p.fecha_hora')
            ->get(['p.ped_id', 'p.fecha_hora', 'p.ped_tot', 'p.ped_tip', 'm.mes_nom', 'pi.pis_nom',
                DB::raw("COALESCE(NULLIF(mz.apeusu, ''), mz.name) as mozo"), DB::raw("COALESCE(NULLIF(pr.apeusu, ''), pr.name) as promotor")]);

        $total = round($ventas->sum('total'), 2);
        $documentos = $ventas->where('tdocod', '!=', '07')->count();
        $ranking = $ventas->groupBy('promotor_id')->map(fn ($g) => (object) [
            'nombre' => $g->first()->promotor ?? 'Sin nombre', 'ventas' => $g->where('tdocod', '!=', '07')->count(), 'total' => round($g->sum('total'), 2),
        ])->sortByDesc('total')->values();

        return view('empresas.promotores.index', [
            'esPromotor' => $esPromotor, 'promotores' => $promotores, 'elegido' => $elegido,
            'nombreElegido' => $elegido ? ($promotores->firstWhere('IdUsuario', $elegido)->nombre ?? $usuario->apeusu) : null,
            'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(),
            'ventas' => $ventas, 'abiertos' => $abiertos, 'ranking' => $ranking,
            'total' => $total, 'documentos' => $documentos, 'ticket' => $documentos ? round($total / $documentos, 2) : 0,
            'porCobrar' => round((float) $abiertos->sum('ped_tot'), 2),
        ]);
    }
}
