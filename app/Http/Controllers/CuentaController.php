<?php
namespace App\Http\Controllers;

use App\Models\{Empresa, EmpresaNegocio, MedioPago};
use App\Support\{Cuentas, Excel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Cuentas por cobrar / por pagar: listado, cobros/pagos, historial, recibos y reportes */
class CuentaController extends Controller
{
    private const TITULOS = [
        'cobrar' => ['titulo' => 'Cuentas por Cobrar', 'persona' => 'Cliente', 'accion' => 'Cobrar', 'pasado' => 'Cobrado'],
        'pagar'  => ['titulo' => 'Cuentas por Pagar', 'persona' => 'Proveedor', 'accion' => 'Pagar', 'pasado' => 'Pagado'],
    ];
    private const TIPOS_DOC = ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. Crédito', '08' => 'N. Débito', '12' => 'Ticket', '13' => 'N. Venta', '00' => 'Otro'];

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja.');
    }

    public function index(Request $request, string $tipo)
    {
        $this->autorizar();
        $suc = Auth::user()->id_empresa_negocio;
        $estado = $request->get('estado', 'pendientes');
        $q = trim((string) $request->get('q'));
        $hoy = now()->toDateString();

        $base = Cuentas::consulta($tipo, $suc);
        $cuentas = (clone $base)
            ->when($estado === 'pendientes', fn($w) => $w->whereIn('estado_cob', ['PENDIENTE', 'PARCIAL']))
            ->when($estado === 'vencidas', fn($w) => $w->whereIn('estado_cob', ['PENDIENTE', 'PARCIAL'])->where('fec_ven', '<', $hoy))
            ->when($estado === 'pagadas', fn($w) => $w->where('estado_cob', 'PAGADO'))
            ->when($estado === 'anuladas', fn($w) => $w->where('estado_cob', 'ANULADO'))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where($tipo === 'cobrar' ? 'd.ccanom' : 'p.prov_raz', 'like', "%{$q}%")
                ->orWhere($tipo === 'cobrar' ? 'd.ccandi' : 'p.prov_ruc', 'like', "{$q}%")
                ->orWhere(DB::raw($tipo === 'cobrar' ? "CONCAT(d.serdoc, '-', d.numdoc)" : "CONCAT(d.com_doc_ser, '-', d.com_doc_num)"), 'like', "%{$q}%")))
            ->orderByRaw('fec_ven IS NULL, fec_ven')
            ->paginate(30)->withQueryString();

        $abiertas = (clone $base)->whereIn('estado_cob', ['PENDIENTE', 'PARCIAL']);
        $t = Cuentas::T[$tipo];
        $kpi = [
            'pendiente' => (float) (clone $abiertas)->sum('saldo'),
            'documentos' => (clone $abiertas)->count(),
            'vencido' => (float) (clone $abiertas)->where('fec_ven', '<', $hoy)->sum('saldo'),
            'por_vencer' => (float) (clone $abiertas)->whereBetween('fec_ven', [$hoy, now()->addDays(7)->toDateString()])->sum('saldo'),
            'mes' => (float) DB::table($t['det'] . ' as p')->join($t['tabla'] . ' as c', 'c.' . $t['pk'], '=', 'p.' . $t['pk'])
                ->where('c.id_empresa_negocio', $suc)->where('p.' . $t['estDet'], 'REGISTRADO')
                ->whereBetween('p.fec_dep', [now()->startOfMonth()->toDateString(), $hoy])->sum('p.abono'),
        ];

        $medios = MedioPago::where('id_empresa_negocio', $suc)->orderByDesc('predeterminado')->get(['id_med_pag', 'nom_med_pag']);

        return view('empresas.cuentas.index', [
            'tipo' => $tipo, 'tx' => self::TITULOS[$tipo], 'cuentas' => $cuentas, 'kpi' => $kpi, 'estado' => $estado, 'q' => $q,
            'medios' => $medios, 'tiposDoc' => self::TIPOS_DOC, 'hoy' => $hoy,
        ]);
    }

    /** Pagos de una cuenta (para el modal de historial) */
    public function pagos(string $tipo, int $id)
    {
        $this->autorizar();
        $cuenta = Cuentas::consulta($tipo, Auth::user()->id_empresa_negocio)->where(($tipo === 'cobrar' ? 'cc.cue_cob_id' : 'cp.cue_pag_id'), $id)->first();
        abort_unless($cuenta, 404);
        return response()->json(['cuenta' => $cuenta, 'pagos' => Cuentas::pagos($tipo, $id)->map(fn($p) => [
            'id' => $p->id, 'recibo' => $p->numero_recibo, 'fecha' => date('d/m/Y', strtotime($p->fec_dep)), 'monto' => (float) $p->abono,
            'saldo' => (float) $p->saldo_detalle, 'estado' => $p->estado, 'oper' => $p->num_oper, 'comentario' => $p->comentario,
            'usuario' => $p->usuario, 'caja' => (bool) $p->mov_caj_id, 'motivo' => $p->motivo_anula,
            'medios' => $p->medios->map(fn($m) => $m->nom_med_pag . ' ' . number_format($m->monto, 2))->implode(' + '),
            'urlRecibo' => route('cuentas.recibo', [$tipo, $p->id]),
        ])->values()]);
    }

    public function pagar(Request $request, string $tipo, int $id)
    {
        $this->autorizar();
        $d = $request->validate([
            'fecha' => 'required|date|before_or_equal:today',
            'medios' => 'required|array|min:1|max:10',
            'medios.*.id' => 'required|integer',
            'medios.*.monto' => 'required|numeric|min:0',
            'num_oper' => 'nullable|string|max:50',
            'comentario' => 'nullable|string|max:200',
            'desde_caja' => 'boolean',
        ], [], ['fecha' => 'fecha del pago']);

        try {
            $detId = Cuentas::registrarPago(Auth::user(), $tipo, $id, $d);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        return response()->json(['estado' => 'ok', 'recibo' => route('cuentas.recibo', [$tipo, $detId])]);
    }

    public function anularPago(Request $request, string $tipo, int $detId)
    {
        $this->autorizar();
        $motivo = $request->validate(['motivo' => 'required|string|min:4|max:100'], [], ['motivo' => 'motivo'])['motivo'];
        try {
            Cuentas::anularPago(Auth::user(), $tipo, $detId, $motivo);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        return response()->json(['estado' => 'ok']);
    }

    /** Recibo imprimible (ticket) */
    public function recibo(string $tipo, int $detId)
    {
        $this->autorizar();
        $t = Cuentas::T[$tipo];
        $pago = DB::table($t['det'])->where($t['detPk'], $detId)->first();
        abort_unless($pago, 404);
        $cuenta = Cuentas::consulta($tipo, Auth::user()->id_empresa_negocio)->where($tipo === 'cobrar' ? 'cc.cue_cob_id' : 'cp.cue_pag_id', $pago->{$t['pk']})->first();
        abort_unless($cuenta, 404);
        $medios = DB::table($t['med'] . ' as m')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'm.med_pag_id')
            ->where('m.' . $t['detPk'], $detId)->get(['mp.nom_med_pag', 'm.monto']);

        return view('empresas.cuentas.recibo', [
            'tipo' => $tipo, 'tx' => self::TITULOS[$tipo], 'pago' => $pago, 'cuenta' => $cuenta, 'medios' => $medios,
            'estado' => $pago->{$t['estDet']}, 'tiposDoc' => self::TIPOS_DOC,
            'empresa' => Empresa::find(Auth::user()->IdEmpresa), 'negocio' => EmpresaNegocio::find(Auth::user()->id_empresa_negocio),
            'usuario' => DB::table('users')->where('IdUsuario', $pago->IdUsuario)->value('apeusu'),
        ]);
    }

    // ---------------- Reportes ----------------

    public function reporte(Request $request, string $tipo)
    {
        $this->autorizar();
        $suc = Auth::user()->id_empresa_negocio;
        $vista = in_array($request->get('vista'), ['antiguedad', 'estado', 'pagos'], true) ? $request->get('vista') : 'antiguedad';
        $hoy = now()->toDateString();
        $datos = [];

        if ($vista === 'antiguedad') {
            // Saldo pendiente por cliente/proveedor según días de vencido
            $abiertas = Cuentas::consulta($tipo, $suc)->whereIn('estado_cob', ['PENDIENTE', 'PARCIAL'])->get();
            $datos['filas'] = $abiertas->groupBy('doc_persona')->map(function ($g) use ($hoy) {
                $f = ['doc' => $g->first()->doc_persona, 'persona' => $g->first()->persona, 'docs' => $g->count(),
                      'por_vencer' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'mas90' => 0, 'total' => 0];
                foreach ($g as $c) {
                    $dias = $c->fec_ven ? (int) floor((strtotime($hoy) - strtotime($c->fec_ven)) / 86400) : 0;
                    $col = $dias <= 0 ? 'por_vencer' : ($dias <= 30 ? 'd30' : ($dias <= 60 ? 'd60' : ($dias <= 90 ? 'd90' : 'mas90')));
                    $f[$col] += (float) $c->saldo;
                    $f['total'] += (float) $c->saldo;
                }
                return $f;
            })->sortByDesc('total')->values();
        }

        if ($vista === 'estado') {
            // Estado de cuenta de un cliente/proveedor: sus documentos al crédito y cada pago
            $datos['personas'] = Cuentas::consulta($tipo, $suc)->where('estado_cob', '!=', 'ANULADO')->get()
                ->unique('doc_persona')->sortBy('persona')->map(fn($c) => ['doc' => $c->doc_persona, 'nombre' => $c->persona])->values();
            $doc = (string) $request->get('persona', $datos['personas'][0]['doc'] ?? '');
            $datos['persona'] = $doc;
            $cuentas = Cuentas::consulta($tipo, $suc)->where('estado_cob', '!=', 'ANULADO')
                ->where($tipo === 'cobrar' ? 'd.ccandi' : 'p.prov_ruc', $doc)->orderBy('fecha')->get();
            $datos['cuentas'] = $cuentas->map(function ($c) use ($tipo) {
                $c->pagos = Cuentas::pagos($tipo, $c->id)->where('estado', 'REGISTRADO')->values();
                return $c;
            });
        }

        if ($vista === 'pagos') {
            $desde = $request->get('desde', now()->startOfMonth()->toDateString());
            $hasta = $request->get('hasta', $hoy);
            $t = Cuentas::T[$tipo];
            $pagos = DB::table($t['det'] . ' as p')->join($t['tabla'] . ' as c', 'c.' . $t['pk'], '=', 'p.' . $t['pk'])
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'p.IdUsuario')
                ->where('c.id_empresa_negocio', $suc)->where('p.' . $t['estDet'], 'REGISTRADO')
                ->whereBetween('p.fec_dep', [$desde, $hasta])->orderBy('p.fec_dep')->orderBy('p.' . $t['detPk'])
                ->get(['p.*', 'p.' . $t['detPk'] . ' as id', 'c.' . $t['doc'] . ' as doc_id', 'u.apeusu as usuario']);
            $medios = DB::table($t['med'] . ' as m')->leftJoin('medios_pagos as mp', 'mp.id_med_pag', '=', 'm.med_pag_id')
                ->whereIn('m.' . $t['detPk'], $pagos->pluck('id'))->get(['m.' . $t['detPk'] . ' as pago', 'mp.nom_med_pag', 'm.monto']);
            $datos += [
                'desde' => $desde, 'hasta' => $hasta,
                'pagos' => $pagos->map(function ($p) use ($tipo, $medios) {
                    $p->doc = Cuentas::documento($tipo, $p->doc_id);
                    $p->medios = $medios->where('pago', $p->id)->map(fn($m) => $m->nom_med_pag . ' ' . number_format($m->monto, 2))->implode(' + ');
                    return $p;
                }),
                'porMedio' => $medios->groupBy('nom_med_pag')->map(fn($g) => round($g->sum('monto'), 2))->sortDesc(),
            ];
        }

        if ($request->boolean('excel')) {
            return $this->excelReporte($tipo, $vista, $datos);
        }

        return view('empresas.cuentas.reporte', ['tipo' => $tipo, 'tx' => self::TITULOS[$tipo], 'vista' => $vista, 'd' => $datos, 'tiposDoc' => self::TIPOS_DOC]);
    }

    private function excelReporte(string $tipo, string $vista, array $d)
    {
        $tx = self::TITULOS[$tipo];
        $x = new Excel();
        if ($vista === 'antiguedad') {
            $x->hoja('Antigüedad de saldos', ['Doc.', $tx['persona'], 'Documentos', 'Por vencer', '1-30 días', '31-60 días', '61-90 días', '+90 días', 'Total'],
                $d['filas']->map(fn($f) => [$f['doc'], $f['persona'], $f['docs'], $f['por_vencer'], $f['d30'], $f['d60'], $f['d90'], $f['mas90'], $f['total']])->all());
        } elseif ($vista === 'estado') {
            $filas = [];
            foreach ($d['cuentas'] as $c) {
                $filas[] = [date('d/m/Y', strtotime($c->fecha)), (self::TIPOS_DOC[$c->tdocod] ?? $c->tdocod) . ' ' . $c->serie . '-' . $c->numero,
                    $c->fec_ven ? date('d/m/Y', strtotime($c->fec_ven)) : '', (float) $c->total, null, (float) $c->saldo, $c->estado_cob];
                foreach ($c->pagos as $p) {
                    $filas[] = [date('d/m/Y', strtotime($p->fec_dep)), '   ' . $p->numero_recibo . ($p->num_oper ? ' · Op. ' . $p->num_oper : ''), '', null, (float) $p->abono, (float) $p->saldo_detalle, ''];
                }
            }
            $x->hoja('Estado de cuenta', ['Fecha', 'Documento / pago', 'Vence', 'Cargo', $tx['pasado'], 'Saldo', 'Estado'], $filas);
        } else {
            $x->hoja($tx['pasado'] . 's', ['Fecha', 'Recibo', 'Documento', $tx['persona'], 'Monto', 'Medios', 'N° operación', 'Usuario'],
                $d['pagos']->map(fn($p) => [date('d/m/Y', strtotime($p->fec_dep)), $p->numero_recibo, $p->doc['numero'], $p->doc['nombre'],
                    (float) $p->abono, $p->medios, $p->num_oper, $p->usuario])->all());
        }
        $ruta = $x->guardar();
        return response()->download($ruta, 'Cuentas_' . $tipo . '_' . $vista . '_' . now()->format('Ymd') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }
}
