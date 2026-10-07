<?php
namespace App\Http\Controllers;

use App\Support\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Http};
use Illuminate\Validation\ValidationException;

/**
 * Clientes (tabla cliente) y proveedores (tabla proveedor) de la empresa, en una misma pantalla.
 * Muestra cuánto compró / se le compró, la última operación y el saldo pendiente (por cobrar / por pagar).
 */
class ContactoController extends Controller
{
    private const DOCS = ['1' => 'DNI', '6' => 'RUC', '4' => 'C. EXTRANJERÍA', '7' => 'PASAPORTE', '0' => 'OTRO'];

    private function ruc(): string
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'No tienes permiso para ver los contactos.');
        return (string) Auth::user()->IdEmpresa;
    }

    /** Consulta base con el resumen de operaciones de cada contacto */
    private function consulta(string $tipo, string $ruc, Request $request)
    {
        $q = trim((string) $request->get('q'));
        $estado = $request->get('estado', 'activos');

        if ($tipo === 'clientes') {
            $ventas = DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->whereNull('ccabaj')->where('ccandi', '!=', '00000000')
                ->groupBy('ccandi')->select('ccandi', DB::raw("SUM(tdocod NOT IN ('07','08')) as operaciones"),
                    DB::raw("SUM(CASE WHEN tdocod = '07' THEN -ccaitv ELSE ccaitv END) as total"), DB::raw('MAX(ccafem) as ultima'));
            $saldos = DB::table('cuentas_cobrar as cc')->join('cpe_cabecera as c', 'c.IdCpe_cabecera', '=', 'cc.IdCpe_cabecera')
                ->where('cc.IdEmpresa', $ruc)->whereIn('cc.estado_cob', ['PENDIENTE', 'PARCIAL'])->groupBy('c.ccandi')
                ->select('c.ccandi', DB::raw('SUM(cc.saldo) as saldo'));
            return DB::table('cliente as x')->where('x.rucemp', $ruc)->where('x.clinum', '!=', '00000000')
                ->leftJoinSub($ventas, 'v', 'v.ccandi', '=', 'x.clinum')->leftJoinSub($saldos, 's', 's.ccandi', '=', 'x.clinum')
                ->when($estado === 'activos', fn($w) => $w->where('x.cliest', 'Activo'))->when($estado === 'inactivos', fn($w) => $w->where('x.cliest', '!=', 'Activo'))
                ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('x.clinom', 'like', "%$q%")->orWhere('x.clinum', 'like', "$q%")->orWhere('x.telefono', 'like', "%$q%")))
                ->select('x.clicod as id', 'x.tdicod', 'x.clinum as doc', 'x.clinom as nombre', 'x.clidir as direccion', 'x.clicor as correo', 'x.telefono',
                    DB::raw("'' as contacto"), DB::raw("x.cliest = 'Activo' as activo"), 'x.mensual', 'x.comprobante', 'x.monto',
                    DB::raw('COALESCE(v.operaciones, 0) as operaciones'), DB::raw('COALESCE(v.total, 0) as total'), 'v.ultima', DB::raw('COALESCE(s.saldo, 0) as saldo'));
        }

        $tc = "CASE WHEN mon_id = 'USD' THEN tip_cam ELSE 1 END";
        $compras = DB::table('compras_cabecera')->where('IdEmpresa', $ruc)->where('est_compra', 'Registrado')->groupBy('prov_id')
            ->select('prov_id', DB::raw('COUNT(*) as operaciones'), DB::raw("SUM(total_com * $tc) as total"), DB::raw('MAX(com_fec) as ultima'),
                DB::raw("SUM(saldofactura * $tc) as saldo"));
        return DB::table('proveedor as x')->where('x.IdEmpresa', $ruc)->leftJoinSub($compras, 'v', 'v.prov_id', '=', 'x.prov_id')
            ->when($estado === 'activos', fn($w) => $w->where('x.prov_est', '1'))->when($estado === 'inactivos', fn($w) => $w->where('x.prov_est', '!=', '1'))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('x.prov_raz', 'like', "%$q%")->orWhere('x.prov_ruc', 'like', "$q%")->orWhere('x.prov_con', 'like', "%$q%")))
            ->select('x.prov_id as id', 'x.tdicod', 'x.prov_ruc as doc', 'x.prov_raz as nombre', 'x.prov_dir as direccion', 'x.prov_cor as correo',
                'x.prov_num_con as telefono', 'x.prov_con as contacto', DB::raw("x.prov_est = '1' as activo"),
                DB::raw('COALESCE(v.operaciones, 0) as operaciones'), DB::raw('COALESCE(v.total, 0) as total'), 'v.ultima', DB::raw('COALESCE(v.saldo, 0) as saldo'));
    }

    public function index(Request $request, string $tipo)
    {
        $ruc = $this->ruc();
        $orden = in_array($request->get('orden'), ['nombre', 'total', 'ultima', 'saldo'], true) ? $request->get('orden') : 'nombre';
        $base = $this->consulta($tipo, $ruc, $request)->orderBy($orden, $orden === 'nombre' ? 'asc' : 'desc');

        if ($request->get('excel')) {
            $filas = $base->get()->map(fn($c) => [self::DOCS[$c->tdicod] ?? '', (string) $c->doc, $c->nombre, $c->direccion === '--' ? '' : $c->direccion,
                $c->telefono, $c->correo, $c->contacto, (int) $c->operaciones, (float) $c->total, (float) $c->saldo,
                $c->ultima ? date('d/m/Y', strtotime($c->ultima)) : '', $c->activo ? 'ACTIVO' : 'INACTIVO'])->all();
            $ruta = (new Excel())->hoja(ucfirst($tipo), ['Tipo doc.', 'Número', 'Nombre / razón social', 'Dirección', 'Teléfono', 'Correo', 'Contacto',
                $tipo === 'clientes' ? 'Compras' : 'Compras', 'Total S/', $tipo === 'clientes' ? 'Por cobrar S/' : 'Por pagar S/', 'Última operación', 'Estado'], $filas)->guardar();
            return response()->download($ruta, "{$tipo}_" . now()->format('Ymd') . '.xlsx')->deleteFileAfterSend();
        }

        $resumen = DB::query()->fromSub($this->consulta($tipo, $ruc, $request->duplicate(['estado' => 'todos', 'q' => ''])), 't')
            ->selectRaw('COUNT(*) as n, SUM(activo) as activos, SUM(operaciones > 0) as con_operaciones, SUM(saldo) as saldo')->first();

        return view('empresas.contactos.index', [
            'tipo' => $tipo, 'contactos' => $base->paginate(25)->withQueryString(), 'resumen' => $resumen, 'docs' => self::DOCS,
            'q' => $request->get('q', ''), 'estado' => $request->get('estado', 'activos'), 'orden' => $orden,
        ]);
    }

    private function validar(Request $request, string $tipo, string $ruc, ?int $id): array
    {
        $d = $request->validate([
            'tdicod' => 'required|in:1,6,4,7,0', 'doc' => 'required|string|max:15', 'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:200', 'telefono' => 'nullable|string|max:20', 'correo' => 'nullable|email|max:50',
            'contacto' => 'nullable|string|max:100', 'activo' => 'boolean',
            'mensual' => 'boolean', 'comprobante' => 'nullable|in:01,03,13', 'monto' => 'nullable|numeric|min:0|max:999999',
        ], [], ['tdicod' => 'tipo de documento', 'doc' => 'número de documento', 'nombre' => 'nombre o razón social']);
        $d['doc'] = preg_replace('/\s+/', '', $d['doc']);
        if ($d['tdicod'] === '1' && !preg_match('/^\d{8}$/', $d['doc'])) {
            throw ValidationException::withMessages(['doc' => 'El DNI tiene 8 dígitos.']);
        }
        if ($d['tdicod'] === '6' && !preg_match('/^(10|15|17|20)\d{9}$/', $d['doc'])) {
            throw ValidationException::withMessages(['doc' => 'El RUC tiene 11 dígitos y empieza con 10, 15, 17 o 20.']);
        }
        if ($d['doc'] === '00000000') {
            throw ValidationException::withMessages(['doc' => 'Ese número está reservado para "venta al portador".']);
        }
        $repetido = $tipo === 'clientes'
            ? DB::table('cliente')->where('rucemp', $ruc)->where('clinum', $d['doc'])->when($id, fn($w) => $w->where('clicod', '!=', $id))->exists()
            : DB::table('proveedor')->where('IdEmpresa', $ruc)->where('prov_ruc', $d['doc'])->when($id, fn($w) => $w->where('prov_id', '!=', $id))->exists();
        if ($repetido) {
            throw ValidationException::withMessages(['doc' => "Ya existe un " . ($tipo === 'clientes' ? 'cliente' : 'proveedor') . " con el documento {$d['doc']}."]);
        }
        return $d;
    }

    public function guardar(Request $request, string $tipo, ?int $id = null)
    {
        $ruc = $this->ruc();
        $d = $this->validar($request, $tipo, $ruc, $id);
        $nombre = mb_strtoupper(trim($d['nombre']));
        $dir = mb_strtoupper(trim((string) ($d['direccion'] ?? ''))) ?: '--';
        $activo = $request->boolean('activo', true);

        if ($tipo === 'clientes') {
            $datos = ['tdicod' => $d['tdicod'], 'clinum' => $d['doc'], 'clinom' => $nombre, 'clidir' => $dir, 'telefono' => $d['telefono'] ?? null,
                'clicor' => $d['correo'] ?? null, 'cliest' => $activo ? 'Activo' : 'Inactivo',
                // Facturación mensual (Venta Masiva)
                'mensual' => $request->boolean('mensual'), 'comprobante' => $d['comprobante'] ?? null, 'monto' => round((float) ($d['monto'] ?? 0), 2)];
            if ($datos['mensual'] && $datos['comprobante'] === '01' && $d['tdicod'] !== '6') {
                throw ValidationException::withMessages(['comprobante' => 'La factura mensual necesita un cliente con RUC.']);
            }
            if ($id) {
                abort_unless(DB::table('cliente')->where('clicod', $id)->where('rucemp', $ruc)->exists(), 404);
                DB::table('cliente')->where('clicod', $id)->update($datos);
            } else {
                DB::table('cliente')->insert($datos + ['rucemp' => $ruc]);
            }
        } else {
            $datos = ['tdicod' => $d['tdicod'], 'prov_ruc' => $d['doc'], 'prov_raz' => $nombre, 'prov_dir' => $dir, 'prov_num_con' => $d['telefono'] ?? null,
                'prov_cor' => $d['correo'] ?? null, 'prov_con' => mb_strtoupper(trim((string) ($d['contacto'] ?? ''))) ?: null, 'prov_est' => $activo ? '1' : '0'];
            if ($id) {
                abort_unless(DB::table('proveedor')->where('prov_id', $id)->where('IdEmpresa', $ruc)->exists(), 404);
                DB::table('proveedor')->where('prov_id', $id)->update($datos);
            } else {
                DB::table('proveedor')->insert($datos + ['IdEmpresa' => $ruc, 'id_empresa_negocio' => Auth::user()->id_empresa_negocio]);
            }
        }
        return response()->json(['success' => true]);
    }

    /** Solo se elimina si no tiene operaciones; si tiene, se desactiva */
    public function eliminar(string $tipo, int $id)
    {
        $ruc = $this->ruc();
        if ($tipo === 'clientes') {
            $c = DB::table('cliente')->where('clicod', $id)->where('rucemp', $ruc)->first();
            abort_unless($c, 404);
            if (DB::table('cpe_cabecera')->where('IdEmpresa', $ruc)->where('ccandi', $c->clinum)->exists()) {
                DB::table('cliente')->where('clicod', $id)->update(['cliest' => 'Inactivo']);
                return back()->with('success', 'El cliente tiene ventas registradas: se desactivó en lugar de eliminarlo.');
            }
            DB::table('cliente')->where('clicod', $id)->delete();
        } else {
            $p = DB::table('proveedor')->where('prov_id', $id)->where('IdEmpresa', $ruc)->first();
            abort_unless($p, 404);
            if (DB::table('compras_cabecera')->where('prov_id', $id)->exists() || DB::table('gastos')->where('IdEmpresa', $ruc)->where('prov_doc', $p->prov_ruc)->exists()) {
                DB::table('proveedor')->where('prov_id', $id)->update(['prov_est' => '0']);
                return back()->with('success', 'El proveedor tiene compras o gastos registrados: se desactivó en lugar de eliminarlo.');
            }
            DB::table('proveedor')->where('prov_id', $id)->delete();
        }
        return back()->with('success', 'Eliminado.');
    }

    /** Últimas operaciones del contacto (panel de detalle) */
    public function historial(string $tipo, int $id)
    {
        $ruc = $this->ruc();
        if ($tipo === 'clientes') {
            $c = DB::table('cliente')->where('clicod', $id)->where('rucemp', $ruc)->first();
            abort_unless($c, 404);
            $ops = DB::table('cpe_cabecera as c')->leftJoin('cuentas_cobrar as cc', 'cc.IdCpe_cabecera', '=', 'c.IdCpe_cabecera')
                ->where('c.IdEmpresa', $ruc)->where('c.ccandi', $c->clinum)->orderByDesc('c.fecha_hora')->limit(25)
                ->get(['c.IdCpe_cabecera as id', 'c.ccafem as fecha', 'c.tdocod', 'c.serdoc', 'c.numdoc', 'c.ccaitv as total', 'c.estadopago', 'c.ccabaj', 'cc.saldo'])
                ->map(fn($o) => ['fecha' => $o->fecha, 'doc' => ['01' => 'Factura', '03' => 'Boleta', '07' => 'N. crédito', '08' => 'N. débito', '13' => 'N. venta'][$o->tdocod] ?? $o->tdocod,
                    'numero' => $o->serdoc . '-' . $o->numdoc, 'total' => ($o->tdocod === '07' ? -1 : 1) * (float) $o->total,
                    'detalle' => $o->ccabaj ? 'ANULADO' : ($o->estadopago === 'CREDITO' ? 'Crédito · saldo S/ ' . number_format((float) $o->saldo, 2) : 'Contado'),
                    'url' => route('cobros.voucher', $o->id)]);
        } else {
            $p = DB::table('proveedor')->where('prov_id', $id)->where('IdEmpresa', $ruc)->first();
            abort_unless($p, 404);
            $ops = DB::table('compras_cabecera')->where('prov_id', $id)->orderByDesc('com_fec')->limit(25)->get()
                ->map(fn($o) => ['fecha' => $o->com_fec, 'doc' => 'Compra', 'numero' => $o->com_doc_ser . '-' . $o->com_doc_num,
                    'total' => (float) $o->total_com * ($o->mon_id === 'USD' ? (float) $o->tip_cam : 1),
                    'detalle' => $o->est_compra !== 'Registrado' ? 'ANULADA' : ((float) $o->saldofactura > 0 ? 'Por pagar S/ ' . number_format((float) $o->saldofactura, 2) : 'Pagada'),
                    'url' => route('compras.edit', $o->com_cab_id)])
                ->concat(DB::table('gastos')->where('IdEmpresa', $ruc)->where('prov_doc', $p->prov_ruc)->orderByDesc('fecha')->limit(10)->get()
                    ->map(fn($g) => ['fecha' => $g->fecha, 'doc' => 'Gasto', 'numero' => trim(($g->serie ? $g->serie . '-' : '') . $g->numero, '-'),
                        'total' => (float) $g->total, 'detalle' => $g->estado === 'Registrado' ? ($g->descripcion ?? '') : 'ANULADO', 'url' => route('gastos.index', ['mes' => substr($g->fecha, 0, 7)])]))
                ->sortByDesc('fecha')->values();
        }
        return response()->json($ops);
    }

    /** Datos de SUNAT por RUC (para autocompletar el formulario) */
    public function sunat(string $doc)
    {
        $this->ruc();
        if ($r = \App\Support\ConsultaPeru::ruc($doc)) {
            return response()->json(['nombre' => $r['nombre'], 'direccion' => $r['direccion'], 'estado' => $r['estado'], 'condicion' => $r['condicion']]);
        }
        if ($r = \App\Support\ConsultaPeru::dni($doc)) {
            return response()->json(['nombre' => $r['nombre'], 'direccion' => null, 'estado' => null, 'condicion' => null]);
        }
        return response()->json(['error' => preg_match('/^\d{8}$|^\d{11}$/', $doc) ? 'No se encontró el documento. Escribe los datos.' : 'Escribe un DNI (8 dígitos) o RUC (11).']);
    }
}
