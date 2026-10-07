<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto};
use App\Support\Compras;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Http};

class CompraController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdminOCaja(), 403, 'Solo Administrador o Caja pueden registrar compras.');
    }

    public function index(Request $request)
    {
        $this->autorizar();
        $sucursal = Auth::user()->id_empresa_negocio;

        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());
        $q = trim((string) $request->get('q'));
        $estado = $request->get('estado', 'Registrado');

        $base = DB::table('compras_cabecera as c')
            ->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.id_empresa_negocio', $sucursal)
            ->whereBetween('c.com_fec', [$desde, $hasta])
            ->when($estado !== 'todos', fn($w) => $w->where('c.est_compra', $estado))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('p.prov_raz', 'like', "%{$q}%")
                ->orWhere('p.prov_ruc', 'like', "{$q}%")
                ->orWhere(DB::raw("CONCAT(c.com_doc_ser, '-', c.com_doc_num)"), 'like', "%{$q}%")));

        $totales = (clone $base)->where('c.est_compra', 'Registrado')
            ->selectRaw("COUNT(*) as cantidad, SUM(CASE WHEN c.mon_id = 'USD' THEN c.total_com * c.tip_cam ELSE c.total_com END) as total, SUM(c.saldofactura) as por_pagar")
            ->first();

        $compras = $base->leftJoin('credito_dias as cd', 'cd.cre_dia_id', '=', 'c.cre_dia_id')
            ->select('c.*', 'p.prov_raz', 'p.prov_ruc', 'cd.cre_dia_nom')
            ->orderByDesc('c.com_fec')->orderByDesc('c.com_cab_id')
            ->paginate(25)->withQueryString();

        return view('empresas.compras.index', [
            'compras' => $compras, 'totales' => $totales, 'desde' => $desde, 'hasta' => $hasta,
            'q' => $q, 'estado' => $estado, 'documentos' => Compras::DOCUMENTOS,
        ]);
    }

    public function create()
    {
        $this->autorizar();
        return view('empresas.compras.form', $this->datosFormulario(null));
    }

    public function edit($id)
    {
        $this->autorizar();
        $cab = DB::table('compras_cabecera')->where('com_cab_id', $id)
            ->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
        abort_unless($cab, 404);

        return view('empresas.compras.form', $this->datosFormulario($cab));
    }

    public function store(Request $request)
    {
        return $this->guardar($request, null);
    }

    public function update(Request $request, $id)
    {
        return $this->guardar($request, (int) $id);
    }

    private function guardar(Request $request, ?int $id)
    {
        $this->autorizar();
        $d = $request->validate([
            'tdocod'          => 'required|in:' . implode(',', array_keys(Compras::DOCUMENTOS)),
            'serie'           => 'required|string|max:4',
            'numero'          => 'required|string|max:8|regex:/^\d+$/',
            'fecEmi'          => 'required|date',
            'fecVen'          => 'nullable|date',
            'fecIng'          => 'required|date',
            'estadopago'      => 'required|integer',
            'moneda'          => 'required|in:PEN,USD',
            'tip_cam'         => 'nullable|numeric|min:0',
            'id_almacen'      => 'required|integer',
            'prov_tdicod'     => 'required|string|size:1',
            'prov_num'        => 'required|string|max:11',
            'prov_nom'        => 'required|string|max:255',
            'prov_dir'        => 'nullable|string|max:255',
            'observaciones'   => 'nullable|string|max:255',
            'actualizar_costo' => 'boolean',
            'items'           => 'required|array|min:1|max:300',
            'items.*.id'      => 'required|integer',
            'items.*.tip_igv' => 'required|in:10,20,30',
            'items.*.cantidad' => 'required|numeric|min:0.01|max:9999999',
            'items.*.costo'   => 'required|numeric|min:0|max:9999999',
            'items.*.flete'   => 'nullable|numeric|min:0',
            'items.*.presentacion' => 'nullable|integer',
            'items.*.lote'    => 'nullable|string|max:50',
            'items.*.vencimiento' => 'nullable|date',
        ], [
            'items.required' => 'Agrega al menos un producto.',
            'numero.regex'   => 'El número del documento solo lleva dígitos.',
        ], [
            'tdocod' => 'documento', 'fecEmi' => 'fecha de emisión', 'fecIng' => 'fecha de ingreso',
            'prov_num' => 'documento del proveedor', 'prov_nom' => 'nombre del proveedor',
        ]);

        try {
            $comId = Compras::guardar(Auth::user(), $d, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo guardar la compra.']);
        }

        $cab = DB::table('compras_cabecera')->where('com_cab_id', $comId)->first(['com_doc_ser', 'com_doc_num']);
        session()->flash('success', ($id ? 'Se actualizó' : 'Se registró') . " la compra {$cab->com_doc_ser}-{$cab->com_doc_num}.");

        return response()->json(['estado' => 'success', 'redirect' => route('compras.index')]);
    }

    public function anular($id)
    {
        $this->autorizar();
        try {
            Compras::anular(Auth::user(), (int) $id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Compra anulada: su mercadería salió del stock.');
    }

    /** Productos que se pueden comprar (simples e insumos), con su último costo */
    public function productos(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $codigo = trim((string) $request->get('codigo'));
        $q = trim((string) $request->get('q'));
        if ($codigo === '' && mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $almacen = (int) $request->get('almacen');
        // Código de barras de una presentación: el producto llega con esa presentación elegida
        $presCodigo = $codigo === '' ? null : DB::table('producto_presentacion as pp')->join('productos as p', 'p.IdProducto', '=', 'pp.IdProducto')
            ->where('p.id_empresa_negocio', $user->id_empresa_negocio)->where('pp.estado', 1)->where('pp.codigo_barra', $codigo)
            ->first(['pp.id_presentacion', 'pp.IdProducto']);
        $productos = Producto::leftJoin('producto_stock', function ($j) use ($almacen) {
                $j->on('productos.IdProducto', '=', 'producto_stock.IdProducto')->where('producto_stock.id_almacen', $almacen);
            })
            ->where('productos.id_empresa_negocio', $user->id_empresa_negocio)
            ->where('productos.proest', 'Activo')
            ->whereIn('productos.promocion', Compras::TIPOS_CON_STOCK)
            ->when($codigo !== '', fn($w) => $w->where(fn($x) => $x->where('productos.procod', $codigo)->orWhere('productos.codigo_barra', $codigo)
                ->when($presCodigo, fn($y) => $y->orWhere('productos.IdProducto', $presCodigo->IdProducto))))
            ->when($codigo === '', function ($w) use ($q) {
                foreach (preg_split('/\s+/', $q) as $p) {
                    $w->where(fn($x) => $x->where('productos.pronom', 'like', "%{$p}%")->orWhere('productos.procod', 'like', "{$p}%")
                        ->orWhere('productos.codigo_barra', $p));
                }
                $w->orderByRaw('productos.procod = ? DESC, productos.pronom LIKE ? DESC', [$q, $q . '%']);
            })
            ->orderBy('productos.pronom')->limit($codigo !== '' ? 1 : 20)
            ->get(['productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod', 'productos.costo', 'productos.promocion', 'productos.control_lote', 'producto_stock.stock']);
        $presentaciones = \App\Support\Precios::presentaciones($productos->pluck('IdProducto'));
        $unidades = \App\Models\UnidadMedida::pluck('umenom', 'umecod');

        return response()->json($productos->map(fn($p) => [
            'id' => $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom,
            'costo' => (float) $p->costo, 'stock' => (float) ($p->stock ?? 0), 'insumo' => (int) $p->promocion === 4,
            'control_lote' => (bool) $p->control_lote, 'unidad' => $unidades[$p->umecod] ?? $p->umecod,
            'presentaciones' => $presentaciones[$p->IdProducto] ?? [],
            'presentacion' => $presCodigo && (int) $presCodigo->IdProducto === (int) $p->IdProducto ? (int) $presCodigo->id_presentacion : null,
        ]));
    }

    /** Sugerencias de proveedores por nombre o RUC */
    public function proveedores(Request $request)
    {
        $this->autorizar();
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        return response()->json(DB::table('proveedor')->where('IdEmpresa', Auth::user()->IdEmpresa)
            ->where(fn($w) => $w->where('prov_raz', 'like', "%{$q}%")->orWhere('prov_ruc', 'like', "{$q}%"))
            ->orderBy('prov_raz')->limit(10)
            ->get(['prov_ruc as num', 'prov_raz as nom', 'prov_dir as dir', 'tdicod']));
    }

    /** Proveedor por documento: primero los registrados; si es RUC, SUNAT */
    public function proveedor($doc)
    {
        $this->autorizar();
        $doc = trim($doc);
        $p = DB::table('proveedor')->where('IdEmpresa', Auth::user()->IdEmpresa)->where('prov_ruc', $doc)->first();
        if ($p) {
            return response()->json(['nom' => $p->prov_raz, 'dir' => $p->prov_dir, 'tdicod' => $p->tdicod]);
        }

        if ($r = \App\Support\ConsultaPeru::ruc($doc)) {
            return response()->json(['nom' => $r['nombre'], 'dir' => $r['direccion'], 'tdicod' => '6']);
        }
        if ($r = \App\Support\ConsultaPeru::dni($doc)) {
            return response()->json(['nom' => $r['nombre'], 'dir' => '', 'tdicod' => '1']);
        }
        return response()->json(['error' => 'No se encontró. Escribe el nombre del proveedor.']);
    }

    private function datosFormulario(?object $cab): array
    {
        $user = Auth::user();
        $compra = null;

        if ($cab) {
            $prov = DB::table('proveedor')->where('prov_id', $cab->prov_id)->first();
            $items = DB::table('compras_detalle as d')->leftJoin('productos as p', 'p.IdProducto', '=', 'd.pro_id')
                ->where('d.com_cab_id', $cab->com_cab_id)->orderBy('d.com_det_id')
                ->get(['d.*', 'p.pronom', 'p.procod', 'p.control_lote']);

            $compra = [
                'id' => $cab->com_cab_id, 'estado' => $cab->est_compra,
                'tdocod' => $cab->tdocod, 'serie' => $cab->com_doc_ser, 'numero' => $cab->com_doc_num,
                'fecEmi' => $cab->com_fec, 'fecVen' => $cab->com_fec_ven, 'fecIng' => $cab->com_fec_ing,
                'estadopago' => $cab->cre_dia_id, 'moneda' => $cab->mon_id, 'tip_cam' => $cab->tip_cam,
                'id_almacen' => $cab->id_almacen, 'observaciones' => $cab->comp_obs,
                'proveedor' => ['tdicod' => $prov->tdicod ?? '6', 'num' => $prov->prov_ruc ?? '', 'nom' => $prov->prov_raz ?? '', 'dir' => $prov->prov_dir ?? ''],
                'items' => $items->map(fn($i) => [
                    'id' => $i->pro_id, 'codigo' => $i->procod, 'nombre' => $i->pronom, 'tip_igv' => $i->tip_igv,
                    'presentacion' => $i->id_presentacion ? (int) $i->id_presentacion : null, 'factor' => (float) ($i->factor ?: 1),
                    'presentacion_nombre' => $i->id_presentacion ? DB::table('producto_presentacion')->where('id_presentacion', $i->id_presentacion)->value('nombre') : null,
                    'cantidad' => (float) $i->cantidad, 'costo' => (float) $i->pre_uni, 'flete' => (float) $i->flete,
                    'lote' => $i->lote, 'vencimiento' => $i->vencimiento, 'control_lote' => (bool) $i->control_lote,
                ])->values(),
            ];
        }

        return [
            'compra' => $compra,
            'documentos' => Compras::DOCUMENTOS,
            'tiposIgv' => Compras::TIPOS_IGV,
            'tipIgvPred' => DB::table('empresa_negocios')->where('id_empresa_negocio', $user->id_empresa_negocio)->value('tip_igv_pred') ?: '10',
            'estadopagos' => DB::table('credito_dias')->where('id_empresa_negocio', $user->id_empresa_negocio)->get(['cre_dia_id', 'cre_dia_nom', 'cre_dia_tip', 'cre_dia_fac']),
            'almacenes' => Almacen::where('id_empresa_negocio', $user->id_empresa_negocio)->orderByDesc('predeterminado')->get(['id_almacen', 'descripcion']),
            'documentosIdentidad' => DB::table('tipo_documento_identidad')->orderBy('orden')->get(['tdicod', 'tdides']),
        ];
    }
}
