<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\ArqueoCaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Arqueo de caja: contar el efectivo de un turno abierto en cualquier momento, sin cerrarlo, y su historial */
class ArqueoCajaController extends Controller
{
    private function autorizar(): void
    {
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->tieneModulo('/arqueos'), 403, 'No tienes permiso para el arqueo de caja.');
    }

    /** Un turno abierto de la sucursal que este usuario puede contar */
    private function turno(int $id): Turno
    {
        $turno = ArqueoCaja::turnosAbiertos(Auth::user())->firstWhere('id_turno', $id);
        abort_unless($turno, 404, 'Ese turno no está abierto o no es tuyo.');

        return $turno;
    }

    public function index(Request $request): View
    {
        $this->autorizar();
        $user = Auth::user();
        $desde = (string) $request->get('desde', now()->subDays(30)->toDateString());
        $hasta = (string) $request->get('hasta', now()->toDateString());

        return view('empresas.arqueos.index', [
            'abiertos' => ArqueoCaja::turnosAbiertos($user), 'desde' => $desde, 'hasta' => $hasta, 'admin' => $user->esAdmin(),
            'arqueos' => DB::table('arqueos_caja as a')->join('turnos as t', 't.id_turno', '=', 'a.id_turno')
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'a.IdUsuario')->leftJoin('users as c', 'c.IdUsuario', '=', 'a.cajero')
                ->where('a.id_empresa_negocio', $user->id_empresa_negocio)
                ->when(! $user->esAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('a.cajero', $user->IdUsuario)->orWhere('a.IdUsuario', $user->IdUsuario)))
                ->whereDate('a.created_at', '>=', $desde)->whereDate('a.created_at', '<=', $hasta)
                ->orderByDesc('a.id')->select('a.*', 't.turno', 't.estado as estado_turno', 'u.apeusu as conto', 'c.apeusu as nombre_cajero')
                ->paginate(25)->withQueryString(),
        ]);
    }

    public function create(int $turno): View
    {
        $this->autorizar();
        $t = $this->turno($turno);
        $ciego = $t->esCiegoPara(Auth::user());

        return view('empresas.arqueos.create', ['turno' => $t, 'denominaciones' => Turno::DENOMINACIONES, 'ciego' => $ciego,
            'r' => $ciego ? null : TurnoController::resumen($t)]);
    }

    public function store(Request $request, int $turno): RedirectResponse
    {
        $this->autorizar();
        $t = $this->turno($turno);
        $d = $request->validate(['billetes' => 'nullable|array', 'billetes.*' => 'nullable|integer|min:0|max:100000',
            'monto' => 'nullable|numeric|min:0|max:9999999', 'observacion' => 'nullable|string|max:300'], [], ['monto' => 'total contado']);
        try {
            $id = ArqueoCaja::registrar(Auth::user(), $t, $d['billetes'] ?? [], isset($d['monto']) ? (float) $d['monto'] : null, $d['observacion'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('arqueos.show', $id)->with('success', 'Arqueo registrado.');
    }

    public function show(int $id): View
    {
        $this->autorizar();
        $user = Auth::user();
        $a = DB::table('arqueos_caja as a')->join('turnos as t', 't.id_turno', '=', 'a.id_turno')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'a.IdUsuario')->leftJoin('users as c', 'c.IdUsuario', '=', 'a.cajero')
            ->where('a.id', $id)->where('a.id_empresa_negocio', $user->id_empresa_negocio)
            ->select('a.*', 't.turno', 't.apertura', 't.estado as estado_turno', 'u.apeusu as conto', 'c.apeusu as nombre_cajero')->first();
        abort_unless($a && ($user->esAdmin() || (int) $a->cajero === (int) $user->IdUsuario || (int) $a->IdUsuario === (int) $user->IdUsuario), 404);
        // Cierre ciego: el cajero no ve cuánto debía haber mientras su turno siga abierto
        $ciego = $a->estado_turno === 'ABIERTO' && (bool) Turno::find($a->id_turno)?->esCiegoPara($user);

        return view('empresas.arqueos.show', ['a' => $a, 'ciego' => $ciego, 'denominaciones' => Turno::DENOMINACIONES,
            'negocio' => DB::table('empresa_negocios')->where('id_empresa_negocio', $a->id_empresa_negocio)->value('nombre_comercial')]);
    }
}
