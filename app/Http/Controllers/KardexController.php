<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Producto;
use App\Support\Buscar;
use App\Support\Excel;
use App\Support\Kardex;
use App\Support\KardexValorizado;
use App\Support\Series;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KardexController extends Controller
{
    // Operaciones SUNAT (tabla 12) permitidas en ingresos y salidas manuales
    private const OPERACIONES = [
        'I' => ['02', '05', '16', '19', '21', '24', '28', '99'],
        'E' => ['10', '11', '12', '13', '14', '15', '25', '28', '99'],
    ];

    // Solo productos simples (0) e insumos (4) manejan stock; preparados y combos no
    private function productosConStock()
    {
        return Producto::where('productos.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->whereIn('productos.promocion', [0, 4])
            ->orderBy('productos.pronom');
    }

    private function almacenes()
    {
        return Almacen::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->get();
    }

    public function index(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $productos = $this->productosConStock()->get(['IdProducto', 'pronom', 'procod', 'umecod']);
        $almacenes = $this->almacenes();

        $idProducto = $request->get('producto');
        $idAlmacen = $request->get('almacen', $almacenes->first()?->id_almacen);
        $desde = $request->get('desde', now()->startOfMonth()->toDateString());
        $hasta = $request->get('hasta', now()->toDateString());

        $vista = $request->get('vista') === 'valorizado' ? 'valorizado' : 'fisico';
        $producto = null;
        $movimientos = collect();
        $saldoInicial = 0;
        $stockActual = 0;
        $valorizado = null;

        if ($idProducto) {
            $producto = Producto::where('IdProducto', $idProducto)->where('id_empresa_negocio', $sucursal)->firstOrFail();

            $base = DB::table('movimientos_productos')
                ->where('movimientos_productos.IdProducto', $producto->IdProducto)
                ->where('movimientos_productos.id_almacen', $idAlmacen)
                ->where('movimientos_productos.id_empresa_negocio', $sucursal);

            // Saldo con el que arranca el rango = stock final del último movimiento anterior a "desde"
            $saldoInicial = (float) ((clone $base)->where('fecha_mov', '<', $desde)->orderByDesc('mov_pro_id')->value('stock') ?? 0);

            $movimientos = (clone $base)
                ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'movimientos_productos.cod_tip_ope')
                ->leftJoin('productos as rel', 'rel.IdProducto', '=', 'movimientos_productos.IdProducto_rel')
                ->leftJoin('users as u', 'u.IdUsuario', '=', 'movimientos_productos.IdUsuario')
                ->whereBetween('movimientos_productos.fecha_mov', [$desde, $hasta])
                ->orderBy('movimientos_productos.mov_pro_id')
                ->select('movimientos_productos.*', 't.des_tip_ope', 'rel.pronom as combo_nom', 'u.apeusu')
                ->get();

            $stockActual = (float) (DB::table('producto_stock')
                ->where('IdProducto', $producto->IdProducto)->where('id_almacen', $idAlmacen)->value('stock') ?? 0);

            if ($vista === 'valorizado') {
                $valorizado = KardexValorizado::producto($producto->IdProducto, (int) $idAlmacen, (int) $sucursal, $desde, $hasta);
            }
        }

        // Sin producto elegido: resumen de todos los productos del almacén (al elegir uno se ve su detalle)
        $resumen = null;
        $totales = null;
        if (! $producto) {
            $filas = $this->filasResumen((int) $sucursal, (int) $idAlmacen, $desde, $hasta);
            $totales = ['entradas' => round($filas->sum('entradas.valor'), 2), 'salidas' => round($filas->sum('salidas.valor'), 2),
                'inicial' => round($filas->sum('inicial.valor'), 2), 'final' => round($filas->sum(fn ($f) => max(0, $f['final']['valor'])), 2)];
            $pagina = LengthAwarePaginator::resolveCurrentPage();
            $resumen = new LengthAwarePaginator($filas->forPage($pagina, 50)->values(), $filas->count(), 50, $pagina,
                ['path' => $request->url(), 'query' => $request->query()]);
        }

        return view('empresas.kardex.index', compact(
            'productos', 'almacenes', 'producto', 'idAlmacen', 'desde', 'hasta', 'movimientos', 'saldoInicial', 'stockActual', 'vista', 'valorizado', 'resumen', 'totales'
        ));
    }

    /** Resumen del kardex de todos los productos (cantidades y soles), ordenado por nombre */
    private function filasResumen(int $sucursal, int $idAlmacen, string $desde, string $hasta)
    {
        $datos = KardexValorizado::resumen($sucursal, $idAlmacen, $desde, $hasta);
        $productos = $this->productosConStock()->whereIn('IdProducto', array_keys($datos))->get(['IdProducto', 'procod', 'pronom', 'umecod']);

        return $productos->map(fn ($p) => ['id' => $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom, 'unidad' => $p->umecod] + $datos[$p->IdProducto])->values();
    }

    /** Kardex en Excel (físico o valorizado): de un producto, o el resumen de todos */
    public function excel(Request $request)
    {
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacen = $this->almacenes()->firstWhere('id_almacen', (int) $request->get('almacen')) ?? abort(404);
        $desde = $request->date('desde')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $hasta = $request->date('hasta')?->toDateString() ?? now()->toDateString();
        if (! $request->filled('producto')) {
            return $this->excelResumen($sucursal, $almacen, $desde, $hasta, $request->get('vista') === 'valorizado');
        }
        $producto = Producto::where('IdProducto', $request->get('producto'))->where('id_empresa_negocio', $sucursal)->firstOrFail();
        $documento = fn ($m) => $m->serie ? $m->serie.'-'.str_pad((string) $m->numero, 8, '0', STR_PAD_LEFT) : ($m->mov_cab_id ? 'MOV-'.$m->mov_cab_id : '');
        $fecha = fn ($m) => Carbon::parse($m->fecha_mov)->format('d/m/Y');

        if ($request->get('vista') === 'valorizado') {
            $k = KardexValorizado::producto($producto->IdProducto, $almacen->id_almacen, $sucursal, $desde, $hasta);
            $filas = [['', 'SALDO INICIAL', '', '', '', '', '', '', '', $k['inicial']['cantidad'], $k['inicial']['costo'], $k['inicial']['valor']]];
            foreach ($k['filas'] as $f) {
                $m = $f['mov'];
                $entrada = $m->mov_tip === 'I';
                $filas[] = [$fecha($m), $m->cod_tip_ope.' '.$m->des_tip_ope, $documento($m),
                    $entrada ? $f['cantidad'] : '', $entrada ? $f['costo'] : '', $entrada ? $f['total'] : '',
                    $entrada ? '' : $f['cantidad'], $entrada ? '' : $f['costo'], $entrada ? '' : $f['total'],
                    $f['saldo']['cantidad'], $f['saldo']['costo'], $f['saldo']['valor']];
            }
            $cabecera = [['Kardex valorizado (costo promedio) · '.$producto->pronom.' · '.$almacen->descripcion.' · del '.$desde.' al '.$hasta],
                ['Fecha', 'Operación', 'Documento', 'Entrada cant.', 'Entrada C.U.', 'Entrada total', 'Salida cant.', 'Salida C.U.', 'Salida total', 'Saldo cant.', 'Saldo C.U.', 'Saldo total']];
            $nombre = 'kardex_valorizado_';
        } else {
            $filas = DB::table('movimientos_productos as m')
                ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'm.cod_tip_ope')
                ->where('m.IdProducto', $producto->IdProducto)->where('m.id_almacen', $almacen->id_almacen)->where('m.id_empresa_negocio', $sucursal)
                ->whereBetween('m.fecha_mov', [$desde, $hasta])->orderBy('m.mov_pro_id')->select('m.*', 't.des_tip_ope')->get()
                ->map(fn ($m) => [$fecha($m), $m->cod_tip_ope.' '.$m->des_tip_ope, $documento($m), (string) ($m->cliente ?? $m->descripcion),
                    (float) $m->stock_inicial, $m->mov_tip === 'I' ? (float) $m->cantidad : '', $m->mov_tip === 'E' ? (float) $m->cantidad : '', (float) $m->stock])->all();
            $cabecera = [['Kardex físico (cantidades) · '.$producto->pronom.' · '.$almacen->descripcion.' · del '.$desde.' al '.$hasta],
                ['Fecha', 'Operación', 'Documento', 'Detalle', 'Saldo anterior', 'Entrada', 'Salida', 'Saldo']];
            $nombre = 'kardex_fisico_';
        }

        $ruta = (new Excel)->hoja('Kardex', $cabecera, $filas)->guardar();

        return response()->download($ruta, $nombre.preg_replace('/[^A-Za-z0-9]+/', '_', $producto->pronom).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function excelResumen(int $sucursal, $almacen, string $desde, string $hasta, bool $valorizado)
    {
        $filas = $this->filasResumen($sucursal, $almacen->id_almacen, $desde, $hasta)->map(fn ($f) => $valorizado
            ? [(string) $f['codigo'], $f['nombre'], $f['unidad'], $f['inicial']['cantidad'], $f['inicial']['valor'], $f['entradas']['cantidad'], $f['entradas']['valor'],
                $f['salidas']['cantidad'], $f['salidas']['valor'], $f['final']['cantidad'], $f['final']['costo'], $f['final']['valor']]
            : [(string) $f['codigo'], $f['nombre'], $f['unidad'], $f['inicial']['cantidad'], $f['entradas']['cantidad'], $f['salidas']['cantidad'], $f['final']['cantidad']])->all();
        $titulo = 'Kardex '.($valorizado ? 'valorizado (costo promedio)' : 'físico (cantidades)').' · todos los productos · '.$almacen->descripcion.' · del '.$desde.' al '.$hasta;
        $cabecera = $valorizado
            ? ['Código', 'Producto', 'Unidad', 'Saldo inicial', 'Saldo inicial S/', 'Entradas', 'Entradas S/', 'Salidas', 'Salidas S/ (costo)', 'Saldo final', 'Costo unit.', 'Saldo final S/']
            : ['Código', 'Producto', 'Unidad', 'Saldo inicial', 'Entradas', 'Salidas', 'Saldo final'];
        $ruta = (new Excel)->hoja('Kardex', [[$titulo], $cabecera], $filas)->guardar();

        return response()->download($ruta, 'kardex_'.($valorizado ? 'valorizado' : 'fisico').'_todos_'.str_replace('-', '', $hasta).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /**
     * Inventario: cuánto tienes en físico (cantidades) y cuánto vale en dinero, a hoy o a una fecha pasada.
     * Valor por costo promedio (del kardex) o por el último costo de compra.
     */
    public function inventario(Request $request)
    {
        $filtros = $this->filtrosInventario($request);
        $filas = $this->filasInventario($filtros);

        // El stock negativo (se vendió sin registrar el ingreso) se muestra pero no resta al valor del inventario
        $positivos = $filas->filter(fn ($f) => $f['cantidad'] > 0);
        $resumen = [
            'valor' => round($positivos->sum('valor'), 2),
            'productos' => $filas->filter(fn ($f) => $f['cantidad'] > 0)->count(),
            'negativos' => $filas->filter(fn ($f) => $f['cantidad'] < 0)->count(),
            'bajo_minimo' => $filas->filter(fn ($f) => $f['minimo'] > 0 && $f['cantidad'] <= $f['minimo'])->count(),
        ];
        $porCategoria = $positivos->groupBy('categoria')->map(fn ($g) => round($g->sum('valor'), 2))->sortDesc()->take(8);
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginas = new LengthAwarePaginator($filas->forPage($pagina, 50)->values(), $filas->count(), 50, $pagina,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('empresas.kardex.inventario', $filtros + [
            'almacenes' => $this->almacenes(), 'categorias' => DB::table('categorias')->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderBy('cat_nom')->pluck('cat_nom', 'cat_id'),
            'filas' => $paginas, 'resumen' => $resumen, 'porCategoria' => $porCategoria,
        ]);
    }

    public function inventarioExcel(Request $request)
    {
        $filtros = $this->filtrosInventario($request);
        $filas = $this->filasInventario($filtros);
        $porAlmacen = (bool) $filtros['idAlmacen'];
        $datos = $filas->map(fn ($f) => array_merge([(string) $f['codigo'], $f['nombre'], (string) $f['categoria'], $f['unidad']],
            $porAlmacen ? [$f['almacen']] : [], [$f['cantidad'], $f['costo'], $f['valor']]))->all();
        $positivos = $filas->filter(fn ($f) => $f['cantidad'] > 0);
        $datos[] = array_merge(['', 'TOTAL (solo stock positivo)', '', ''], $porAlmacen ? [''] : [], [round($positivos->sum('cantidad'), 4), '', round($positivos->sum('valor'), 2)]);
        $titulo = 'Inventario al '.Carbon::parse($filtros['fecha'])->format('d/m/Y').' · '.($filtros['metodo'] === 'ultimo' ? 'último costo' : 'costo promedio')
            .' · '.($porAlmacen ? $this->almacenes()->firstWhere('id_almacen', $filtros['idAlmacen'])?->descripcion : 'todos los almacenes');
        $ruta = (new Excel)->hoja('Inventario', [[$titulo], array_merge(['Código', 'Producto', 'Categoría', 'Unidad'], $porAlmacen ? ['Almacén'] : [],
            ['Stock', 'Costo unitario', 'Valor S/'])], $datos)->guardar();

        return response()->download($ruta, 'inventario_valorizado_'.str_replace('-', '', $filtros['fecha']).'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** @return array{idAlmacen: ?int, fecha: string, metodo: string, categoria: ?int, q: string, conStock: bool} */
    private function filtrosInventario(Request $request): array
    {
        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();

        return [
            'idAlmacen' => $request->integer('almacen') ?: null,
            'fecha' => min($fecha, now()->toDateString()),
            'metodo' => $request->get('metodo') === 'ultimo' ? 'ultimo' : 'promedio',
            'categoria' => $request->integer('categoria') ?: null,
            'q' => trim((string) $request->get('q')),
            // Al abrir la pantalla solo se ven los productos con stock; al filtrar manda el casillero
            'conStock' => $request->has('fecha') ? $request->boolean('con_stock') : true,
        ];
    }

    /** Filas del inventario: por producto (todos los almacenes sumados) o por producto en el almacén elegido */
    private function filasInventario(array $f)
    {
        $sucursal = (int) Auth::user()->id_empresa_negocio;
        $almacenes = $this->almacenes()->when($f['idAlmacen'], fn ($a) => $a->where('id_almacen', $f['idAlmacen']))->pluck('descripcion', 'id_almacen');
        $productos = $this->productosConStock()
            ->leftJoin('categorias as c', 'c.cat_id', '=', 'productos.cat_id')
            ->when($f['categoria'], fn ($q) => $q->where('productos.cat_id', $f['categoria']))
            ->tap(fn ($q) => Buscar::palabras($q, $f['q'], ['productos.pronom'], ['productos.procod', 'productos.codigo_barra']))
            ->get(['productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod', 'productos.costo', 'productos.stock_min', 'c.cat_nom']);

        // Existencias y costo promedio según el kardex a esa fecha; hoy, la cantidad es el stock actual de cada almacén
        $kardex = KardexValorizado::existencias($sucursal, $f['idAlmacen'], $f['fecha']);
        $hoy = $f['fecha'] >= now()->toDateString();
        $stockHoy = $hoy ? DB::table('producto_stock')->whereIn('id_almacen', $almacenes->keys())
            ->get(['IdProducto', 'id_almacen', 'stock'])->mapWithKeys(fn ($s) => [$s->IdProducto.'-'.$s->id_almacen => (float) $s->stock]) : collect();

        $filas = collect();
        foreach ($productos as $p) {
            $porAlmacen = [];
            foreach ($almacenes as $idAlmacen => $nombreAlmacen) {
                $clave = $p->IdProducto.'-'.$idAlmacen;
                $cantidad = $hoy ? ($stockHoy[$clave] ?? 0.0) : ($kardex[$clave]['cantidad'] ?? 0.0);
                $costo = $f['metodo'] === 'ultimo' || ($kardex[$clave]['costo'] ?? 0) <= 0 ? (float) $p->costo : $kardex[$clave]['costo'];
                $porAlmacen[] = ['almacen' => $nombreAlmacen, 'cantidad' => round($cantidad, 4), 'valor' => round($cantidad * $costo, 2)];
            }
            $cantidad = round(array_sum(array_column($porAlmacen, 'cantidad')), 4);
            if ($f['conStock'] && abs($cantidad) < 0.0001) {
                continue;
            }
            $valor = round(array_sum(array_column($porAlmacen, 'valor')), 2);
            $filas->push(['id' => $p->IdProducto, 'codigo' => $p->procod, 'nombre' => $p->pronom, 'categoria' => $p->cat_nom ?: 'SIN CATEGORÍA',
                'unidad' => $p->umecod, 'almacen' => $f['idAlmacen'] ? $almacenes->first() : $almacenes->count().' almacén(es)',
                'cantidad' => $cantidad, 'valor' => $valor, 'costo' => abs($cantidad) > 0.0001 ? round($valor / $cantidad, 4) : (float) $p->costo,
                'minimo' => (float) $p->stock_min]);
        }

        return $filas;
    }

    public function stock(Request $request)
    {
        $almacenes = $this->almacenes();
        $idAlmacen = $request->get('almacen', $almacenes->first()?->id_almacen);
        $q = trim((string) $request->get('q'));

        $productos = $this->productosConStock()
            ->leftJoin('producto_stock as ps', function ($j) use ($idAlmacen) {
                $j->on('ps.IdProducto', '=', 'productos.IdProducto')->where('ps.id_almacen', $idAlmacen);
            })
            ->tap(fn ($qq) => Buscar::palabras($qq, $q, ['productos.pronom'], ['productos.procod', 'productos.codigo_barra']))
            ->select('productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod',
                'productos.promocion', 'productos.stock_min', 'productos.costo', DB::raw('COALESCE(ps.stock, 0) as stock'))
            ->paginate(30)->withQueryString();

        return view('empresas.kardex.stock', compact('productos', 'almacenes', 'idAlmacen', 'q'));
    }

    public function movimiento(Request $request)
    {
        $tipo = $request->get('tipo') === 'E' ? 'E' : 'I';
        $productos = $this->productosConStock()->get(['IdProducto', 'pronom', 'procod', 'costo', 'control_lote', 'control_serie']);
        $almacenes = $this->almacenes();
        $operaciones = DB::table('tipo_operacion_sunat')->whereIn('cod_tip_ope', self::OPERACIONES[$tipo])->get();

        // Últimos movimientos manuales registrados
        $recientes = DB::table('movimientos_cabecera as mc')
            ->leftJoin('tipo_operacion_sunat as t', 't.cod_tip_ope', '=', 'mc.cod_tip_ope')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'mc.usu_ent')
            ->where('mc.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->where('mc.mov_tip', $tipo)
            ->orderByDesc('mc.mov_cab_id')->limit(10)
            ->select('mc.*', 't.des_tip_ope', 'u.apeusu',
                DB::raw('(SELECT COUNT(*) FROM movimientos_productos mp WHERE mp.mov_cab_id = mc.mov_cab_id) as items'))
            ->get();

        return view('empresas.kardex.movimiento', compact('tipo', 'productos', 'almacenes', 'operaciones', 'recientes'));
    }

    public function guardarMovimiento(Request $request)
    {
        $user = Auth::user();
        $tipo = $request->input('mov_tip') === 'E' ? 'E' : 'I';

        $request->validate([
            'id_almacen' => 'required|integer',
            'cod_tip_ope' => 'required|in:'.implode(',', self::OPERACIONES[$tipo]),
            'fecha' => 'required|date|before_or_equal:today',
            'observaciones' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.IdProducto' => 'required|integer',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.costo' => 'nullable|numeric|min:0',
            'items.*.lote' => 'nullable|string|max:50',
            'items.*.vencimiento' => 'nullable|date',
            'items.*.series' => 'nullable|string|max:20000',
        ], [], [
            'cod_tip_ope' => 'Tipo de operación', 'items' => 'Productos',
            'items.*.cantidad' => 'Cantidad', 'items.*.costo' => 'Costo',
        ]);

        $almacen = Almacen::where('id_almacen', $request->id_almacen)->where('id_empresa_negocio', $user->id_empresa_negocio)->first();
        if (! $almacen) {
            return back()->withInput()->withErrors(['id_almacen' => 'Almacén no válido.']);
        }

        $ids = collect($request->items)->pluck('IdProducto')->unique();
        $productos = $this->productosConStock()->whereIn('IdProducto', $ids)->get()->keyBy('IdProducto');
        if ($productos->count() !== $ids->count()) {
            return back()->withInput()->withErrors(['items' => 'Hay productos que no existen o no manejan stock.']);
        }

        // Farmacia: en ingresos, los productos con control de lote necesitan lote y vencimiento
        if ($tipo === 'I') {
            foreach ($request->items as $it) {
                $p = $productos[$it['IdProducto']];
                if ($p->control_lote && (empty(trim($it['lote'] ?? '')) || empty($it['vencimiento']))) {
                    return back()->withInput()->withErrors(['items' => "Ingresa el lote y la fecha de vencimiento de {$p->pronom}."]);
                }
            }
        }

        try {
            DB::transaction(function () use ($request, $user, $tipo, $almacen, $productos) {
                $cabId = DB::table('movimientos_cabecera')->insertGetId([
                    'mov_tip' => $tipo, 'cod_tip_ope' => $request->cod_tip_ope,
                    'fecha' => $request->fecha, 'observaciones' => $request->observaciones,
                    'estado' => 'REGISTRADO', 'usu_ent' => $user->IdUsuario,
                    'part_alm' => $tipo === 'E' ? $almacen->id_almacen : null,
                    'des_alm' => $tipo === 'I' ? $almacen->id_almacen : null,
                    'id_empresa_negocio' => $user->id_empresa_negocio,
                ]);

                foreach ($request->items as $it) {
                    $prod = $productos[$it['IdProducto']];
                    $costo = isset($it['costo']) && $it['costo'] !== '' ? (float) $it['costo'] : (float) $prod->costo;

                    Kardex::registrar($prod->IdProducto, $almacen->id_almacen, (float) $it['cantidad'], $tipo, [
                        'cod_tip_ope' => $request->cod_tip_ope, 'mov_cab_id' => $cabId, 'fecha_mov' => $request->fecha,
                        'costo' => $costo, 'descripcion' => $request->observaciones,
                        'numero' => (string) $cabId,
                        'lote' => $tipo === 'I' ? mb_strtoupper(trim($it['lote'] ?? '')) : null,
                        'vencimiento' => $tipo === 'I' ? ($it['vencimiento'] ?? null) : null,
                    ]);

                    // Electrodomésticos: entran con su serie o salen de baja las series indicadas
                    if ($prod->control_serie) {
                        $series = Series::lista($it['series'] ?? '');
                        $tipo === 'I'
                            ? Series::ingresar($prod, $almacen->id_almacen, $series, (float) $it['cantidad'],
                                ['mov_cab_id' => $cabId, 'fecha' => $request->fecha, 'id_empresa_negocio' => $user->id_empresa_negocio])
                            : Series::darDeBaja($prod, $series, (float) $it['cantidad'], $cabId);
                    }

                    // En compras se actualiza el costo del producto con el último costo de ingreso
                    if ($tipo === 'I' && $request->cod_tip_ope === '02' && $costo > 0) {
                        Producto::where('IdProducto', $prod->IdProducto)->update(['costo' => $costo]);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('kardex.movimiento', ['tipo' => $tipo])
            ->with('success', ($tipo === 'I' ? 'Ingreso' : 'Salida').' registrado y kardex actualizado.');
    }
}
