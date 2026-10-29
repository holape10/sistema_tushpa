<?php

namespace App\Http\Controllers;

use App\Support\Courier;
use App\Support\CourierCuentas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Reportes del courier (de lo que sale de la sucursal): envíos e ingresos por día y por destino, estados, mejores clientes,
 * lo que falta cobrar, los rezagados, los reclamos y la liquidación con las agencias aliadas.
 */
class CourierReporteController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja ven los reportes.');
        $user = Auth::user();
        $ruc = (string) $user->IdEmpresa;
        $desde = Carbon::parse($request->query('desde', now()->startOfMonth()->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->query('hasta', now()->toDateString()))->endOfDay();
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $ruc)->get()->keyBy('age_id');

        $envios = Courier::salen($user, DB::table('cou_envios as e'))->whereBetween('e.creado', [$desde, $hasta])->where('e.estado', '!=', 'ANULADO')->get(['e.*']);
        $porDia = $envios->groupBy(fn ($e) => substr((string) $e->creado, 0, 10))->map(fn ($g) => ['envios' => $g->count(), 'total' => round((float) $g->sum('total'), 2)])->sortKeys();
        $porDestino = $envios->groupBy('destino_id')->map(fn ($g, $id) => (object) ['agencia' => Courier::etiquetaAgencia($agencias[$id] ?? null), 'envios' => $g->count(),
            'piezas' => (int) $g->sum('piezas'), 'kg' => round((float) $g->sum('peso_cobrado'), 2), 'total' => round((float) $g->sum('total'), 2)])->sortByDesc('total')->values();
        $clientes = $envios->groupBy(fn ($e) => $e->rem_doc.'|'.$e->rem_nombre)->map(fn ($g, $k) => (object) ['nombre' => explode('|', $k, 2)[1], 'doc' => explode('|', $k, 2)[0],
            'envios' => $g->count(), 'total' => round((float) $g->sum('total'), 2)])->sortByDesc('total')->take(10)->values();
        $cfg = Courier::config($ruc);

        return view('empresas.courier.reportes', [
            'desde' => $desde, 'hasta' => $hasta,
            'kpi' => (object) [
                'envios' => $envios->count(), 'piezas' => (int) $envios->sum('piezas'), 'kg' => round((float) $envios->sum('peso_cobrado'), 2),
                'total' => round((float) $envios->sum('total'), 2), 'ticket' => $envios->count() ? round((float) $envios->avg('total'), 2) : 0,
                'cobrado' => round((float) $envios->whereNotNull('IdCpe_cabecera')->sum('total'), 2),
                'porCobrar' => round((float) $envios->whereNull('IdCpe_cabecera')->where('total', '>', 0)->where('estado', '!=', 'DEVUELTO')->sum('total'), 2),
                'entregados' => $envios->where('estado', 'ENTREGADO')->count(),
                'aTiempo' => $envios->where('estado', 'ENTREGADO')->filter(fn ($e) => $e->llegada_estimada && substr((string) $e->entregado, 0, 10) <= $e->llegada_estimada)->count(),
            ],
            'porDia' => $porDia, 'porDestino' => $porDestino, 'clientes' => $clientes,
            'porEstado' => $envios->countBy('estado'), 'porPago' => $envios->groupBy('pago')->map(fn ($g) => round((float) $g->sum('total'), 2)),
            'rezagados' => Courier::llegan($user, DB::table('cou_envios as e'))->where('e.estado', 'EN_DESTINO')->where('e.creado', '<', now()->subDays((int) $cfg->dias_almacen))
                ->orderBy('e.creado')->limit(50)->get(['e.*']),
            'diasAlmacen' => (int) $cfg->dias_almacen,
            'reclamos' => DB::table('cou_reclamos')->where('IdEmpresa', $ruc)->whereBetween('creado', [$desde, $hasta])->get()->countBy('estado'),
            'aliados' => CourierCuentas::porLiquidarAliados($ruc),
            'liquidaciones' => DB::table('cou_liquidaciones as l')->join('cou_agencias as a', 'a.age_id', '=', 'l.age_id')->where('l.IdEmpresa', $ruc)
                ->orderByDesc('l.liq_id')->limit(15)->get(['l.*', 'a.ciudad', 'a.nombre']),
            'esAdmin' => $user->esAdmin(),
        ]);
    }

    public function liquidarAliado(Request $request, int $ageId): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador liquida con los aliados.');
        $d = $request->validate(['observacion' => 'nullable|string|max:255']);
        try {
            CourierCuentas::liquidarAliado(Auth::user(), $ageId, $d['observacion'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Liquidación registrada.']);
    }
}
