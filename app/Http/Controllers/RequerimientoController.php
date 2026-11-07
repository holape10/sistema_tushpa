<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Support\Bitacora;
use App\Support\Requerimientos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Requerimientos del día: cocina o almacén pide lo que falta; el administrador (o quien registra compras) lo compra,
 * marca lo comprado y lo registra como compra. Cada sucursal ve solo los suyos.
 */
class RequerimientoController extends Controller
{
    public function index(Request $request): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $estado = array_key_exists((string) $request->query('estado'), Requerimientos::ESTADOS) ? (string) $request->query('estado') : null;

        return view('empresas.requerimientos.index', [
            'requerimientos' => DB::table('requerimientos as r')->leftJoin('users as u', 'u.IdUsuario', '=', 'r.IdUsuario')
                ->leftJoin('almacenes as a', 'a.id_almacen', '=', 'r.id_almacen')->where('r.id_empresa_negocio', $sucursal)
                ->when($estado, fn ($q) => $q->where('r.estado', $estado))
                ->select('r.*', 'u.apeusu as pidio', 'a.descripcion as almacen',
                    DB::raw('(SELECT COUNT(*) FROM requerimientos_detalle d WHERE d.requerimiento_id = r.id) as lineas'))
                ->orderByRaw("r.estado = 'PENDIENTE' DESC")->orderByDesc('r.id')->paginate(25)->withQueryString(),
            'estado' => $estado,
            'conteo' => DB::table('requerimientos')->where('id_empresa_negocio', $sucursal)->groupBy('estado')->selectRaw('estado, COUNT(*) as n')->pluck('n', 'estado'),
            'atiende' => Requerimientos::atiende(Auth::user()),
        ]);
    }

    /** Lo que hay, lo que falta y lo que se acabaría: de ahí se arma el pedido */
    public function create(Request $request): View
    {
        $this->autorizar();
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->orderBy('descripcion')->get(['id_almacen', 'descripcion']);
        abort_if($almacenes->isEmpty(), 404, 'La sucursal no tiene almacén.');
        $almacen = $almacenes->firstWhere('id_almacen', (int) $request->query('almacen')) ?? $almacenes->first();

        return view('empresas.requerimientos.create', [
            'almacenes' => $almacenes, 'almacen' => $almacen,
            'productos' => Requerimientos::sugerencias($sucursal, (int) $almacen->id_almacen),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'id_almacen' => 'required|integer', 'fecha_para' => 'required|date|after_or_equal:today',
            'observaciones' => 'nullable|string|max:500',
            'items' => 'required|array|max:500', 'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'nullable|numeric|min:0|max:9999999', 'items.*.nota' => 'nullable|string|max:150',
        ], ['items.required' => 'Pon la cantidad de al menos un producto.', 'fecha_para.after_or_equal' => 'El pedido es para hoy o un día que viene.'],
            ['fecha_para' => 'fecha']);
        try {
            $id = Requerimientos::guardar(Auth::user(), $d);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $r = DB::table('requerimientos')->where('id', $id)->first();
        Bitacora::registrar('compra.requerimiento', 'Pidió el requerimiento '.Requerimientos::codigo($r).' para el '.date('d/m/Y', strtotime($r->fecha_para)),
            null, ['referencia' => ['requerimiento', $id]]);

        return redirect()->route('requerimientos.show', $id)->with('success', Requerimientos::codigo($r).' enviado: el administrador ya lo ve en su inicio.');
    }

    public function show(int $id): View
    {
        $this->autorizar();
        $r = $this->requerimiento($id);

        return view('empresas.requerimientos.show', $this->datos($r) + ['atiende' => Requerimientos::atiende(Auth::user())]);
    }

    public function imprimir(int $id): View
    {
        $this->autorizar();
        $r = $this->requerimiento($id);

        return view('empresas.requerimientos.imprimir', $this->datos($r) + [
            'empresa' => DB::table('empresa')->where('IdEmpresa', Auth::user()->IdEmpresa)->first(['NomEmpresa']),
            'sucursal' => DB::table('empresa_negocios')->where('id_empresa_negocio', $r->id_empresa_negocio)->first(['nombre_comercial', 'direccion']),
        ]);
    }

    /** En el mercado: lo que se compró de cada producto y a qué precio */
    public function marcar(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        abort_unless(Requerimientos::atiende(Auth::user()), 403, 'Solo el administrador o quien registra compras marca lo comprado.');
        $d = $request->validate(['lineas' => 'required|array', 'lineas.*.comprado' => 'nullable|numeric|min:0|max:9999999',
            'lineas.*.precio' => 'nullable|numeric|min:0|max:9999999', 'registrar' => 'nullable|boolean']);
        try {
            Requerimientos::marcar(Auth::user(), $id, $d['lineas']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return ! empty($d['registrar'])
            ? redirect()->route('compras.create', ['requerimiento' => $id])
            : back()->with('success', 'Guardado lo comprado. Cuando termines, regístralo como compra.');
    }

    public function anular(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['motivo' => 'required|string|max:255'], [], ['motivo' => 'motivo']);
        try {
            $r = Requerimientos::anular(Auth::user(), $id, $d['motivo']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.requerimiento_anular', 'Anuló el requerimiento '.Requerimientos::codigo($r).': '.$d['motivo'], null,
            ['referencia' => ['requerimiento', $id]]);

        return redirect()->route('requerimientos.index')->with('success', Requerimientos::codigo($r).' anulado.');
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/requerimientos') || Requerimientos::atiende(Auth::user()),
            403, 'No tienes permiso para los requerimientos.');
    }

    private function requerimiento(int $id): object
    {
        try {
            return Requerimientos::deSucursal(Auth::user(), $id);
        } catch (\RuntimeException) {
            abort(404);
        }
    }

    /** @return array<string, mixed> */
    private function datos(object $r): array
    {
        $usuarios = DB::table('users')->whereIn('IdUsuario', array_filter([$r->IdUsuario, $r->atendido_por]))->pluck('apeusu', 'IdUsuario');

        return [
            'r' => $r, 'codigo' => Requerimientos::codigo($r), 'lineas' => Requerimientos::detalle($r->id),
            'pidio' => $usuarios[$r->IdUsuario] ?? '', 'atendio' => $r->atendido_por ? ($usuarios[$r->atendido_por] ?? '') : null,
            'almacen' => DB::table('almacenes')->where('id_almacen', $r->id_almacen)->value('descripcion'),
            'compra' => $r->com_cab_id ? DB::table('compras_cabecera')->where('com_cab_id', $r->com_cab_id)->first(['com_cab_id', 'com_doc_ser', 'com_doc_num', 'total_com']) : null,
        ];
    }
}
