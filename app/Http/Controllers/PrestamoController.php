<?php

namespace App\Http\Controllers;

use App\Support\Prestamos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Préstamos: panel con la cartera (capital en la calle, cobrado hoy, morosos), nuevo préstamo con simulador,
 * detalle con cronograma y pagos, cobro, anulaciones y la tarjeta de pagos para imprimir.
 */
class PrestamoController extends Controller
{
    private function suc(): int
    {
        return (int) Auth::user()->id_empresa_negocio;
    }

    private function usa(): void
    {
        abort_unless(Prestamos::usa(Auth::user()), 403, 'No tienes acceso a los préstamos.');
    }

    private function gestiona(): void
    {
        abort_unless(Prestamos::gestiona(Auth::user()), 403, 'Solo el administrador o caja registran préstamos.');
    }

    private function prestamo(int $id): object
    {
        $p = DB::table('prestamos')->where('pre_id', $id)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($p, 404);
        // El cobrador solo ve los préstamos que cobra
        $u = Auth::user();
        abort_if(Prestamos::esCobrador($u) && ! Prestamos::gestiona($u) && (int) $p->cobrador_id !== (int) $u->IdUsuario, 403, 'Este préstamo lo cobra otra persona.');

        return $p;
    }

    private function json(callable $f): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + (array) $f());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    private function negocio(): object
    {
        $n = DB::table('empresa_negocios as n')->join('empresa as e', 'e.IdEmpresa', '=', 'n.IdEmpresa')->where('n.id_empresa_negocio', $this->suc())
            ->first(['n.nombre_comercial', 'n.direccion', 'n.telefono', 'n.logo_suc', 'e.NomEmpresa', 'e.LogEmpresa']);
        $n->logo = collect([$n->logo_suc, $n->LogEmpresa])->first(fn ($l) => $l && is_file(public_path($l)));

        return $n;
    }

    public static function cobradores(int $suc)
    {
        return DB::table('users as u')->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')->where('u.id_empresa_negocio', $suc)->where('u.estusu', 1)
            ->whereIn('r.role_id', [2, 4, Prestamos::ROL_COBRADOR])->select('u.IdUsuario', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as nombre"), 'r.role_id')
            ->get()->unique('IdUsuario')->sortBy(fn ($u) => ($u->role_id == Prestamos::ROL_COBRADOR ? '0' : '1').$u->nombre)->values();
    }

    // ------------------------------------------------------------------ panel

    public function index()
    {
        $this->usa();
        $u = Auth::user();
        if (Prestamos::esCobrador($u) && ! Prestamos::gestiona($u)) {
            return redirect()->route('prestamos.cobranza');
        }
        $suc = $this->suc();
        $hoy = now()->toDateString();
        $mes = now()->startOfMonth()->toDateString();
        $cartera = Prestamos::cartera($suc);
        $pagosHoy = DB::table('prestamo_pagos')->where('id_empresa_negocio', $suc)->where('anulado', 0)->where('tipo', 'PAGO')
            ->whereBetween('fecha_hora', [$hoy.' 00:00:00', $hoy.' 23:59:59'])->selectRaw('COALESCE(SUM(monto),0) as monto, COALESCE(SUM(mora),0) as mora, COUNT(DISTINCT pre_id) as n')->first();
        $desembolsos = DB::table('prestamos')->where('id_empresa_negocio', $suc)->where('estado', '!=', 'ANULADO');
        $dias = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $cobros = DB::table('prestamo_pagos')->where('id_empresa_negocio', $suc)->where('anulado', 0)->where('tipo', 'PAGO')
            ->where('fecha_hora', '>=', $dias->first().' 00:00:00')->groupBy(DB::raw('DATE(fecha_hora)'))
            ->select(DB::raw('DATE(fecha_hora) as dia'), DB::raw('SUM(monto + mora) as total'))->pluck('total', 'dia');
        $prestado = (clone $desembolsos)->where('fecha', '>=', $dias->first())->groupBy('fecha')->select('fecha', DB::raw('SUM(entregado) as total'))->pluck('total', 'fecha');
        $porEstado = collect(['AL_DIA', 'ATRASADO', 'MOROSO'])->mapWithKeys(fn ($e) => [$e => ['n' => $cartera->where('estado', $e)->count(), 'saldo' => round($cartera->where('estado', $e)->sum('saldo'), 2)]]);

        return view('empresas.prestamos.index', [
            'negocio' => $this->negocio(),
            'kpi' => (object) [
                'capital' => round($cartera->sum('capital_pendiente'), 2),
                'porCobrar' => round($cartera->sum('saldo'), 2),
                'gananciaPorCobrar' => round($cartera->sum('saldo') - $cartera->sum('capital_pendiente'), 2),
                'cobradoHoy' => round((float) $pagosHoy->monto + (float) $pagosHoy->mora, 2),
                'pagaronHoy' => (int) $pagosHoy->n,
                'faltaHoy' => round($cartera->sum('cobrar_hoy'), 2),
                'deudoresHoy' => $cartera->where('cobrar_hoy', '>', 0)->count(),
                'activos' => $cartera->count(),
                'clientes' => $cartera->pluck('pcl_id')->unique()->count(),
                'prestadoHoy' => round((float) (clone $desembolsos)->where('fecha', $hoy)->sum('entregado'), 2),
                'prestadoMes' => round((float) (clone $desembolsos)->where('fecha', '>=', $mes)->sum('entregado'), 2),
                'gananciaMes' => Prestamos::ganancia($suc, $mes, $hoy),
                'cobradoMes' => round((float) DB::table('prestamo_pagos')->where('id_empresa_negocio', $suc)->where('anulado', 0)->where('tipo', 'PAGO')
                    ->where('fecha_hora', '>=', $mes.' 00:00:00')->sum(DB::raw('monto + mora')), 2),
            ],
            'porEstado' => $porEstado,
            'grafico' => $dias->map(fn ($d) => ['dia' => $d, 'cobrado' => round((float) ($cobros[$d] ?? 0), 2), 'prestado' => round((float) ($prestado[$d] ?? 0), 2)]),
            'morosos' => $cartera->whereIn('estado', ['MOROSO', 'ATRASADO'])->sortByDesc('vencidas')->take(8)->values(),
            'renovar' => $cartera->where('avance', '>=', 75)->sortByDesc('avance')->take(8)->values(),
            'cartera' => $cartera->map(fn ($p) => [
                'pre_id' => $p->pre_id, 'numero' => $p->numero, 'cliente' => $p->clinom, 'dni' => $p->clinum, 'zona' => $p->zona, 'cobrador' => $p->cobrador,
                'monto' => (float) $p->monto, 'total' => (float) $p->total, 'pagado' => (float) $p->pagado, 'saldo' => $p->saldo, 'cuota' => (float) $p->cuota,
                'avance' => $p->avance, 'vencidas' => $p->vencidas, 'estado' => $p->estado, 'cobrar_hoy' => $p->cobrar_hoy, 'ultima' => $p->ultima_cuota,
                'frecuencia' => $p->frecuencia,
            ])->values(),
            'puedeConfigurar' => $u->esAdmin(),
        ]);
    }

    // ------------------------------------------------------------------ nuevo préstamo

    public function nuevo(Request $request): View
    {
        $this->gestiona();
        $suc = $this->suc();
        $cfg = Prestamos::config($suc);
        $cliente = null;
        if ($request->filled('cliente')) {
            $cliente = PrestamoClienteController::datosCliente((int) $request->cliente, $suc);
        }

        return view('empresas.prestamos.nuevo', [
            'cfg' => $cfg, 'cliente' => $cliente,
            'planes' => DB::table('prestamo_planes')->where('id_empresa_negocio', $suc)->where('activo', 1)->orderByDesc('primer_prestamo')->orderBy('cuotas')->get(),
            'cobradores' => self::cobradores($suc),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->gestiona();
        $d = $request->validate([
            'pcl_id' => 'required|integer', 'plan_id' => 'nullable|integer', 'monto' => 'required|numeric|min:1|max:9999999',
            'interes_pct' => 'required|numeric|min:0|max:500', 'cuotas' => 'required|integer|min:1|max:400', 'frecuencia' => 'required|in:'.implode(',', array_keys(Prestamos::FRECUENCIAS)),
            'fecha' => 'nullable|date|before_or_equal:today|after:'.now()->subDays(60)->toDateString(),
            'cobrador_id' => ['nullable', 'integer', Rule::in(self::cobradores($this->suc())->pluck('IdUsuario')->map(fn ($u) => (int) $u)->all())],
            'medio_entrega' => 'nullable|in:'.implode(',', Prestamos::MEDIOS), 'renueva_pre_id' => 'nullable|integer', 'obs' => 'nullable|string|max:255',
        ], ['fecha.after' => 'La fecha del préstamo no puede ser de hace más de 60 días.'], ['interes_pct' => 'interés', 'cuotas' => 'número de cuotas']);

        return $this->json(function () use ($d) {
            $id = Prestamos::crear(Auth::user(), (int) $d['pcl_id'], $d);

            return ['mensaje' => 'Préstamo registrado.', 'pre_id' => $id, 'url' => route('prestamos.ver', $id)];
        });
    }

    // ------------------------------------------------------------------ detalle

    /** Préstamo con su cronograma, pagos, gestiones y situación (lo usan el detalle y la cobranza) */
    public static function detalle(object $p): array
    {
        $cfg = Prestamos::config((int) $p->id_empresa_negocio);
        $cuotas = DB::table('prestamo_cuotas')->where('pre_id', $p->pre_id)->orderBy('numero')->get();
        $s = Prestamos::situacion($p, $cuotas, null, $cfg);
        $cli = DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->where('k.pcl_id', $p->pcl_id)
            ->first(['k.*', 'c.clinom', 'c.clinum', 'c.telefono']);

        return [
            'prestamo' => $p, 'cliente' => $cli, 'situacion' => $s,
            'pagos' => DB::table('prestamo_pagos as g')->leftJoin('users as u', 'u.IdUsuario', '=', 'g.IdUsuario')->where('g.pre_id', $p->pre_id)
                ->orderByDesc('g.fecha_hora')->get(['g.*', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as usuario")]),
            'gestiones' => DB::table('prestamo_gestiones as g')->leftJoin('users as u', 'u.IdUsuario', '=', 'g.IdUsuario')->where('g.pre_id', $p->pre_id)
                ->orderByDesc('g.fecha_hora')->limit(30)->get(['g.*', DB::raw("COALESCE(NULLIF(TRIM(u.apeusu), ''), u.name) as usuario")]),
            'cobrador' => $p->cobrador_id ? DB::table('users')->where('IdUsuario', $p->cobrador_id)->value(DB::raw("COALESCE(NULLIF(TRIM(apeusu), ''), name)")) : null,
        ];
    }

    public function ver(int $id): View
    {
        $this->usa();
        $p = $this->prestamo($id);
        $u = Auth::user();

        return view('empresas.prestamos.ver', self::detalle($p) + [
            'cfg' => Prestamos::config($this->suc()), 'esAdmin' => $u->esAdmin(), 'gestiona' => Prestamos::gestiona($u),
            'cobradores' => Prestamos::gestiona($u) ? self::cobradores($this->suc()) : collect(),
            'negocio' => $this->negocio(), 'otros' => DB::table('prestamos')->where('pcl_id', $p->pcl_id)->where('pre_id', '!=', $p->pre_id)->orderByDesc('pre_id')->limit(10)
                ->get(['pre_id', 'numero', 'monto', 'total', 'estado', 'fecha']),
        ]);
    }

    public function pagar(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $p = $this->prestamo($id);
        $d = $request->validate([
            'monto' => 'required|numeric|min:0|max:9999999', 'mora' => 'nullable|numeric|min:0|max:99999', 'medio' => 'required|in:'.implode(',', Prestamos::MEDIOS),
            'operacion' => 'nullable|string|max:30', 'obs' => 'nullable|string|max:200', 'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
        ]);
        if ($d['medio'] !== 'EFECTIVO' && trim((string) ($d['operacion'] ?? '')) === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Escribe el N° de operación del '.mb_strtolower($d['medio']).'.']);
        }

        return $this->json(function () use ($p, $d) {
            $pagId = DB::transaction(fn () => Prestamos::registrarPago(Auth::user(), $p, (float) $d['monto'], (float) ($d['mora'] ?? 0), $d['medio'],
                $d['operacion'] ?? null, $d['obs'] ?? null, 'PAGO', $d['lat'] ?? null, $d['lng'] ?? null));
            $p = DB::table('prestamos')->where('pre_id', $p->pre_id)->first();
            $saldo = round((float) $p->total - (float) $p->pagado, 2);
            $cli = DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->where('k.pcl_id', $p->pcl_id)->first(['c.clinom', 'c.telefono']);
            $negocio = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $p->id_empresa_negocio)->value('nombre_comercial');
            $total = (float) $d['monto'] + (float) ($d['mora'] ?? 0);
            $texto = "{$negocio}: recibimos S/ ".number_format($total, 2).' de '.$cli->clinom.' el '.now()->format('d/m/Y H:i')
                .' (préstamo N° '.str_pad((string) $p->numero, 5, '0', STR_PAD_LEFT).'). '.($saldo > 0 ? 'Saldo: S/ '.number_format($saldo, 2).'.' : '¡Préstamo cancelado! Gracias.');

            return ['mensaje' => $saldo > 0 ? 'Pago registrado. Saldo S/ '.number_format($saldo, 2) : '¡Préstamo cancelado!', 'saldo' => $saldo,
                'pag_id' => $pagId, 'recibo' => route('prestamos.recibo', $pagId), 'whatsapp' => Prestamos::whatsapp($cli->telefono, $texto)];
        });
    }

    public function gestion(Request $request, int $id): JsonResponse
    {
        $this->usa();
        $p = $this->prestamo($id);
        $d = $request->validate(['resultado' => 'required|in:'.implode(',', array_keys(Prestamos::RESULTADOS_GESTION)),
            'promesa' => 'nullable|date|after_or_equal:today', 'nota' => 'nullable|string|max:200']);
        DB::table('prestamo_gestiones')->insert(['pre_id' => $p->pre_id, 'fecha_hora' => now(), 'resultado' => $d['resultado'], 'promesa' => $d['promesa'] ?? null,
            'nota' => $d['nota'] ?? null, 'IdUsuario' => Auth::id(), 'id_empresa_negocio' => $this->suc()]);

        return response()->json(['ok' => true, 'mensaje' => 'Visita anotada.']);
    }

    public function anularPago(Request $request, int $pag): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador anula pagos.');
        $d = $request->validate(['motivo' => 'required|string|min:3|max:200']);

        return $this->json(function () use ($pag, $d) {
            Prestamos::anularPago(Auth::user(), $pag, $d['motivo']);

            return ['mensaje' => 'Pago anulado.'];
        });
    }

    public function anular(Request $request, int $id): JsonResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador anula préstamos.');
        $this->prestamo($id);
        $d = $request->validate(['motivo' => 'required|string|min:3|max:150']);

        return $this->json(function () use ($id, $d) {
            Prestamos::anularPrestamo(Auth::user(), $id, $d['motivo']);

            return ['mensaje' => 'Préstamo anulado.'];
        });
    }

    /**
     * Editar el préstamo: caja cambia el cobrador o la nota; el administrador además corrige monto, interés,
     * cuotas, frecuencia y fecha (se rehace el cronograma y lo pagado se vuelve a aplicar).
     */
    public function editar(Request $request, int $id): JsonResponse
    {
        $this->gestiona();
        $p = $this->prestamo($id);
        $cobradores = self::cobradores($this->suc())->pluck('IdUsuario')->map(fn ($u) => (int) $u)->all();
        $reglas = ['cobrador_id' => ['nullable', 'integer', Rule::in($cobradores)], 'obs' => 'nullable|string|max:255'];
        $esAdmin = Auth::user()->esAdmin();
        if ($esAdmin) {
            $reglas += ['monto' => 'required|numeric|min:1|max:9999999', 'interes_pct' => 'required|numeric|min:0|max:500', 'cuotas' => 'required|integer|min:1|max:400',
                'frecuencia' => 'required|in:'.implode(',', array_keys(Prestamos::FRECUENCIAS)), 'fecha' => 'required|date|before_or_equal:today'];
        }
        $d = $request->validate($reglas, ['cobrador_id.in' => 'Ese cobrador no es de esta sucursal.'], ['interes_pct' => 'interés', 'cuotas' => 'número de cuotas']);

        return $this->json(function () use ($p, $d, $esAdmin) {
            if ($esAdmin) {
                Prestamos::corregir(Auth::user(), (int) $p->pre_id, ['fecha' => Carbon::parse($d['fecha'])->toDateString()] + $d);
            } else {
                DB::table('prestamos')->where('pre_id', $p->pre_id)->update(['cobrador_id' => $d['cobrador_id'] ?? null, 'obs' => $d['obs'] ?? null]);
            }

            return ['mensaje' => 'Préstamo actualizado.'];
        });
    }

    // ------------------------------------------------------------------ impresos

    /** Tarjeta de pagos (cronograma con casillas) para entregar al cliente */
    public function tarjeta(int $id): View
    {
        $this->usa();

        return view('empresas.prestamos.tarjeta', self::detalle($this->prestamo($id)) + ['negocio' => $this->negocio()]);
    }

    public function recibo(int $pag): View
    {
        $this->usa();
        $g = DB::table('prestamo_pagos')->where('pag_id', $pag)->where('id_empresa_negocio', $this->suc())->first();
        abort_unless($g, 404);
        $p = $this->prestamo((int) $g->pre_id);
        $cli = DB::table('prestamo_clientes as k')->join('cliente as c', 'c.clicod', '=', 'k.clicod')->where('k.pcl_id', $p->pcl_id)->first(['c.clinom', 'c.clinum']);
        $saldoTras = round((float) $p->total - (float) DB::table('prestamo_pagos')->where('pre_id', $p->pre_id)->where('anulado', 0)->where('pag_id', '<=', $g->pag_id)->sum('monto'), 2);

        return view('empresas.prestamos.recibo', ['g' => $g, 'p' => $p, 'cli' => $cli, 'saldo' => max(0, $saldoTras), 'negocio' => $this->negocio(),
            'usuario' => DB::table('users')->where('IdUsuario', $g->IdUsuario)->value(DB::raw("COALESCE(NULLIF(TRIM(apeusu), ''), name)"))]);
    }
}
