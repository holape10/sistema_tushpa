<?php

namespace App\Http\Controllers;

use App\Models\EmpresaNegocio;
use App\Models\MedioPago;
use App\Models\Turno;
use App\Support\AnulacionVenta;
use App\Support\Notas;
use App\Support\Sunat\SunatService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja ven el panel de ventas.');
    }

    /** Sucursales de la empresa del usuario (el filtro nunca sale de su empresa) */
    private function sucursales()
    {
        return EmpresaNegocio::where('IdEmpresa', Auth::user()->IdEmpresa)->orderBy('id_empresa_negocio')->get();
    }

    private function venta($id): object
    {
        $cab = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)
            ->whereIn('id_empresa_negocio', $this->sucursales()->pluck('id_empresa_negocio'))->first();
        abort_unless($cab, 404);

        return $cab;
    }

    /** Consulta base con los filtros del formulario */
    private function consulta(Request $request)
    {
        $sucursal = (int) $request->get('sucursal', Auth::user()->id_empresa_negocio);
        abort_unless($this->sucursales()->contains('id_empresa_negocio', $sucursal), 403);

        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $cliente = trim((string) $request->get('cliente'));
        $comprobante = strtoupper(trim((string) $request->get('comprobante')));

        $q = DB::table('cpe_cabecera as c')
            ->leftJoin('tipo_documento as t', 't.tdocod', '=', 'c.tdocod')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
            ->leftJoin('mesas as m', 'm.mes_id', '=', 'c.mes_id')
            ->where('c.id_empresa_negocio', $sucursal)
            ->whereBetween('c.ccafem', [$desde, $hasta])
            ->when($request->filled('tipo'), fn ($w) => $w->where('c.tdocod', $request->tipo))
            ->when($cliente !== '', fn ($w) => $w->where(fn ($x) => $x->where('c.ccanom', 'like', "%$cliente%")->orWhere('c.ccandi', 'like', "$cliente%")))
            ->when($comprobante !== '', function ($w) use ($comprobante) {
                // Acepta "F001-123", "F001" o solo "123"
                if (preg_match('/^([A-Z0-9]{4})-?(\d+)?$/', $comprobante, $m) && ! ctype_digit($comprobante)) {
                    $w->where('c.serdoc', $m[1]);
                    if (! empty($m[2])) {
                        $w->where('c.numdoc', (int) $m[2]);
                    }
                } elseif (ctype_digit($comprobante)) {
                    $w->where('c.numdoc', (int) $comprobante);
                }
            })
            ->when($request->get('estado') === 'anuladas', fn ($w) => $w->whereNotNull('c.ccabaj'))
            ->when($request->get('estado') === 'vigentes', fn ($w) => $w->whereNull('c.ccabaj'))
            ->when($request->filled('sunat'), fn ($w) => $w->where('c.est_sunat', $request->sunat))
            ->when($request->filled('medio'), fn ($w) => $w->whereExists(fn ($s) => $s->select(DB::raw(1))->from('venta_medio_pago as v')
                ->whereColumn('v.IdCpe_cabecera', 'c.IdCpe_cabecera')->where('v.id_med_pag', $request->medio)));

        return [$q, compact('sucursal', 'desde', 'hasta', 'cliente', 'comprobante')];
    }

    public function index(Request $request)
    {
        $this->autorizar();
        [$q, $filtros] = $this->consulta($request);

        // Resumen sobre TODO lo filtrado (no solo la página)
        $vigentes = (clone $q)->whereNull('c.ccabaj');
        $resumen = [
            // Las notas de crédito restan y no cuentan como venta
            'total' => (float) (clone $vigentes)->sum(DB::raw(Notas::SIGNO_SQL.' * c.ccaitv')),
            'cantidad' => (clone $vigentes)->whereNotIn('c.tdocod', ['07', '08'])->count(),
            'credito' => (float) (clone $vigentes)->sum('c.totalcredito'),
            'anuladas' => (clone $q)->whereNotNull('c.ccabaj')->count(),
            'porTipo' => (clone $vigentes)->groupBy('c.tdocod', 't.tdodes')
                ->select('t.tdodes', DB::raw('COUNT(*) as n'), DB::raw('SUM('.Notas::SIGNO_SQL.' * c.ccaitv) as total'))->get(),
            'porMedio' => DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'v.id_med_pag')
                ->whereIn('v.IdCpe_cabecera', (clone $vigentes)->select('c.IdCpe_cabecera'))
                ->groupBy('mp.nom_med_pag')->select('mp.nom_med_pag', DB::raw('SUM(v.monto) as total'))->orderByDesc('total')->get(),
        ];

        $ventas = (clone $q)
            ->orderByDesc('c.fecha_hora')->orderByDesc('c.IdCpe_cabecera')
            ->select('c.*', 't.tdodes', 'u.apeusu as usuario', 'm.mes_nom')
            ->paginate(50)->withQueryString();

        // Medios de pago de las ventas de la página
        $medios = DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'v.id_med_pag')
            ->whereIn('v.IdCpe_cabecera', $ventas->pluck('IdCpe_cabecera'))
            ->get(['v.IdCpe_cabecera', 'mp.nom_med_pag', 'v.monto'])->groupBy('IdCpe_cabecera');

        // Turnos abiertos: solo esas ventas se pueden anular o cambiar de medio de pago
        $turnosAbiertos = Turno::where('estado', 'ABIERTO')->pluck('id_turno')->all();

        return view('empresas.ventas.index', $filtros + [
            'ventas' => $ventas, 'medios' => $medios, 'resumen' => $resumen, 'turnosAbiertos' => $turnosAbiertos,
            'sucursales' => $this->sucursales(),
            'tipos' => DB::table('tipo_documento')->orderBy('tdocod')->get(),
            'mediosPago' => MedioPago::where('id_empresa_negocio', $filtros['sucursal'])->get(),
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(),
        ]);
    }

    public function detalle($id)
    {
        $this->autorizar();
        $cab = $this->venta($id);

        return response()->json([
            'cabecera' => $cab,
            'detalle' => DB::table('cpe_detalle')->where('IdCpe_cabecera', $id)->get(['cdedes', 'cdecan', 'cdepuni', 'cdevve']),
            'medios' => DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'v.id_med_pag')
                ->where('v.IdCpe_cabecera', $id)->get(['v.id_med_pag', 'mp.nom_med_pag', 'v.monto']),
            'usuario' => DB::table('users')->where('IdUsuario', $cab->IdUsuario)->value('apeusu'),
            'anuladoPor' => $cab->IdUsuario_baja ? DB::table('users')->where('IdUsuario', $cab->IdUsuario_baja)->value('apeusu') : null,
        ]);
    }

    /** Ventas de un turno ya cerrado no se tocan: cambiarían el cuadre de caja que ya se entregó */
    private function validarTurnoAbierto(object $cab): ?string
    {
        if (! $cab->id_turno || ! Turno::where('id_turno', $cab->id_turno)->where('estado', 'ABIERTO')->exists()) {
            return 'Esta venta pertenece a un turno ya CERRADO; no se puede modificar.';
        }

        return null;
    }

    public function actualizarMedios(Request $request, $id)
    {
        $this->autorizar();
        $cab = $this->venta($id);
        $request->validate([
            'medios' => 'required|array|min:1',
            'medios.*.id_med_pag' => 'required|integer',
            'medios.*.monto' => 'required|numeric|min:0.01',
        ]);

        if ($cab->ccabaj) {
            return response()->json(['success' => false, 'message' => 'La venta está anulada.']);
        }
        if ($cab->estadopago !== 'CONTADO') {
            return response()->json(['success' => false, 'message' => 'Las ventas al crédito no tienen medio de pago.']);
        }
        if ($error = $this->validarTurnoAbierto($cab)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        $validos = MedioPago::where('id_empresa_negocio', $cab->id_empresa_negocio)->pluck('id_med_pag')->map(fn ($v) => (int) $v)->all();
        $lineas = collect($request->medios)->map(fn ($m) => ['id' => (int) $m['id_med_pag'], 'monto' => round((float) $m['monto'], 2)]);
        if ($lineas->pluck('id')->diff($validos)->isNotEmpty() || $lineas->pluck('id')->duplicates()->isNotEmpty()) {
            return response()->json(['success' => false, 'message' => 'Medio de pago no válido o repetido.']);
        }
        if (abs($lineas->sum('monto') - (float) $cab->ccaitv) > 0.01) {
            return response()->json(['success' => false, 'message' => 'Los medios suman S/ '.number_format($lineas->sum('monto'), 2)
                .' y el total de la venta es S/ '.number_format($cab->ccaitv, 2).'.']);
        }

        DB::transaction(function () use ($cab, $lineas) {
            DB::table('venta_medio_pago')->where('IdCpe_cabecera', $cab->IdCpe_cabecera)->delete();
            foreach ($lineas as $l) {
                DB::table('venta_medio_pago')->insert([
                    'IdCpe_cabecera' => $cab->IdCpe_cabecera, 'id_med_pag' => $l['id'], 'monto' => $l['monto'],
                    'id_turno' => $cab->id_turno, 'id_empresa_negocio' => $cab->id_empresa_negocio,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function anular(Request $request, $id)
    {
        $this->autorizar();
        $request->validate(['motivo' => 'required|string|min:5|max:70'], [], ['motivo' => 'Motivo']);
        $cab = $this->venta($id);

        if ($cab->ccabaj) {
            return response()->json(['success' => false, 'message' => 'La venta ya está anulada.']);
        }
        if ($cab->tdocod !== '13' && ! in_array($cab->tdocod, ['01', '03'], true)) {
            return response()->json(['success' => false, 'message' => 'Las notas de crédito y débito no se anulan aquí.']);
        }
        if ($error = $this->validarTurnoAbierto($cab)) {
            return response()->json(['success' => false, 'message' => $error]);
        }

        // Factura o boleta ya informada a SUNAT: se anula con Comunicación de Baja (la venta se anula cuando SUNAT la acepta)
        if ($cab->tdocod !== '13') {
            try {
                $r = SunatService::paraUsuario(Auth::user())->comunicarBaja((int) $cab->IdCpe_cabecera, trim($request->motivo));
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()]);
            } catch (\Throwable $e) {
                report($e);

                return response()->json(['success' => false, 'message' => 'Error al comunicar la baja: '.$e->getMessage()]);
            }

            return response()->json(['success' => $r['ok'], 'message' => $r['mensaje']]);
        }

        try {
            $devueltos = AnulacionVenta::anular((int) $cab->IdCpe_cabecera, $request->motivo, Auth::id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => 'No se puede anular: '.$e->getMessage()]);
        }

        if ($devueltos === null) {
            return response()->json(['success' => false, 'message' => 'La venta ya fue anulada por otro usuario.']);
        }

        return response()->json(['success' => true, 'message' => 'Venta anulada.'.($devueltos ? " Se devolvió al stock lo vendido ($devueltos movimiento(s) de kardex)." : '')]);
    }

    /** Excel (CSV) con todo lo filtrado */
    public function exportar(Request $request)
    {
        $this->autorizar();
        [$q] = $this->consulta($request);
        $filas = (clone $q)->orderBy('c.fecha_hora')->select('c.*', 't.tdodes', 'u.apeusu as usuario')->get();
        $medios = DB::table('venta_medio_pago as v')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'v.id_med_pag')
            ->whereIn('v.IdCpe_cabecera', $filas->pluck('IdCpe_cabecera'))->get()->groupBy('IdCpe_cabecera');

        return response()->streamDownload(function () use ($filas, $medios) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes
            fputcsv($out, ['Fecha', 'Hora', 'Tipo', 'Serie', 'Número', 'Doc. cliente', 'Cliente', 'Gravado', 'Exonerado', 'IGV', 'Total',
                'Condición', 'Medios de pago', 'Estado SUNAT', 'Anulado', 'Motivo anulación', 'Usuario'], ';');
            foreach ($filas as $f) {
                fputcsv($out, [
                    Carbon::parse($f->ccafem)->format('d/m/Y'), Carbon::parse($f->fecha_hora)->format('H:i'),
                    $f->tdodes, $f->serdoc, $f->numdoc, $f->ccandi, $f->ccanom,
                    number_format($f->ccatvg, 2, '.', ''), number_format($f->ccatexo, 2, '.', ''), number_format($f->ccaigv, 2, '.', ''),
                    number_format($f->ccaitv, 2, '.', ''), $f->estadopago,
                    ($medios[$f->IdCpe_cabecera] ?? collect())->map(fn ($m) => $m->nom_med_pag.' '.number_format($m->monto, 2, '.', ''))->implode(' / '),
                    $f->est_sunat, $f->ccabaj ? 'SÍ' : '', $f->motivo_baja, $f->usuario,
                ], ';');
            }
            fclose($out);
        }, 'ventas_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
