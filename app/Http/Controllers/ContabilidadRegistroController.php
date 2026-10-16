<?php

namespace App\Http\Controllers;

use App\Support\ConsultaPeru;
use App\Support\Contabilidad\Contabilidad;
use App\Support\Contabilidad\Registro;
use App\Support\Excel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Registro de Ventas y Registro de Compras contables (solo montos, como el CONCAR): para estudios contables o empresas
 * que no venden con el sistema. Cada comprobante genera su asiento (y el de cobro o pago si es al contado).
 */
class ContabilidadRegistroController extends Controller
{
    private function ruc(string $tipo): string
    {
        $user = Auth::user();
        abort_unless($user->esAdmin() || $user->tieneModulo('/contabilidad/registro/'.$tipo), 403, 'No tienes acceso al registro contable.');
        $ruc = (string) $user->IdEmpresa;
        Contabilidad::asegurar($ruc);

        return $ruc;
    }

    private static function tipo(string $tipo): string
    {
        return $tipo === 'ventas' ? 'VENTA' : 'COMPRA';
    }

    public function index(Request $request, string $tipo)
    {
        $ruc = $this->ruc($tipo);
        $t = self::tipo($tipo);
        $p = preg_replace('/\D/', '', (string) $request->get('periodo', now()->format('Ym')));
        $periodo = strlen($p) === 6 ? $p : now()->format('Ym');
        $q = trim((string) $request->get('q'));

        $registros = DB::table('conta_registros')->where('IdEmpresa', $ruc)->where('tipo', $t)->where('periodo', $periodo)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%{$q}%")->orWhere('doc_num', 'like', "{$q}%")
                ->orWhere('numero', 'like', "%{$q}%")->orWhere('serie', 'like', "%{$q}%")))
            ->orderBy('fecha')->orderBy('id')->get();

        if ($request->get('excel')) {
            return $this->excel($t, $periodo, $registros);
        }

        $signo = fn ($r) => $r->tdocod === '07' ? -1 : 1;
        $tc = fn ($r) => $r->moneda === 'USD' ? (float) $r->tipo_cambio : 1;
        $totales = [
            'base' => round($registros->sum(fn ($r) => $signo($r) * $r->base * $tc($r)), 2),
            'igv' => round($registros->sum(fn ($r) => $signo($r) * $r->igv * $tc($r)), 2),
            'no_gravado' => round($registros->sum(fn ($r) => $signo($r) * ($r->no_gravado + $r->otros) * $tc($r)), 2),
            'total' => round($registros->sum(fn ($r) => $signo($r) * $r->total * $tc($r)), 2),
        ];
        $imputables = Contabilidad::imputables($ruc);
        $cfg = Contabilidad::config($ruc);

        return view('empresas.contabilidad.registro', [
            'tipo' => $tipo, 't' => $t, 'periodo' => $periodo, 'q' => $q, 'registros' => $registros, 'totales' => $totales,
            'cerrado' => Contabilidad::cerrado($ruc, $periodo), 'cfg' => $cfg,
            'cuentas' => $imputables->filter(fn ($n, $c) => collect(Registro::PREFIJOS[$t])->contains(fn ($pre) => str_starts_with((string) $c, $pre))),
            'cajas' => $imputables->filter(fn ($n, $c) => str_starts_with((string) $c, '10')),
            'imputables' => $imputables,
            'asientos' => DB::table('conta_asientos')->where('IdEmpresa', $ruc)->where('ref_tabla', 'conta_registros')
                ->whereIn('ref_id', $registros->pluck('id'))->get(['id', 'ref_id', 'subdiario', 'numero'])->groupBy('ref_id'),
        ]);
    }

    public function guardar(Request $request, string $tipo, ?int $id = null): JsonResponse
    {
        $ruc = $this->ruc($tipo);
        $t = self::tipo($tipo);
        $d = $request->validate([
            'fecha' => 'required|date', 'fecha_vence' => 'nullable|date',
            'tdocod' => ['required', Rule::in(array_keys(Registro::DOCUMENTOS[$t]))],
            'serie' => 'nullable|string|max:20', 'numero' => 'required|string|max:20',
            'doc_tipo' => 'required|in:0,1,4,6,7', 'doc_num' => 'nullable|string|max:15', 'nombre' => 'required|string|max:200',
            'moneda' => 'required|in:PEN,USD', 'tipo_cambio' => 'nullable|numeric|min:0.001|max:99',
            'base' => 'nullable|numeric|min:0', 'igv' => 'nullable|numeric|min:0', 'no_gravado' => 'nullable|numeric|min:0', 'otros' => 'nullable|numeric',
            'cuenta' => 'required|string|max:12', 'condicion' => 'required|in:CONTADO,CREDITO', 'cuenta_pago' => 'nullable|required_if:condicion,CONTADO|string|max:12',
            'glosa' => 'nullable|string|max:200',
            'lineas' => 'nullable|array|min:2|max:50', 'lineas.*.cuenta' => 'required|string|max:12',
            'lineas.*.debe' => 'nullable|numeric|min:0', 'lineas.*.haber' => 'nullable|numeric|min:0', 'lineas.*.glosa' => 'nullable|string|max:150',
        ], ['cuenta_pago.required_if' => 'Elige la caja o banco del pago al contado.'],
            ['tdocod' => 'tipo de documento', 'nombre' => $t === 'VENTA' ? 'cliente' : 'proveedor', 'cuenta' => 'cuenta contable']);

        foreach (['base', 'igv', 'no_gravado', 'otros'] as $c) {
            $d[$c] = round((float) ($d[$c] ?? 0), 2);
        }
        $d['total'] = round($d['base'] + $d['igv'] + $d['no_gravado'] + $d['otros'], 2);
        if ($d['total'] <= 0) {
            return response()->json(['success' => false, 'message' => 'El total del comprobante debe ser mayor a cero.']);
        }
        $d += ['tipo' => $t];
        $d['tipo_cambio'] = $d['moneda'] === 'USD' ? (float) ($d['tipo_cambio'] ?? 0) : 1;
        if ($d['moneda'] === 'USD' && $d['tipo_cambio'] <= 0) {
            return response()->json(['success' => false, 'message' => 'Escribe el tipo de cambio del día.']);
        }
        $d['serie'] = mb_strtoupper(trim((string) ($d['serie'] ?? ''))) ?: null;
        $d['numero'] = ltrim(trim($d['numero']), '0') ?: '0';
        $d['nombre'] = mb_strtoupper(trim($d['nombre']));
        $d['doc_num'] = preg_replace('/\s+/', '', (string) ($d['doc_num'] ?? '')) ?: null;
        $d['cuenta_pago'] = $d['condicion'] === 'CONTADO' ? $d['cuenta_pago'] : null;
        $lineas = $d['lineas'] ?? null;
        unset($d['lineas']);

        try {
            $nuevo = Registro::guardar($ruc, $d, $lineas, $id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'id' => $nuevo, 'message' => ($t === 'VENTA' ? 'Venta' : 'Compra').' registrada con su asiento.',
            'periodo' => Carbon::parse($d['fecha'])->format('Ym')]);
    }

    public function eliminar(string $tipo, int $id)
    {
        $ruc = $this->ruc($tipo);
        try {
            Registro::eliminar($ruc, $id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['registro' => $e->getMessage()]);
        }

        return back()->with('success', 'Comprobante y su asiento eliminados.');
    }

    /** Nombre del RUC o DNI para no escribirlo a mano */
    public function documento(string $tipo, string $doc): JsonResponse
    {
        $this->ruc($tipo);
        $r = strlen($doc) === 11 ? ConsultaPeru::ruc($doc) : (strlen($doc) === 8 ? ConsultaPeru::dni($doc) : null);

        return response()->json($r ? ['ok' => true, 'nombre' => $r['nombre']] : ['ok' => false, 'mensaje' => 'No se encontró. Escribe el nombre.']);
    }

    /** Registro de Ventas (formato 14.1) o de Compras (formato 8.1) simplificado, en soles */
    private function excel(string $t, string $periodo, $registros)
    {
        $filas = $registros->map(function ($r) {
            $tc = $r->moneda === 'USD' ? (float) $r->tipo_cambio : 1;
            $s = $r->tdocod === '07' ? -1 : 1;

            return [Carbon::parse($r->fecha)->format('d/m/Y'), $r->fecha_vence ? Carbon::parse($r->fecha_vence)->format('d/m/Y') : '',
                $r->tdocod, (string) $r->serie, (string) $r->numero, $r->doc_tipo, (string) $r->doc_num, $r->nombre,
                round($s * $r->base * $tc, 2), round($s * $r->igv * $tc, 2), round($s * $r->no_gravado * $tc, 2), round($s * $r->otros * $tc, 2),
                round($s * $r->total * $tc, 2), $r->moneda, $r->moneda === 'USD' ? (float) $r->tipo_cambio : '', (string) $r->cuenta, $r->condicion];
        })->all();
        $titulo = $t === 'VENTA' ? 'Registro de Ventas' : 'Registro de Compras';
        $x = (new Excel)->hoja($titulo.' '.$periodo,
            ['Fecha', 'Vence', 'Tipo', 'Serie', 'Número', 'Tipo doc.', 'RUC / DNI', $t === 'VENTA' ? 'Cliente' : 'Proveedor',
                'Base imponible', 'IGV', 'Exonerado / inafecto', 'Otros', 'Total', 'Moneda', 'T.C.', 'Cuenta', 'Condición'], $filas, [8, 9, 10, 11, 12]);

        return response()->download($x->guardar(), mb_strtolower(str_replace(' ', '_', $titulo))."_{$periodo}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
