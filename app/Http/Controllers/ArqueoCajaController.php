<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\ArqueoCaja;
use App\Support\ArqueoDiario;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

        $base = DB::table('arqueos_caja as a')->where('a.id_empresa_negocio', $user->id_empresa_negocio)
            ->when(! $user->esAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('a.cajero', $user->IdUsuario)->orWhere('a.IdUsuario', $user->IdUsuario)))
            ->whereDate('a.created_at', '>=', $desde)->whereDate('a.created_at', '<=', $hasta);

        return view('empresas.arqueos.index', [
            'abiertos' => ArqueoCaja::turnosAbiertos($user), 'desde' => $desde, 'hasta' => $hasta, 'admin' => $user->esAdmin(),
            'puedeDiario' => $this->puedeDiario(), 'cajeros' => $user->esAdmin() ? DB::table('users')->where('id_empresa_negocio', $user->id_empresa_negocio)
                ->orderBy('apeusu')->pluck('apeusu', 'IdUsuario') : collect(),
            'totales' => $user->esAdmin() ? (clone $base)->selectRaw('COUNT(*) AS n, SUM(CASE WHEN diferencia < 0 THEN -diferencia ELSE 0 END) AS falta,
                SUM(CASE WHEN diferencia > 0 THEN diferencia ELSE 0 END) AS sobra, SUM(CASE WHEN diferencia = 0 THEN 1 ELSE 0 END) AS cuadran')->first() : null,
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
            'foto' => $ciego ? null : ArqueoCaja::foto($t, TurnoController::resumen($t))]);
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

    /** Con cierre ciego el cajero no puede ver cuánto debía haber: el arqueo diario lo ve solo el administrador */
    private function puedeDiario(): bool
    {
        $user = Auth::user();

        return $user->esAdmin() || ! DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->value('cierre_ciego');
    }

    /** Arqueo diario de caja en PDF (formato del sistema antiguo): el administrador, de toda la sucursal o de un cajero; el cajero, lo suyo */
    public function diario(Request $request): Response
    {
        $this->autorizar();
        abort_unless($this->puedeDiario(), 403, 'Con cierre ciego, el arqueo diario lo ve el administrador.');
        $user = Auth::user();
        $d = $request->validate(['fecha' => 'nullable|date', 'usuario' => 'nullable|integer']);
        $fecha = $d['fecha'] ?? now()->toDateString();
        $usuario = $user->esAdmin() ? (isset($d['usuario']) ? (int) $d['usuario'] : null) : (int) $user->IdUsuario;

        $negocio = DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->first(['nombre_comercial', 'direccion', 'IdEmpresa']);
        $empresa = DB::table('empresa')->where('IdEmpresa', $negocio->IdEmpresa)->first(['NomEmpresa', 'DirEmpresa']);
        $varias = DB::table('empresa_negocios')->where('IdEmpresa', $negocio->IdEmpresa)->where('estado', 'Activo')->count() > 1;

        $datos = ArqueoDiario::datos((int) $user->id_empresa_negocio, $fecha, $usuario);
        $html = view('empresas.arqueos.diario_pdf', $datos + [
            'empresa' => $empresa->NomEmpresa, 'direccion' => $negocio->direccion ?: $empresa->DirEmpresa, 'ruc' => $negocio->IdEmpresa,
            'sucursal' => $varias ? $negocio->nombre_comercial : null,
            'cajero' => $usuario ? DB::table('users')->where('IdUsuario', $usuario)->value('apeusu') : null,
        ])->render();

        $opciones = new Options;
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml($html, 'UTF-8');
        // Con muchos medios de pago las columnas no entran a lo alto
        $pdf->setPaper('A4', $datos['medios']->count() > 4 ? 'landscape' : 'portrait');
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Arqueo_Diario_'.$fecha.'.pdf"']);
    }
}
