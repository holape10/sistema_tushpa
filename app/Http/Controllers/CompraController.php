<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Support\Acciones;
use App\Support\Asistente\LecturaComprobante;
use App\Support\Bitacora;
use App\Support\Compras;
use App\Support\ConsultaPeru;
use App\Support\Contabilidad\Contabilidad;
use App\Support\MesesCerrados;
use App\Support\OrdenesCompra;
use App\Support\Precios;
use App\Support\Recetas;
use App\Support\Requerimientos;
use App\Support\Rubros;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            ->when($estado === 'transito', fn ($w) => $w->where('c.est_compra', 'Registrado')->where('c.en_transito', true))
            ->when(! in_array($estado, ['todos', 'transito'], true), fn ($w) => $w->where('c.est_compra', $estado))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('p.prov_raz', 'like', "%{$q}%")
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
            'mesesCerrados' => MesesCerrados::lista((string) Auth::user()->IdEmpresa),
            'enTransito' => DB::table('compras_cabecera')->where('id_empresa_negocio', $sucursal)->where('est_compra', 'Registrado')
                ->where('en_transito', true)->count(),
        ]);
    }

    /** Llegó la mercadería que estaba en tránsito: entra al almacén con la fecha en que se recibió */
    public function recibir(Request $request, int $id): RedirectResponse
    {
        $this->autorizar();
        $d = $request->validate(['fecha' => 'required|date'], [], ['fecha' => 'fecha de recepción']);
        $foto = self::fotoCompra($id);
        try {
            Compras::recibir(Auth::user(), $id, $d['fecha']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.recibir', 'Recibió en el almacén la mercadería de la compra '.($foto['documento'] ?? $id).' (estaba en tránsito)', null,
            ['referencia' => ['compra', $id], 'datos' => ['fecha' => $d['fecha'], 'productos' => $foto['productos'] ?? []]]);

        return back()->with('success', 'Mercadería recibida: ya está en el stock del almacén.');
    }

    /** Cierra o reabre un mes (el mismo cierre del Libro Diario): cerrado, sus compras y gastos ya no se tocan */
    public function mes(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador cierra y reabre meses.');
        $d = $request->validate(['mes' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'accion' => 'required|in:cerrar,abrir'],
            ['mes.regex' => 'Elige el mes.'], ['mes' => 'mes']);
        $ruc = (string) Auth::user()->IdEmpresa;
        $periodo = str_replace('-', '', $d['mes']);
        $nombre = Contabilidad::nombrePeriodo($periodo);
        if ($d['accion'] === 'cerrar') {
            if ($periodo > now()->format('Ym')) {
                return back()->with('error', 'No se cierra un mes que todavía no llega.');
            }
            MesesCerrados::cerrar($ruc, $periodo, Auth::id());
            Bitacora::registrar('compra.mes_cerrar', "Cerró el mes {$nombre}: sus compras y gastos ya no se pueden cambiar", null,
                ['referencia' => ['periodo', $periodo], 'empresa' => $ruc]);

            return back()->with('success', "🔒 {$nombre} cerrado: sus compras y gastos ya no se registran, editan ni anulan.");
        }
        if (MesesCerrados::abrir($ruc, $periodo)) {
            // Reabrir un mes declarado permite cambiarlo: siempre se revisa
            Bitacora::registrar('compra.mes_abrir', "Reabrió el mes {$nombre} (estaba cerrado)", null,
                ['referencia' => ['periodo', $periodo], 'empresa' => $ruc, 'alerta' => true]);
        }

        return back()->with('success', "🔓 {$nombre} abierto otra vez. Ciérralo cuando termines de corregir.");
    }

    /** Consulta en SUNAT si el comprobante del proveedor es válido (apiperu.dev) y guarda lo que respondió */
    public function consultarSunat(int $id)
    {
        $this->autorizar();
        $c = DB::table('compras_cabecera as c')->leftJoin('proveedor as p', 'p.prov_id', '=', 'c.prov_id')
            ->where('c.com_cab_id', $id)->where('c.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->first(['c.com_cab_id', 'c.tdocod', 'c.com_doc_ser', 'c.com_doc_num', 'c.com_fec', 'c.total_com', 'c.prov_num', 'p.prov_ruc']);
        abort_unless($c, 404);
        $ruc = preg_match('/^\d{11}$/', (string) $c->prov_ruc) ? $c->prov_ruc : $c->prov_num;
        if (! in_array($c->tdocod, ['01', '03', '07', '08'], true) || ! preg_match('/^\d{11}$/', (string) $ruc)) {
            return response()->json(['ok' => false, 'estado' => null, 'color' => 'slate', 'mensaje' => 'Solo se consultan facturas, boletas y notas electrónicas de un proveedor con RUC.']);
        }
        $r = ConsultaPeru::validarCpe($ruc, $c->tdocod, (string) $c->com_doc_ser, (string) $c->com_doc_num, (string) $c->com_fec, (float) $c->total_com);
        if ($r['ok']) {
            DB::table('compras_cabecera')->where('com_cab_id', $id)->update(['sunat_estado' => mb_substr($r['estado'], 0, 50), 'sunat_consultado' => now()]);
        }

        return response()->json($r);
    }

    public function create(Request $request)
    {
        $this->autorizar();
        $desdeOrden = null;
        if ($ordenId = (int) $request->query('orden')) {
            $orden = DB::table('ordenes_compra')->where('id', $ordenId)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->first();
            abort_unless($orden, 404);
            if ($orden->estado !== 'APROBADA') {
                return redirect()->route('ordenes_compra.show', $orden->id)->with('error', 'Solo una orden aprobada se convierte en compra.');
            }
            $desdeOrden = OrdenesCompra::paraFormulario($orden);
        } elseif ($request->query('lectura') && session()->has('compra_lectura')) {
            // Lo leído de la foto, el PDF o el XML del comprobante
            $desdeOrden = session('compra_lectura');
        } elseif ($requerimientoId = (int) $request->query('requerimiento')) {
            try {
                $comprobante = in_array($request->query('comprobante'), ['01', '03', '12', '00'], true) ? $request->query('comprobante') : '12';
                $desdeOrden = Requerimientos::paraFormulario(Requerimientos::paraComprar(Auth::user(), $requerimientoId), $comprobante);
            } catch (\RuntimeException $e) {
                return redirect()->route('requerimientos.index')->with('error', $e->getMessage());
            }
        }

        return view('empresas.compras.form', $this->datosFormulario(null) + ['desdeOrden' => $desdeOrden]);
    }

    /** Foto, PDF o XML del comprobante: se lee y se abre el formulario de compra ya lleno para revisarlo */
    public function leer(Request $request)
    {
        $this->autorizar();
        $request->validate(['archivo' => 'required|file|max:12288|extensions:'.LecturaComprobante::EXTENSIONES],
            ['archivo.extensions' => 'Sube una foto (JPG, PNG), un PDF o el XML de la factura.', 'archivo.max' => 'El archivo pesa más de 12 MB.'], ['archivo' => 'comprobante']);
        set_time_limit(120);
        try {
            $leido = LecturaComprobante::leer(Auth::user(), $request->file('archivo'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('compras.create', ['lectura' => 1])->with('compra_lectura', $leido);
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
            'tdocod' => 'required|in:'.implode(',', array_keys(Compras::DOCUMENTOS)),
            'serie' => 'required|string|max:4',
            'numero' => 'required|string|max:8|regex:/^\d+$/',
            'fecEmi' => 'required|date',
            'fecVen' => 'nullable|date',
            'fecIng' => 'required|date',
            'estadopago' => 'required|integer',
            'moneda' => 'required|in:PEN,USD',
            'tip_cam' => 'nullable|numeric|min:0',
            'id_almacen' => 'required|integer',
            'prov_tdicod' => 'required|string|size:1',
            'prov_num' => 'required|string|max:11',
            'prov_nom' => 'required|string|max:255',
            'prov_dir' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:255',
            'actualizar_costo' => 'boolean',
            'en_transito' => 'boolean',
            'orden_id' => 'nullable|integer',
            'requerimiento_id' => 'nullable|integer',
            // Leído de un comprobante: qué producto eligió el usuario para cada línea que no se reconoció (se aprende)
            'aprendidos' => 'nullable|array|max:100',
            'items' => 'required|array|min:1|max:300',
            'items.*.id' => 'required|integer',
            'items.*.tip_igv' => 'required|in:10,20,30',
            'items.*.cantidad' => 'required|numeric|min:0.01|max:9999999',
            'items.*.costo' => 'required|numeric|min:0|max:9999999',
            'items.*.flete' => 'nullable|numeric|min:0',
            'items.*.presentacion' => 'nullable|integer',
            'items.*.lote' => 'nullable|string|max:50',
            'items.*.vencimiento' => 'nullable|date',
            'items.*.series' => 'nullable|string|max:20000',
        ], [
            'items.required' => 'Agrega al menos un producto.',
            'numero.regex' => 'El número del documento solo lleva dígitos.',
        ], [
            'tdocod' => 'documento', 'fecEmi' => 'fecha de emisión', 'fecIng' => 'fecha de ingreso',
            'prov_num' => 'documento del proveedor', 'prov_nom' => 'nombre del proveedor',
        ]);

        $fotoAntes = $id ? self::fotoCompra($id) : null;
        try {
            if ($id) {
                Acciones::exigir(Auth::user(), 'compras_editar');
            }
            $comId = Compras::guardar(Auth::user(), $d, $id);
        } catch (\RuntimeException $e) {
            return response()->json(['estado' => 'error', 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['estado' => 'error', 'mensaje' => config('app.debug') ? $e->getMessage() : 'No se pudo guardar la compra.']);
        }

        $cab = DB::table('compras_cabecera')->where('com_cab_id', $comId)->first(['com_doc_ser', 'com_doc_num', 'prov_num', 'total_com', 'mon_id']);
        if (! empty($d['aprendidos'])) {
            LecturaComprobante::aprender(Auth::user(), (string) $cab->prov_num, $d['aprendidos']);
        }
        if ($id) {
            // Editar una compra cambia el stock y el costo: siempre se revisa
            Bitacora::siCambio('compra.editar', "Editó la compra {$cab->com_doc_ser}-{$cab->com_doc_num}", $fotoAntes, self::fotoCompra($comId),
                self::ETIQUETAS_BITACORA, ['referencia' => ['compra', $comId], 'alerta' => true]);
        } else {
            Bitacora::registrar('compra.registrar', "Registró la compra {$cab->com_doc_ser}-{$cab->com_doc_num} del proveedor {$cab->prov_num} por "
                .($cab->mon_id === 'USD' ? 'US$ ' : 'S/ ').number_format((float) $cab->total_com, 2)
                .(! empty($d['orden_id']) ? ' (de la orden de compra)' : '').(! empty($d['requerimiento_id']) ? ' (del requerimiento del día)' : '').(! empty($d['en_transito']) ? ' · mercadería en tránsito' : ''), null,
                ['referencia' => ['compra', $comId]]);
        }
        session()->flash('success', ($id ? 'Se actualizó' : 'Se registró')." la compra {$cab->com_doc_ser}-{$cab->com_doc_num}.");
        if ($aviso = Recetas::avisoDeCompra((int) Auth::user()->id_empresa_negocio, $comId)) {
            session()->flash('costos', $aviso);
        }

        return response()->json(['estado' => 'success', 'redirect' => route('compras.index')]);
    }

    public function anular($id)
    {
        $this->autorizar();
        $foto = self::fotoCompra((int) $id);
        try {
            Acciones::exigir(Auth::user(), 'compras_editar');
            Compras::anular(Auth::user(), (int) $id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        Bitacora::registrar('compra.anular', 'Anuló la compra '.($foto['documento'] ?? $id).' (proveedor '.($foto['proveedor'] ?? '').', total '.($foto['total'] ?? '').')',
            null, ['referencia' => ['compra', (int) $id], 'alerta' => true, 'datos' => ['productos' => $foto['productos'] ?? []]]);

        return back()->with('success', 'Compra anulada: su mercadería salió del stock.');
    }

    /** Lo que se compara en la bitácora */
    public const ETIQUETAS_BITACORA = ['documento' => 'Documento', 'proveedor' => 'Proveedor', 'emision' => 'Fecha de emisión', 'ingreso' => 'Fecha de ingreso',
        'moneda' => 'Moneda', 'tipo_cambio' => 'Tipo de cambio', 'total' => 'Total', 'almacen' => 'Almacén', 'observacion' => 'Observación', 'productos' => 'Productos'];

    /**
     * La compra como se ve (documento, proveedor, total y cada producto con su cantidad y precio) para la bitácora.
     *
     * @return array<string, mixed>|null
     */
    public static function fotoCompra(int $id): ?array
    {
        $c = Bitacora::foto('compras_cabecera', ['com_cab_id' => $id]);
        if (! $c) {
            return null;
        }
        $numero = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
        $productos = DB::table('compras_detalle as d')->leftJoin('productos as p', 'p.IdProducto', '=', 'd.pro_id')->where('d.com_cab_id', $id)
            ->orderBy('d.com_det_id')->get(['p.pronom', 'd.pro_id', 'd.cantidad', 'd.pre_uni'])
            ->map(fn ($i) => ($i->pronom ?? 'Producto '.$i->pro_id).' × '.$numero($i->cantidad).' a '.number_format((float) $i->pre_uni, 2, '.', ''))->all();

        return [
            'documento' => $c['com_doc_ser'].'-'.$c['com_doc_num'], 'proveedor' => $c['prov_num'], 'emision' => $c['com_fec'], 'ingreso' => $c['com_fec_ing'],
            'moneda' => $c['mon_id'], 'tipo_cambio' => $c['tip_cam'], 'total' => number_format((float) $c['total_com'], 2, '.', ''),
            'almacen' => DB::table('almacenes')->where('id_almacen', $c['id_almacen'])->value('descripcion') ?? $c['id_almacen'],
            'observacion' => $c['comp_obs'], 'productos' => $productos,
        ];
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
            ->when($codigo !== '', fn ($w) => $w->where(fn ($x) => $x->where('productos.procod', $codigo)->orWhere('productos.codigo_barra', $codigo)
                ->when($presCodigo, fn ($y) => $y->orWhere('productos.IdProducto', $presCodigo->IdProducto))))
            ->when($codigo === '', function ($w) use ($q) {
                foreach (preg_split('/\s+/', $q) as $p) {
                    $w->where(fn ($x) => $x->where('productos.pronom', 'like', "%{$p}%")->orWhere('productos.procod', 'like', "{$p}%")
                        ->orWhere('productos.codigo_barra', $p));
                }
                $w->orderByRaw('productos.procod = ? DESC, productos.pronom LIKE ? DESC', [$q, $q.'%']);
            })
            ->orderBy('productos.pronom')->limit($codigo !== '' ? 1 : 20)
            ->get(['productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod', 'productos.costo', 'productos.promocion', 'productos.control_lote', 'productos.control_serie', 'producto_stock.stock']);
        $presentaciones = Precios::presentaciones($productos->pluck('IdProducto'));
        $unidades = UnidadMedida::pluck('umenom', 'umecod');

        return response()->json($productos->map(fn ($p) => [
            'id' => $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom,
            'costo' => (float) $p->costo, 'stock' => (float) ($p->stock ?? 0), 'insumo' => (int) $p->promocion === 4,
            'control_lote' => (bool) $p->control_lote, 'control_serie' => (bool) $p->control_serie, 'unidad' => $unidades[$p->umecod] ?? $p->umecod,
            'presentaciones' => $presentaciones[$p->IdProducto] ?? [],
            'presentacion' => $presCodigo && (int) $presCodigo->IdProducto === (int) $p->IdProducto ? (int) $presCodigo->id_presentacion : null,
        ]));
    }

    /**
     * Producto nuevo creado desde la compra (sin salir de la pantalla): lo básico para comprarlo y venderlo.
     * Responde con el mismo formato del buscador para agregarlo de frente al detalle.
     */
    public function productoRapido(Request $request)
    {
        $this->autorizar();
        $user = Auth::user();
        $tipos = array_values(array_intersect(Compras::TIPOS_CON_STOCK, Rubros::tiposProducto()));
        $d = $request->validate([
            'promocion' => ['required', 'integer', 'in:'.implode(',', $tipos)],
            'pronom' => 'required|string|max:150',
            'propun' => ((int) $request->promocion === 4 ? 'nullable' : 'required').'|numeric|min:0|max:999999',
            'costo' => ((int) $request->promocion === 4 ? 'required|numeric|gt:0' : 'nullable|numeric|min:0').'|max:999999',
            'umecod' => 'nullable|exists:unidad_medida,umecod',
            'cat_id' => 'nullable|integer',
            'codigo_barra' => 'nullable|string|max:50',
        ], ['costo.required' => 'Escribe el costo del insumo: sin él, el food cost de las recetas no es real.', 'costo.gt' => 'Escribe el costo del insumo: sin él, el food cost de las recetas no es real.'],
            ['pronom' => 'nombre del producto', 'propun' => 'precio de venta', 'promocion' => 'tipo', 'codigo_barra' => 'código de barras', 'umecod' => 'unidad']);

        $sucursal = (int) $user->id_empresa_negocio;
        $nombre = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $d['pronom'])));
        if (Producto::where('id_empresa_negocio', $sucursal)->where('pronom', $nombre)->where('proest', 'Activo')->exists()) {
            return response()->json(['message' => "Ya existe un producto llamado {$nombre}: búscalo en el buscador."], 422);
        }
        $codigo = trim((string) ($d['codigo_barra'] ?? ''));
        if ($codigo !== '' && (Producto::where('id_empresa_negocio', $sucursal)->where(fn ($q) => $q->where('codigo_barra', $codigo)->orWhere('procod', $codigo))->exists()
            || DB::table('producto_presentacion as pp')->join('productos as p', 'p.IdProducto', '=', 'pp.IdProducto')
                ->where('p.id_empresa_negocio', $sucursal)->where('pp.codigo_barra', $codigo)->exists())) {
            return response()->json(['message' => "El código de barras {$codigo} ya lo tiene otro producto."], 422);
        }
        $categoria = ! empty($d['cat_id']) && DB::table('categorias')->where('cat_id', $d['cat_id'])->where('id_empresa_negocio', $sucursal)->exists()
            ? (int) $d['cat_id'] : null;

        $producto = Producto::create([
            'procod' => 'P'.time().random_int(10, 99), 'codigo_barra' => $codigo ?: null, 'pronom' => $nombre,
            'umecod' => $d['umecod'] ?? 'NIU', 'costo' => $d['costo'] ?? 0, 'propun' => $d['propun'] ?? 0,
            'promocion' => (int) $d['promocion'], 'cat_id' => $categoria, 'stock_min' => 0, 'proest' => 'Activo',
            'ume_equivalente' => null, 'factor_equivalente' => 1,
            'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $sucursal,
        ]);
        $unidad = UnidadMedida::where('umecod', $producto->umecod)->value('umenom');

        return response()->json([
            'id' => $producto->IdProducto, 'codigo' => $producto->procod, 'nombre' => $producto->pronom, 'costo' => (float) $producto->costo,
            'stock' => 0.0, 'insumo' => (int) $producto->promocion === 4, 'control_lote' => false, 'control_serie' => false,
            'unidad' => $unidad ?? $producto->umecod, 'presentaciones' => [], 'presentacion' => null,
        ]);
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
            ->where(fn ($w) => $w->where('prov_raz', 'like', "%{$q}%")->orWhere('prov_ruc', 'like', "{$q}%"))
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

        if ($r = ConsultaPeru::ruc($doc)) {
            return response()->json(['nom' => $r['nombre'], 'dir' => $r['direccion'], 'tdicod' => '6']);
        }
        if ($r = ConsultaPeru::dni($doc)) {
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
                ->get(['d.*', 'p.pronom', 'p.procod', 'p.control_lote', 'p.control_serie']);

            $compra = [
                'id' => $cab->com_cab_id, 'estado' => $cab->est_compra,
                'tdocod' => $cab->tdocod, 'serie' => $cab->com_doc_ser, 'numero' => $cab->com_doc_num,
                'fecEmi' => $cab->com_fec, 'fecVen' => $cab->com_fec_ven, 'fecIng' => $cab->com_fec_ing,
                'estadopago' => $cab->cre_dia_id, 'moneda' => $cab->mon_id, 'tip_cam' => $cab->tip_cam,
                'id_almacen' => $cab->id_almacen, 'observaciones' => $cab->comp_obs,
                'en_transito' => (bool) ($cab->en_transito ?? false), 'orden' => $cab->orden_compra ?? null,
                'proveedor' => ['tdicod' => $prov->tdicod ?? '6', 'num' => $prov->prov_ruc ?? '', 'nom' => $prov->prov_raz ?? '', 'dir' => $prov->prov_dir ?? ''],
                'items' => $items->map(fn ($i) => [
                    'id' => $i->pro_id, 'codigo' => $i->procod, 'nombre' => $i->pronom, 'tip_igv' => $i->tip_igv,
                    'presentacion' => $i->id_presentacion ? (int) $i->id_presentacion : null, 'factor' => (float) ($i->factor ?: 1),
                    'presentacion_nombre' => $i->id_presentacion ? DB::table('producto_presentacion')->where('id_presentacion', $i->id_presentacion)->value('nombre') : null,
                    'cantidad' => (float) $i->cantidad, 'costo' => (float) $i->pre_uni, 'flete' => (float) $i->flete,
                    'lote' => $i->lote, 'vencimiento' => $i->vencimiento, 'control_lote' => (bool) $i->control_lote,
                    'control_serie' => (bool) $i->control_serie, 'series' => (string) $i->series,
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
            // Para crear un producto sin salir de la compra
            'categorias' => DB::table('categorias')->where('id_empresa_negocio', $user->id_empresa_negocio)->orderBy('cat_nom')->get(['cat_id', 'cat_nom']),
            'unidades' => UnidadMedida::orderBy('umenom')->get(['umecod', 'umenom']),
            'tiposNuevo' => array_values(array_intersect(Compras::TIPOS_CON_STOCK, Rubros::tiposProducto())),
        ];
    }
}
