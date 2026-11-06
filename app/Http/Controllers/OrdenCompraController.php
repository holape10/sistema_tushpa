<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Support\Bitacora;
use App\Support\Buscar;
use App\Support\Compras;
use App\Support\ConsultaPeru;
use App\Support\OrdenesCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Órdenes de compra: las pide quien tiene la opción en su menú, las aprueba el administrador y se vuelven compra */
class OrdenCompraController extends Controller
{
    public function index(Request $request): View
    {
        $this->autorizar();
        $estado = array_key_exists((string) $request->query('estado'), OrdenesCompra::ESTADOS) ? (string) $request->query('estado') : null;
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $q = trim((string) $request->query('q'));

        $ordenes = DB::table('ordenes_compra as o')->leftJoin('users as u', 'u.IdUsuario', '=', 'o.IdUsuario')
            ->where('o.id_empresa_negocio', $sucursal)
            ->when($estado, fn ($w) => $w->where('o.estado', $estado))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('o.prov_nom', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('o.prov_num', 'like', addcslashes($q, '%_\\').'%')->orWhere('o.numero', (int) preg_replace('/\D/', '', $q))))
            ->orderByDesc('o.id')->select('o.*', 'u.apeusu as pidio')->paginate(25)->withQueryString();

        return view('empresas.ordenes_compra.index', [
            'ordenes' => $ordenes, 'estado' => $estado, 'q' => $q,
            'conteo' => DB::table('ordenes_compra')->where('id_empresa_negocio', $sucursal)->groupBy('estado')->selectRaw('estado, COUNT(*) as n')->pluck('n', 'estado'),
        ]);
    }

    public function create(): View
    {
        $this->autorizar();

        return view('empresas.ordenes_compra.form', $this->datos(null));
    }

    public function edit(int $id): View|RedirectResponse
    {
        $this->autorizar();
        $orden = $this->orden($id);
        if ($orden->estado !== 'PENDIENTE') {
            return redirect()->route('ordenes_compra.show', $id)->with('error', 'Solo se edita una orden que aún no se aprueba.');
        }

        return view('empresas.ordenes_compra.form', $this->datos($orden));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->guardar($request, null);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->guardar($request, $id);
    }

    public function show(int $id): View
    {
        $this->autorizar();
        $orden = $this->orden($id);

        return view('empresas.ordenes_compra.show', $this->detalle($orden));
    }

    public function imprimir(int $id): View
    {
        $this->autorizar();
        $orden = $this->orden($id);
        $sucursal = DB::table('empresa_negocios')->where('id_empresa_negocio', $orden->id_empresa_negocio)->first();

        return view('empresas.ordenes_compra.imprimir', $this->detalle($orden) + [
            'empresa' => DB::table('empresa')->where('IdEmpresa', $orden->IdEmpresa)->first(), 'sucursal' => $sucursal,
        ]);
    }

    /** El administrador aprueba o rechaza */
    public function decidir(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['accion' => 'required|in:aprobar,rechazar', 'motivo' => 'nullable|required_if:accion,rechazar|string|max:255'],
            ['motivo.required_if' => 'Escribe por qué se rechaza.']);
        try {
            $orden = OrdenesCompra::decidir(Auth::user(), $id, $d['accion'] === 'aprobar', $d['motivo'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $codigo = OrdenesCompra::codigo($orden);
        Bitacora::registrar('compra.orden_'.($d['accion'] === 'aprobar' ? 'aprobar' : 'rechazar'),
            ($d['accion'] === 'aprobar' ? 'Aprobó' : 'Rechazó')." la orden de compra {$codigo} de {$orden->prov_nom} por ".$this->monto($orden), null,
            ['referencia' => ['orden_compra', $id], 'datos' => array_filter(['motivo' => $d['motivo'] ?? null])]);

        return back()->with('success', $d['accion'] === 'aprobar' ? "✔ {$codigo} aprobada: ya se puede convertir en compra." : "{$codigo} rechazada.");
    }

    public function anular(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['motivo' => 'required|string|min:4|max:255'], [], ['motivo' => 'motivo']);
        try {
            $orden = OrdenesCompra::anular(Auth::user(), $id, $d['motivo']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.orden_anular', 'Anuló la orden de compra '.OrdenesCompra::codigo($orden)." de {$orden->prov_nom}", null,
            ['referencia' => ['orden_compra', $id], 'datos' => ['motivo' => $d['motivo']]]);

        return back()->with('success', 'Orden anulada.');
    }

    /** Buscador de productos que se compran (los que manejan stock) */
    public function productos(Request $request): JsonResponse
    {
        $this->autorizar();
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $consulta = Producto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->where('proest', 'Activo')
            ->whereIn('promocion', Compras::TIPOS_CON_STOCK);
        Buscar::palabras($consulta, $q, ['pronom'], ['procod', 'codigo_barra']);
        $productos = $consulta->orderBy('pronom')->limit(20)->get(['IdProducto', 'procod', 'pronom', 'umecod', 'costo']);
        $stock = DB::table('producto_stock')->whereIn('IdProducto', $productos->pluck('IdProducto'))->groupBy('IdProducto')
            ->selectRaw('IdProducto, SUM(stock) as stock')->pluck('stock', 'IdProducto');

        return response()->json($productos->map(fn ($p) => ['id' => $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom,
            'unidad' => $p->umecod, 'costo' => (float) $p->costo, 'stock' => (float) ($stock[$p->IdProducto] ?? 0)]));
    }

    /** Nombre del proveedor por su RUC/DNI (los ya registrados primero; si no, la consulta de RUC) */
    public function proveedor(string $doc): JsonResponse
    {
        $this->autorizar();
        $doc = preg_replace('/\D/', '', $doc);
        if ($p = DB::table('proveedor')->where('IdEmpresa', Auth::user()->IdEmpresa)->where('prov_ruc', $doc)->first()) {
            return response()->json(['nom' => $p->prov_raz, 'tdicod' => $p->tdicod]);
        }
        $r = strlen($doc) === 11 ? ConsultaPeru::ruc($doc) : (strlen($doc) === 8 ? ConsultaPeru::dni($doc) : null);

        return response()->json($r ? ['nom' => $r['nombre'], 'tdicod' => strlen($doc) === 11 ? '6' : '1'] : ['error' => 'No se encontró. Escribe el nombre del proveedor.']);
    }

    private function guardar(Request $request, ?int $id): JsonResponse
    {
        $this->autorizar();
        $d = $request->validate([
            'fecha' => 'required|date', 'fecha_entrega' => 'nullable|date',
            'prov_tdicod' => 'required|in:1,4,6,7,0', 'prov_num' => 'required|string|max:15', 'prov_nom' => 'required|string|max:255',
            'moneda' => 'required|in:PEN,USD', 'tip_cam' => 'nullable|numeric|min:0|max:100',
            'id_almacen' => 'nullable|integer', 'condiciones' => 'nullable|string|max:255', 'observaciones' => 'nullable|string|max:500',
            'items' => 'required|array|min:1|max:300', 'items.*.id' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.001|max:9999999', 'items.*.costo' => 'required|numeric|min:0|max:9999999',
            'items.*.tip_igv' => 'nullable|in:10,20,30',
        ], ['items.required' => 'Agrega al menos un producto.'], ['prov_num' => 'RUC o DNI del proveedor', 'prov_nom' => 'nombre del proveedor']);
        if (! empty($d['id_almacen']) && ! Almacen::where('id_almacen', $d['id_almacen'])->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->exists()) {
            return response()->json(['estado' => 'error', 'mensaje' => 'Almacén no válido.']);
        }
        try {
            $ordenId = OrdenesCompra::guardar(Auth::user(), $d, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        }
        $orden = DB::table('ordenes_compra')->where('id', $ordenId)->first();
        $codigo = OrdenesCompra::codigo($orden);
        if (! $id) {
            Bitacora::registrar('compra.orden_crear', "Pidió la orden de compra {$codigo} a {$orden->prov_nom} por ".$this->monto($orden)
                .($orden->estado === 'APROBADA' ? ' (aprobada al crearla)' : ''), null, ['referencia' => ['orden_compra', $ordenId]]);
        }
        session()->flash('success', $orden->estado === 'APROBADA' ? "{$codigo} guardada y aprobada." : "{$codigo} guardada: espera la aprobación del administrador.");

        return response()->json(['estado' => 'success', 'redirect' => route('ordenes_compra.show', $ordenId)]);
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin() || Auth::user()->tieneModulo('/ordenes-compra'), 403, 'No tienes permiso para las órdenes de compra.');
    }

    private function orden(int $id): object
    {
        $orden = DB::table('ordenes_compra')->where('id', $id)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
        abort_unless($orden, 404);

        return $orden;
    }

    private function detalle(object $orden): array
    {
        return [
            'orden' => $orden, 'codigo' => OrdenesCompra::codigo($orden),
            'items' => DB::table('ordenes_compra_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->where('d.orden_id', $orden->id)
                ->orderBy('d.id')->get(['d.*', 'p.pronom', 'p.procod', 'p.umecod']),
            'pidio' => DB::table('users')->where('IdUsuario', $orden->IdUsuario)->value('apeusu'),
            'aprobo' => $orden->IdUsuario_aprueba ? DB::table('users')->where('IdUsuario', $orden->IdUsuario_aprueba)->value('apeusu') : null,
            'almacen' => $orden->id_almacen ? DB::table('almacenes')->where('id_almacen', $orden->id_almacen)->value('descripcion') : null,
            'compra' => $orden->com_cab_id ? DB::table('compras_cabecera')->where('com_cab_id', $orden->com_cab_id)->first(['com_cab_id', 'com_doc_ser', 'com_doc_num', 'en_transito', 'est_compra']) : null,
        ];
    }

    private function datos(?object $orden): array
    {
        $sucursal = (int) Auth::user()->id_empresa_negocio;

        return [
            'orden' => $orden ? ['id' => $orden->id, 'codigo' => OrdenesCompra::codigo($orden), 'fecha' => $orden->fecha, 'fecha_entrega' => $orden->fecha_entrega,
                'prov_tdicod' => $orden->prov_tdicod, 'prov_num' => $orden->prov_num, 'prov_nom' => $orden->prov_nom, 'moneda' => $orden->moneda,
                'tip_cam' => $orden->tip_cam, 'id_almacen' => $orden->id_almacen, 'condiciones' => $orden->condiciones, 'observaciones' => $orden->observaciones,
                'items' => DB::table('ordenes_compra_detalle as d')->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')->where('d.orden_id', $orden->id)
                    ->orderBy('d.id')->get(['d.IdProducto as id', 'p.procod as codigo', 'p.pronom as nombre', 'p.umecod as unidad', 'd.cantidad', 'd.costo', 'd.tip_igv'])
                    ->map(fn ($i) => ['id' => (int) $i->id, 'codigo' => $i->codigo, 'nombre' => $i->nombre, 'unidad' => $i->unidad,
                        'cantidad' => (float) $i->cantidad, 'costo' => (float) $i->costo, 'tip_igv' => $i->tip_igv])->all()] : null,
            'almacenes' => Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->get(['id_almacen', 'descripcion']),
            'tipIgvPred' => DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('tip_igv_pred') ?: '10',
        ];
    }

    private function monto(object $orden): string
    {
        return ($orden->moneda === 'USD' ? 'US$ ' : 'S/ ').number_format((float) $orden->total, 2);
    }
}
