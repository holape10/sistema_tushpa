<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto};
use App\Support\{Excel, ExcelLector, Kardex};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

/**
 * Inventario físico: se cuenta lo que hay en el almacén y el sistema ajusta el stock a ese conteo.
 * - Producto sin movimientos en el almacén → ingreso por SALDO INICIAL (operación 16).
 * - Producto con movimientos → AJUSTE POR DIFERENCIA DE INVENTARIO (operación 28), ingreso o salida según la diferencia.
 */
class InventarioController extends Controller
{
    private function autorizar(): void
    {
        abort_unless(Auth::user()->esAdmin(), 403, 'Solo el Administrador registra inventarios.');
    }

    private function almacenes()
    {
        return Almacen::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->orderByDesc('predeterminado')->get();
    }

    /** Productos que manejan stock (simples e insumos) con su stock actual en el almacén */
    private function productos(int $idAlmacen)
    {
        return Producto::where('productos.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->whereIn('productos.promocion', [0, 4])
            ->leftJoin('producto_stock as ps', function ($j) use ($idAlmacen) {
                $j->on('ps.IdProducto', '=', 'productos.IdProducto')->where('ps.id_almacen', $idAlmacen);
            })
            ->leftJoin('categorias as c', 'c.cat_id', '=', 'productos.cat_id')
            ->orderBy('productos.pronom')
            ->get(['productos.IdProducto', 'productos.procod', 'productos.pronom', 'productos.umecod', 'productos.costo',
                'productos.promocion', 'c.cat_nom', DB::raw('COALESCE(ps.stock, 0) as stock')]);
    }

    private function almacen(int $id): Almacen
    {
        return Almacen::where('id_almacen', $id)->where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->firstOrFail();
    }

    public function index(Request $request)
    {
        $inventarios = DB::table('inventario_cabecera as i')
            ->leftJoin('almacenes as a', 'a.id_almacen', '=', 'i.id_almacen')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'i.IdUsuario')
            ->where('i.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->when($request->get('almacen'), fn($q, $a) => $q->where('i.id_almacen', $a))
            ->orderByDesc('i.inv_cab_id')
            ->select('i.*', 'a.descripcion as almacen', 'u.apeusu',
                DB::raw('(SELECT COUNT(*) FROM inventario_detalle d WHERE d.inv_cab_id = i.inv_cab_id) as items'),
                DB::raw('(SELECT COALESCE(SUM(d.diferencia * d.costo), 0) FROM inventario_detalle d WHERE d.inv_cab_id = i.inv_cab_id) as valor_ajuste'))
            ->paginate(20)->withQueryString();

        $almacenes = $this->almacenes();
        return view('empresas.inventarios.index', compact('inventarios', 'almacenes'));
    }

    public function create(Request $request)
    {
        $this->autorizar();
        $almacenes = $this->almacenes();
        abort_if($almacenes->isEmpty(), 404, 'Primero registra un almacén.');

        $idAlmacen = (int) $request->get('almacen', $almacenes->first()->id_almacen);
        $this->almacen($idAlmacen);
        $productos = $this->productos($idAlmacen);

        // Productos que ya tienen kardex en este almacén: su conteo será un ajuste (28) y no saldo inicial (16)
        $conMovimientos = DB::table('movimientos_productos')->where('id_almacen', $idAlmacen)
            ->distinct()->pluck('IdProducto')->flip();

        return view('empresas.inventarios.create', compact('almacenes', 'idAlmacen', 'productos', 'conMovimientos'));
    }

    public function store(Request $request)
    {
        $this->autorizar();
        $request->validate([
            'id_almacen'    => 'required|integer',
            'fecha'         => 'required|date|before_or_equal:today',
            'observaciones' => 'nullable|string|max:255',
            'items'         => 'required|json',
        ], [], ['items' => 'Productos contados']);

        $user = Auth::user();
        $almacen = $this->almacen((int) $request->id_almacen);

        // Los conteos llegan como JSON para no chocar con el límite max_input_vars cuando hay cientos de productos
        $items = collect(json_decode($request->items, true))
            ->filter(fn($i) => isset($i['IdProducto'], $i['cantidad']) && is_numeric($i['cantidad']) && $i['cantidad'] >= 0)
            ->keyBy('IdProducto');
        if ($items->isEmpty()) {
            return back()->withInput()->withErrors(['items' => 'Ingresa el conteo de al menos un producto.']);
        }

        $productos = Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->whereIn('promocion', [0, 4])
            ->whereIn('IdProducto', $items->keys())->get()->keyBy('IdProducto');
        if ($productos->count() !== $items->count()) {
            return back()->withInput()->withErrors(['items' => 'Hay productos que no existen o no manejan stock.']);
        }

        $id = DB::transaction(fn() => self::procesar($almacen, $request->fecha, $request->observaciones, 'MANUAL',
            $items->map(fn($i) => ['IdProducto' => (int) $i['IdProducto'], 'cantidad' => (float) $i['cantidad'],
                'costo' => isset($i['costo']) && is_numeric($i['costo']) ? (float) $i['costo'] : null])->values()->all(),
            $productos));

        return redirect()->route('inventarios.show', $id)->with('success', 'Inventario procesado: el stock quedó igual al conteo.');
    }

    /**
     * Registra el inventario y ajusta el kardex. Debe llamarse dentro de una transacción.
     * También lo usa la importación de productos para cargar el stock inicial.
     *
     * @param array $items [['IdProducto' => int, 'cantidad' => float, 'costo' => ?float], ...]
     */
    public static function procesar(Almacen $almacen, string $fecha, ?string $observaciones, string $origen, array $items, $productos): int
    {
        $user = Auth::user();
        $cabId = DB::table('inventario_cabecera')->insertGetId([
            'id_almacen' => $almacen->id_almacen, 'id_empresa_negocio' => $almacen->id_empresa_negocio,
            'fecha' => $fecha, 'observaciones' => $observaciones, 'origen' => $origen, 'estado' => 'PROCESADO',
            'IdUsuario' => $user->IdUsuario, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($items as $it) {
            $prod = $productos[$it['IdProducto']];
            $costo = $it['costo'] !== null && $it['costo'] > 0 ? $it['costo'] : (float) $prod->costo;

            // Bloquea la fila para leer el stock real del momento (Kardex::registrar vuelve a bloquearla, misma transacción)
            $sistema = (float) (DB::table('producto_stock')->where('IdProducto', $prod->IdProducto)
                ->where('id_almacen', $almacen->id_almacen)->lockForUpdate()->value('stock') ?? 0);
            $tieneKardex = DB::table('movimientos_productos')->where('IdProducto', $prod->IdProducto)
                ->where('id_almacen', $almacen->id_almacen)->exists();
            $diferencia = round($it['cantidad'] - $sistema, 5);
            $operacion = $tieneKardex ? '28' : '16';

            DB::table('inventario_detalle')->insert([
                'inv_cab_id' => $cabId, 'IdProducto' => $prod->IdProducto, 'stock_sistema' => $sistema,
                'stock_fisico' => $it['cantidad'], 'diferencia' => $diferencia, 'costo' => $costo, 'cod_tip_ope' => $operacion,
            ]);

            if ($diferencia != 0) {
                Kardex::registrar($prod->IdProducto, $almacen->id_almacen, abs($diferencia), $diferencia > 0 ? 'I' : 'E', [
                    'cod_tip_ope' => $operacion, 'inv_cab_id' => $cabId, 'fecha_mov' => $fecha, 'costo' => $costo,
                    'numero' => 'INV-' . $cabId, 'descripcion' => 'INVENTARIO N° ' . $cabId,
                    'id_empresa_negocio' => $almacen->id_empresa_negocio,
                ]);
            }

            // El costo indicado en el conteo pasa a ser el costo del producto
            if ($it['costo'] !== null && $it['costo'] > 0 && (float) $prod->costo != $it['costo']) {
                Producto::where('IdProducto', $prod->IdProducto)->update(['costo' => $it['costo']]);
            }
        }

        return $cabId;
    }

    public function show(int $id)
    {
        $inventario = DB::table('inventario_cabecera as i')
            ->leftJoin('almacenes as a', 'a.id_almacen', '=', 'i.id_almacen')
            ->leftJoin('users as u', 'u.IdUsuario', '=', 'i.IdUsuario')
            ->where('i.inv_cab_id', $id)->where('i.id_empresa_negocio', Auth::user()->id_empresa_negocio)
            ->select('i.*', 'a.descripcion as almacen', 'u.apeusu')->first();
        abort_unless($inventario, 404);

        $detalle = DB::table('inventario_detalle as d')
            ->join('productos as p', 'p.IdProducto', '=', 'd.IdProducto')
            ->where('d.inv_cab_id', $id)->orderBy('p.pronom')
            ->select('d.*', 'p.procod', 'p.pronom', 'p.umecod')->get();

        return view('empresas.inventarios.show', compact('inventario', 'detalle'));
    }

    /** Descarga el inventario procesado en Excel */
    public function exportar(int $id)
    {
        $vista = $this->show($id);
        ['inventario' => $inv, 'detalle' => $detalle] = $vista->getData();

        $filas = $detalle->map(fn($d) => [$d->procod, $d->pronom, $d->umecod, (float) $d->stock_sistema, (float) $d->stock_fisico,
            (float) $d->diferencia, (float) $d->costo, round($d->diferencia * $d->costo, 2),
            $d->cod_tip_ope === '16' ? 'SALDO INICIAL' : 'AJUSTE'])->all();

        $ruta = (new Excel())->hoja('Inventario ' . $id,
            ['Código', 'Producto', 'Unidad', 'Stock sistema', 'Stock físico', 'Diferencia', 'Costo unit.', 'Valor ajuste', 'Tipo'], $filas)->guardar();

        return response()->download($ruta, "inventario_{$id}_" . str_replace(' ', '_', $inv->almacen) . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Plantilla para contar: todos los productos del almacén con su stock actual y una columna vacía para el conteo */
    public function plantilla(Request $request)
    {
        $this->autorizar();
        $almacen = $this->almacen((int) $request->get('almacen'));

        $filas = $this->productos($almacen->id_almacen)->map(fn($p) => [
            $p->IdProducto, (string) $p->procod, $p->pronom, $p->umecod, $p->cat_nom ?? '', (float) $p->stock, '', (float) $p->costo,
        ])->all();

        $ruta = (new Excel())
            ->hoja('Conteo', ['ID', 'Código', 'Producto', 'Unidad', 'Categoría', 'Stock sistema', 'Stock físico', 'Costo unitario'], $filas)
            ->hoja('Instrucciones', ['Paso', 'Detalle'], [
                ['1', 'Escribe en la columna "Stock físico" la cantidad que contaste de cada producto.'],
                ['2', 'Deja vacía la celda de los productos que no contaste: no se modificarán.'],
                ['3', 'Si contaste 0, escribe 0: el stock del producto quedará en cero.'],
                ['4', '"Costo unitario" es opcional; si lo cambias, se actualiza el costo del producto.'],
                ['5', 'No cambies las columnas ID ni Código: con ellas se identifica cada producto.'],
                ['6', 'Guarda el archivo y súbelo en Inventarios > Nuevo inventario > Cargar Excel.'],
            ])->guardar();

        return response()->download($ruta, 'conteo_' . str_replace(' ', '_', $almacen->descripcion) . '_' . now()->format('Ymd') . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Lee el Excel de conteo y devuelve los valores para llenar la pantalla (el usuario revisa antes de procesar) */
    public function leerExcel(Request $request)
    {
        $this->autorizar();
        $request->validate(['archivo' => 'required|file|max:10240|mimes:xlsx,csv,txt']);

        try {
            $filas = ExcelLector::filas($request->file('archivo')->getRealPath(), $request->file('archivo')->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo leer el archivo: ' . $e->getMessage()], 422);
        }

        $cab = array_map(fn($t) => self::normalizar($t), $filas[0] ?? []);
        $col = fn(array $nombres) => collect($nombres)->map(fn($n) => array_search($n, $cab, true))->first(fn($i) => $i !== false);
        $cId = $col(['id']);
        $cCod = $col(['codigo', 'cod']);
        $cNom = $col(['producto', 'nombre', 'descripcion']);
        $cCant = $col(['stock fisico', 'conteo', 'cantidad', 'stock']);
        $cCosto = $col(['costo unitario', 'costo']);
        if ($cCant === null || ($cId === null && $cCod === null && $cNom === null)) {
            return response()->json(['message' => 'El Excel debe tener la columna "Stock físico" y una columna para identificar el producto (ID, Código o Producto). Usa la plantilla.'], 422);
        }

        $productos = Producto::where('id_empresa_negocio', Auth::user()->id_empresa_negocio)->whereIn('promocion', [0, 4])
            ->get(['IdProducto', 'procod', 'pronom']);
        $porId = $productos->keyBy('IdProducto');
        $porCodigo = $productos->filter(fn($p) => $p->procod !== '')->keyBy(fn($p) => mb_strtoupper(trim($p->procod)));
        $porNombre = $productos->keyBy(fn($p) => mb_strtoupper(trim($p->pronom)));

        $items = [];
        $errores = [];
        foreach (array_slice($filas, 1) as $i => $f) {
            $n = $i + 2;
            $cant = str_replace(',', '.', $f[$cCant] ?? '');
            if ($cant === '') {
                continue; // producto no contado
            }
            $prod = ($cId !== null ? $porId[(int) ($f[$cId] ?? 0)] ?? null : null)
                ?? ($cCod !== null ? $porCodigo[mb_strtoupper($f[$cCod] ?? '')] ?? null : null)
                ?? ($cNom !== null ? $porNombre[mb_strtoupper($f[$cNom] ?? '')] ?? null : null);

            if (!$prod) {
                $errores[] = "Fila {$n}: producto no encontrado (" . ($f[$cCod] ?? $f[$cNom] ?? $f[$cId] ?? '') . ').';
            } elseif (!is_numeric($cant) || $cant < 0) {
                $errores[] = "Fila {$n}: el stock físico \"{$cant}\" no es una cantidad válida.";
            } else {
                $costo = $cCosto !== null ? str_replace(',', '.', $f[$cCosto] ?? '') : '';
                $items[$prod->IdProducto] = ['cantidad' => (float) $cant, 'costo' => is_numeric($costo) ? (float) $costo : null];
            }
        }

        return response()->json(['items' => $items, 'errores' => $errores]);
    }

    private static function normalizar(string $t): string
    {
        $t = mb_strtolower(trim($t));
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '.' => '', '°' => '']);
    }
}
