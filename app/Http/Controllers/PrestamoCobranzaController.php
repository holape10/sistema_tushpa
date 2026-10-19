<?php

namespace App\Http\Controllers;

use App\Support\Prestamos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Cobranza del día (pensada para el celular del cobrador): a quién cobrar hoy, cuánto, quién ya pagó,
 * cobro con un toque, "no pagó" o promesa, WhatsApp, llamada y mapa. El cobrador ve solo sus clientes.
 */
class PrestamoCobranzaController extends Controller
{
    private function lista(Request $request): array
    {
        $u = Auth::user();
        $suc = (int) $u->id_empresa_negocio;
        $soloMios = Prestamos::esCobrador($u) && ! Prestamos::gestiona($u);
        $cobrador = $soloMios ? (int) $u->IdUsuario : ((int) $request->cobrador ?: null);
        $hoy = now()->toDateString();
        $cartera = Prestamos::cartera($suc, ['cobrador' => $cobrador]);
        $pagosHoy = DB::table('prestamo_pagos')->where('id_empresa_negocio', $suc)->where('anulado', 0)->where('tipo', 'PAGO')
            ->whereBetween('fecha_hora', [$hoy.' 00:00:00', $hoy.' 23:59:59'])->when($cobrador, fn ($q) => $q->whereIn('pre_id', $cartera->pluck('pre_id')))
            ->get(['pre_id', 'monto', 'mora', 'medio', 'IdUsuario']);
        // También los que hoy terminaron de pagar (ya no están vigentes)
        $terminados = DB::table('prestamos')->whereIn('pre_id', $pagosHoy->pluck('pre_id')->diff($cartera->pluck('pre_id')))->pluck('pre_id');
        if ($terminados->isNotEmpty()) {
            $cartera = $cartera->concat(Prestamos::cartera($suc, ['estados' => ['PAGADO'], 'cobrador' => $cobrador])->whereIn('pre_id', $terminados));
        }
        $gestiones = DB::table('prestamo_gestiones')->where('id_empresa_negocio', $suc)->whereBetween('fecha_hora', [$hoy.' 00:00:00', $hoy.' 23:59:59'])
            ->orderByDesc('ges_id')->get(['pre_id', 'resultado', 'promesa', 'nota'])->unique('pre_id')->keyBy('pre_id');
        $porPre = $pagosHoy->groupBy('pre_id');

        $filas = $cartera->map(function ($p) use ($porPre, $gestiones) {
            $pagado = round((float) ($porPre[$p->pre_id] ?? collect())->sum(fn ($g) => $g->monto + $g->mora), 2);
            $g = $gestiones[$p->pre_id] ?? null;

            return [
                'pre_id' => $p->pre_id, 'numero' => $p->numero, 'cliente' => $p->clinom, 'dni' => $p->clinum, 'telefono' => $p->telefono,
                'direccion' => $p->direccion, 'referencia' => $p->referencia, 'zona' => $p->zona, 'negocio' => $p->negocio, 'lat' => $p->lat, 'lng' => $p->lng,
                'cuota' => (float) $p->cuota, 'saldo' => $p->saldo, 'vencidas' => $p->vencidas, 'estado' => $p->estado, 'cobrar' => $p->cobrar_hoy,
                'mora' => $p->mora_sugerida, 'pagado_hoy' => $pagado, 'gestion' => $g ? Prestamos::RESULTADOS_GESTION[$g->resultado].($g->promesa ? ' ('.Carbon::parse($g->promesa)->format('d/m').')' : '') : null,
                'avance' => $p->avance, 'pagadas' => $p->pagadas, 'cuotas' => (int) $p->cuotas, 'cobrador' => $p->cobrador,
                'toca_hoy' => $p->cobrar_hoy > 0,
            ];
        })->sortBy(fn ($f) => ($f['pagado_hoy'] > 0 ? '2' : ($f['toca_hoy'] ? '0' : '1')).str_pad((string) (99 - min(99, $f['vencidas'])), 2, '0', STR_PAD_LEFT).($f['zona'] ?? '').$f['cliente'])->values();

        return [
            'filas' => $filas,
            'resumen' => ['cobrado' => round($pagosHoy->sum(fn ($g) => $g->monto + $g->mora), 2), 'pendiente' => round($cartera->where('estado', '!=', 'PAGADO')->sum('cobrar_hoy'), 2),
                'efectivo' => round($pagosHoy->where('medio', 'EFECTIVO')->sum(fn ($g) => $g->monto + $g->mora), 2),
                'digital' => round($pagosHoy->where('medio', '!=', 'EFECTIVO')->sum(fn ($g) => $g->monto + $g->mora), 2),
                'pagaron' => $porPre->count(), 'tocan' => $filas->where('toca_hoy', true)->count() + $filas->where('pagado_hoy', '>', 0)->where('toca_hoy', false)->count(),
                'visitas' => $gestiones->count()],
        ];
    }

    public function index(Request $request): View
    {
        abort_unless(Prestamos::usa(Auth::user()), 403, 'No tienes acceso a los préstamos.');
        $u = Auth::user();

        return view('empresas.prestamos.cobranza', $this->lista($request) + [
            'cfg' => Prestamos::config((int) $u->id_empresa_negocio), 'gestiona' => Prestamos::gestiona($u),
            'cobradores' => Prestamos::gestiona($u) ? PrestamoController::cobradores((int) $u->id_empresa_negocio) : collect(),
            'cobrador' => (int) $request->cobrador ?: null,
            'negocio' => (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $u->id_empresa_negocio)->value('nombre_comercial'),
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        abort_unless(Prestamos::usa(Auth::user()), 403);

        return response()->json($this->lista($request));
    }
}
