<?php
namespace App\Http\Controllers;

use App\Support\Notas;
use App\Support\Sunat\SunatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/** Notas de crédito y débito electrónicas: listado, emisión sobre una factura/boleta y envío a SUNAT */
class NotaController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja emiten notas de crédito y débito.');
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $notas = DB::table('cpe_cabecera as c')
            ->leftJoin('tipo_nota_credito as nc', fn($j) => $j->on('nc.nccod', '=', 'c.tipnot')->where('c.tdocod', '07'))
            ->leftJoin('tipo_nota_debito as nd', fn($j) => $j->on('nd.ndcod', '=', 'c.tipnot')->where('c.tdocod', '08'))
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'c.IdUsuario')
            ->where('c.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->whereIn('c.tdocod', ['07', '08'])
            ->whereBetween('c.ccafem', [$desde, $hasta])
            ->when($request->filled('tipo'), fn($w) => $w->where('c.tdocod', $request->tipo))
            ->when(trim((string) $request->get('q')) !== '', function ($w) use ($request) {
                $q = trim($request->q);
                $w->where(fn($x) => $x->where('c.ccanom', 'like', "%{$q}%")->orWhere('c.ccandi', 'like', "{$q}%")
                    ->orWhere(DB::raw("CONCAT(c.serdoc, '-', c.numdoc)"), 'like', "%{$q}%")
                    ->orWhere(DB::raw("CONCAT(c.serie_ref, '-', c.num_ref)"), 'like', "%{$q}%"));
            })
            ->orderByDesc('c.IdCpe_cabecera')
            ->select('c.*', DB::raw('COALESCE(nc.ncdes, nd.nddes) as motivo_des'), 'u.apeusu')
            ->paginate(30)->withQueryString();

        return view('empresas.notas.index', compact('notas', 'desde', 'hasta'));
    }

    /** Formulario: busca el comprobante (por id o "F001-123") y muestra sus líneas con lo que queda por rebajar */
    public function create(Request $request)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;
        $ref = null;
        $error = null;

        if ($request->filled('ref')) {
            $ref = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $request->ref)->where('id_empresa_negocio', $sucursal)->first();
        } elseif ($request->filled('doc')) {
            if (preg_match('/^([A-Z0-9]{4})\s*-\s*0*(\d+)$/', strtoupper(trim($request->doc)), $m)) {
                $ref = DB::table('cpe_cabecera')->where('id_empresa_negocio', $sucursal)->whereIn('tdocod', ['01', '03'])
                    ->where('serdoc', $m[1])->where('numdoc', (int) $m[2])->first();
            }
            $error = $ref ? null : 'No se encontró la factura o boleta ' . strtoupper($request->doc) . ' en esta sucursal.';
        }

        $lineas = collect();
        $usado = ['total' => 0, 'cantidades' => [], 'notas' => 0];
        $notasPrevias = collect();
        if ($ref) {
            try {
                Notas::validarReferencia($ref);
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
            $usado = Notas::usado($ref->IdCpe_cabecera);
            $lineas = DB::table('cpe_detalle')->where('IdCpe_cabecera', $ref->IdCpe_cabecera)->orderBy('IdCpe_detalle')->get()
                ->map(fn($l) => [
                    'id' => $l->IdCpe_detalle, 'descripcion' => $l->cdedes, 'cantidad' => (float) $l->cdecan,
                    'precio' => (float) $l->cdepuni, 'total' => (float) $l->cdevve, 'producto' => (bool) $l->IdProducto,
                    'devuelto' => (float) ($usado['cantidades'][$l->IdCpe_detalle] ?? 0), 'lotes' => $l->lotes,
                ]);
            $notasPrevias = DB::table('cpe_cabecera')->where('IdCpe_cabecera_ref', $ref->IdCpe_cabecera)
                ->whereIn('tdocod', ['07', '08'])->orderBy('IdCpe_cabecera')->get();
        }

        $sucursalDatos = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();

        return view('empresas.notas.create', [
            'ref' => $ref, 'error' => $error, 'lineas' => $lineas, 'usado' => $usado, 'notasPrevias' => $notasPrevias,
            'motivosNc' => DB::table('tipo_nota_credito')->whereIn('nccod', Notas::MOTIVOS_NC)->orderBy('nccod')->pluck('ncdes', 'nccod'),
            'motivosNd' => DB::table('tipo_nota_debito')->whereIn('ndcod', Notas::MOTIVOS_ND)->orderBy('ndcod')->pluck('nddes', 'ndcod'),
            'sucursal' => $sucursalDatos,
        ]);
    }

    /** Datos para emitir la nota desde el modal del Panel de ventas (sin salir de la pantalla) */
    public function datos($id)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;
        $ref = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->where('id_empresa_negocio', $sucursal)->first();
        abort_unless($ref, 404);

        $error = null;
        try {
            Notas::validarReferencia($ref);
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }
        $usado = Notas::usado($ref->IdCpe_cabecera);
        $suc = DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->first();
        $letra = $ref->serdoc[0] === 'F' ? 'F' : 'B';
        $proximo = fn($s, $n) => $suc->$s . '-' . str_pad((int) $suc->$n + 1, 8, '0', STR_PAD_LEFT);

        return response()->json([
            'error' => $error,
            'ref' => [
                'id' => $ref->IdCpe_cabecera, 'tipo' => $ref->tdocod === '01' ? 'Factura' : 'Boleta',
                'numero' => $ref->serdoc . '-' . str_pad($ref->numdoc, 8, '0', STR_PAD_LEFT),
                'fecha' => \Carbon\Carbon::parse($ref->ccafem)->format('d/m/Y'), 'cliente' => $ref->ccanom, 'doc' => $ref->ccandi,
                'total' => (float) $ref->ccaitv, 'saldo' => round((float) $ref->ccaitv - $usado['total'], 2), 'est_sunat' => $ref->est_sunat,
            ],
            'tieneNotas' => $usado['notas'] > 0,
            'proximo' => ['07' => $proximo('SerNC' . $letra, 'NumNC' . $letra), '08' => $proximo('SerND' . $letra, 'NumND' . $letra)],
            'lineas' => DB::table('cpe_detalle')->where('IdCpe_cabecera', $ref->IdCpe_cabecera)->orderBy('IdCpe_detalle')->get()
                ->map(fn($l) => [
                    'id' => $l->IdCpe_detalle, 'descripcion' => $l->cdedes, 'cantidad' => (float) $l->cdecan,
                    'precio' => (float) $l->cdepuni, 'producto' => (bool) $l->IdProducto,
                    'devuelto' => (float) ($usado['cantidades'][$l->IdCpe_detalle] ?? 0), 'lotes' => $l->lotes,
                ])->values(),
            'motivos' => [
                '07' => DB::table('tipo_nota_credito')->whereIn('nccod', Notas::MOTIVOS_NC)->orderBy('nccod')->pluck('ncdes', 'nccod'),
                '08' => DB::table('tipo_nota_debito')->whereIn('ndcod', Notas::MOTIVOS_ND)->orderBy('ndcod')->pluck('nddes', 'ndcod'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $d = $request->validate([
            'ref'                   => 'required|integer',
            'tdocod'                => 'required|in:07,08',
            'tipnot'                => 'required|string|size:2',
            'motivo'                => 'required|string|min:5|max:100',
            'items'                 => 'nullable|array|max:200',
            'items.*.IdCpe_detalle' => 'nullable|integer',
            'items.*.descripcion'   => 'nullable|string|max:150',
            'items.*.cantidad'      => 'nullable|numeric|min:0',
            'items.*.precio'        => 'nullable|numeric|min:0',
        ], [], ['motivo' => 'sustento (motivo)', 'tipnot' => 'tipo de nota']);

        try {
            $id = Notas::emitir(Auth::user(), (int) $d['ref'], $d);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()]);
            }
            return back()->withInput()->withErrors(['nota' => $e->getMessage()]);
        }

        $nota = DB::table('cpe_cabecera')->where('IdCpe_cabecera', $id)->first();
        $numero = $nota->serdoc . '-' . str_pad($nota->numdoc, 8, '0', STR_PAD_LEFT);
        $mensaje = ($nota->tdocod === '07' ? 'Nota de crédito ' : 'Nota de débito ') . "{$numero} emitida.";

        // Las notas de facturas se envían en el momento; las de boletas van en el resumen diario
        if ($nota->serdoc[0] === 'F') {
            try {
                $r = SunatService::paraUsuario(Auth::user())->enviarComprobante($id);
                $mensaje .= ' SUNAT: ' . $r['estado'] . ($r['ok'] ? '.' : ' — ' . $r['mensaje'] . ' Puedes reenviarla desde esta lista.');
            } catch (\Throwable $e) {
                $mensaje .= ' No se pudo enviar a SUNAT (' . $e->getMessage() . '). Reenvíala desde esta lista.';
            }
        } else {
            $mensaje .= ' Se enviará a SUNAT en el resumen diario de boletas.';
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje, 'id' => $id,
                'ver' => route('cobros.voucher', $id)]);
        }
        return redirect()->route('notas.index')->with('success', $mensaje);
    }
}
