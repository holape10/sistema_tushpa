<?php

namespace App\Http\Controllers;

use App\Models\Turno;
use App\Support\CourierCuentas;
use App\Support\Impresion\Impresion;
use App\Support\VentaDirecta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Clientes empresa con crédito en el courier: su línea (límite y plazo), su estado de cuenta y la factura al crédito por
 * los envíos del periodo.
 */
class CourierCreditoController extends Controller
{
    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo el administrador o la caja usan los envíos.');

        return (string) Auth::user()->IdEmpresa;
    }

    public function index(): View
    {
        $ruc = $this->ruc();
        $creditos = DB::table('cou_creditos as c')->join('cliente as cl', 'cl.clicod', '=', 'c.clicod')->where('c.IdEmpresa', $ruc)
            ->orderByDesc('c.activo')->orderBy('cl.clinom')->get(['c.*', 'cl.clinum', 'cl.clinom', 'cl.telefono']);
        $deudas = DB::table('cou_envios')->whereIn('rem_clicod', $creditos->pluck('clicod'))->where('pago', 'CREDITO')->whereNull('IdCpe_cabecera')
            ->where('estado', '!=', 'ANULADO')->groupBy('rem_clicod')->selectRaw('rem_clicod, COUNT(*) envios, SUM(total) total')->get()->keyBy('rem_clicod');

        return view('empresas.courier.creditos.index', [
            'creditos' => $creditos->map(fn ($c) => (object) ((array) $c + ['envios' => (int) ($deudas[$c->clicod]->envios ?? 0), 'deuda' => round((float) ($deudas[$c->clicod]->total ?? 0), 2)])),
            'esAdmin' => Auth::user()->esAdmin(),
        ]);
    }

    /** Da (o cambia) el crédito de un cliente: el administrador */
    public function guardar(Request $request): RedirectResponse
    {
        $ruc = $this->ruc();
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el administrador da crédito.');
        $d = $request->validate(['clinum' => 'required|string|min:8|max:11', 'clinom' => 'required|string|max:150', 'telefono' => 'nullable|string|max:20',
            'limite' => 'required|numeric|min:0|max:9999999', 'dias' => 'required|integer|min:1|max:120', 'activo' => 'nullable|boolean'],
            [], ['clinum' => 'DNI o RUC', 'clinom' => 'nombre o razón social', 'limite' => 'límite', 'dias' => 'plazo']);
        $doc = preg_replace('/\D/', '', $d['clinum']);
        $clicod = DB::table('cliente')->where('rucemp', $ruc)->where('clinum', $doc)->value('clicod')
            ?? DB::table('cliente')->insertGetId(['tdicod' => strlen($doc) === 11 ? '6' : '1', 'clinum' => $doc, 'clinom' => mb_strtoupper(trim($d['clinom'])),
                'telefono' => $d['telefono'] ?? null, 'clidir' => '--', 'rucemp' => $ruc, 'cliest' => 'Activo']);
        DB::table('cou_creditos')->updateOrInsert(['clicod' => $clicod], ['limite' => round((float) $d['limite'], 2), 'dias' => (int) $d['dias'],
            'activo' => $request->boolean('activo', true), 'IdEmpresa' => $ruc, 'creado' => now()]);

        return back()->with('success', 'Crédito guardado: ya puede enviar a crédito.');
    }

    /** Estado de cuenta: sus envíos a crédito del periodo, lo que falta facturar y lo ya facturado */
    public function cuenta(Request $request, int $clicod): View
    {
        $ruc = $this->ruc();
        $credito = DB::table('cou_creditos as c')->join('cliente as cl', 'cl.clicod', '=', 'c.clicod')->where('c.IdEmpresa', $ruc)->where('c.clicod', $clicod)
            ->first(['c.*', 'cl.clinum', 'cl.clinom', 'cl.clidir', 'cl.telefono']);
        abort_unless($credito, 404);
        $mes = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mes')) ? $request->query('mes') : null;
        $agencias = DB::table('cou_agencias')->where('IdEmpresa', $ruc)->get()->keyBy('age_id');
        $envios = DB::table('cou_envios')->where('rem_clicod', $clicod)->where('pago', 'CREDITO')->where('estado', '!=', 'ANULADO')
            ->when($mes, fn ($q) => $q->where('creado', 'like', $mes.'%'), fn ($q) => $q->where(fn ($w) => $w->whereNull('IdCpe_cabecera')->orWhere('creado', '>=', now()->subMonths(2))))
            ->orderBy('creado')->get();
        $comprobantes = DB::table('cpe_cabecera')->whereIn('IdCpe_cabecera', $envios->pluck('IdCpe_cabecera')->filter()->unique())->get(['IdCpe_cabecera', 'serdoc', 'numdoc', 'ccafve'])->keyBy('IdCpe_cabecera');

        return view('empresas.courier.creditos.cuenta', [
            'c' => $credito, 'mes' => $mes, 'agencias' => $agencias, 'envios' => $envios, 'comprobantes' => $comprobantes,
            'deuda' => round((float) CourierCuentas::sinFacturar($clicod)->sum('total'), 2), 'turno' => Turno::abiertoDe(Auth::user()),
            'meses' => DB::table('cou_envios')->where('rem_clicod', $clicod)->where('pago', 'CREDITO')->selectRaw("DISTINCT DATE_FORMAT(creado, '%Y-%m') mes")->orderByDesc('mes')->pluck('mes'),
        ]);
    }

    public function facturar(Request $request, int $clicod): JsonResponse
    {
        $this->ruc();
        $d = $request->validate(['ids' => 'required|array|min:1|max:300', 'ids.*' => 'integer', 'imprimir' => 'nullable|boolean']);
        try {
            $cabId = CourierCuentas::facturar(Auth::user(), $clicod, $d['ids']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        $impreso = false;
        if (! empty($d['imprimir'])) {
            try {
                $impreso = Impresion::comprobante($cabId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true, 'impreso' => $impreso] + VentaDirecta::respuesta($cabId));
    }
}
