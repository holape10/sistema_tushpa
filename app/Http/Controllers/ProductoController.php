<?php
namespace App\Http\Controllers;

use App\Models\{Almacen, Producto, ProductoPrecioDinamico, ProductoPresentacion, Categoria, Subcategoria, UnidadMedida, Combo};
use App\Support\{Excel, ExcelLector, Kardex};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $q = trim($request->get('q'));
        $tipo = $request->get('tipo', '');

        $productos = Producto::where('id_empresa_negocio', $sucursal)
            ->when($q, fn($query) => $query->where(fn($w) => $w->where('pronom', 'like', "%{$q}%")
                ->orWhere('procod', $q)->orWhere('codigo_barra', $q)))
            ->when($tipo !== '', fn($query) => $query->where('promocion', $tipo))
            ->orderBy('pronom')
            ->paginate(15)
            ->withQueryString();

        $almacenes = Almacen::where('id_empresa_negocio', $sucursal)->orderByDesc('predeterminado')->get();

        return view('empresas.productos.index', compact('productos', 'q', 'tipo', 'almacenes'));
    }

    public function create()
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        $unidades = UnidadMedida::orderBy('umenom')->get();

        // para armar combos: productos y preparados ya existentes
        $itemsParaCombo = Producto::where('id_empresa_negocio', $sucursal)
            ->whereIn('promocion', [0, 2]) // solo Producto y Preparado entran a un combo
            ->orderBy('pronom')->get();

        return view('empresas.productos.create', compact('categorias', 'subcategorias', 'unidades', 'itemsParaCombo'));
    }

    /** Reglas comunes de crear y editar; el precio de venta no aplica a insumos (no se venden) */
    private function reglas(bool $esInsumo): array
    {
        return [
            'pronom'             => 'required|string|max:150',
            'propun'             => $esInsumo ? 'nullable|numeric|min:0' : 'required|numeric|min:0.01',
            'costo'              => 'nullable|numeric|min:0',
            'debe'               => 'nullable|string|max:12|regex:/^[0-9A-Za-z]+$/',
            'haber'              => 'nullable|string|max:12|regex:/^[0-9A-Za-z]+$/',
            'codigo_barra'       => 'nullable|string|max:50',
            'umecod'             => 'nullable|exists:unidad_medida,umecod',
            'ume_equivalente'    => 'nullable|exists:unidad_medida,umecod',
            'factor_equivalente' => 'nullable|required_with:ume_equivalente|numeric|min:0.0001|max:99999999',
            'imagen'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'presentaciones'               => 'nullable|array|max:20',
            'presentaciones.*.id'          => 'nullable|integer',
            'presentaciones.*.umecod'      => 'required|exists:unidad_medida,umecod',
            'presentaciones.*.nombre'      => 'nullable|string|max:60',
            'presentaciones.*.factor'      => 'required|numeric|min:0.001|max:999999',
            'presentaciones.*.precio'      => 'required|numeric|min:0.01|max:999999',
            'presentaciones.*.codigo_barra' => 'nullable|string|max:50',
            'precios'               => 'nullable|array|max:50',
            'precios.*.dia'         => 'required|integer|between:0,7',
            'precios.*.hora_inicio' => 'required|date_format:H:i',
            'precios.*.hora_fin'    => 'required|date_format:H:i',
            'precios.*.precio'      => 'required|numeric|min:0.01|max:999999',
        ];
    }

    private const NOMBRES = [
        'pronom' => 'Nombre del producto', 'debe' => 'Cuenta Debe', 'haber' => 'Cuenta Haber', 'propun' => 'Precio de venta', 'promocion' => 'Tipo', 'codigo_barra' => 'Código de barras',
        'ume_equivalente' => 'Unidad equivalente', 'factor_equivalente' => 'Factor de equivalencia', 'imagen' => 'Imagen',
        'presentaciones.*.umecod' => 'Unidad de la presentación', 'presentaciones.*.factor' => 'Factor de la presentación',
        'presentaciones.*.precio' => 'Precio de la presentación', 'presentaciones.*.codigo_barra' => 'Código de barras de la presentación',
        'precios.*.dia' => 'Día del precio dinámico', 'precios.*.hora_inicio' => 'Hora inicio del precio dinámico',
        'precios.*.hora_fin' => 'Hora fin del precio dinámico', 'precios.*.precio' => 'Precio especial',
    ];

    /**
     * Un código de barras debe llevar a un solo producto o presentación de la sucursal
     * (tampoco puede ser el código interno de otro producto, porque las cajas buscan por ambos).
     */
    private function errorCodigos(Request $request, ?Producto $producto, bool $conPresentaciones): ?string
    {
        $codigos = collect([trim((string) $request->codigo_barra)]);
        if ($conPresentaciones) {
            $codigos = $codigos->merge(collect($request->presentaciones ?? [])->pluck('codigo_barra')->map(fn($c) => trim((string) $c)));
        }
        $codigos = $codigos->filter(fn($c) => $c !== '')->values();
        if ($codigos->isEmpty()) {
            return null;
        }
        if ($repetido = $codigos->duplicates()->first()) {
            return "El código de barras {$repetido} está repetido en este producto.";
        }

        $sucursal = Auth::user()->id_empresa_negocio;
        $otro = Producto::where('id_empresa_negocio', $sucursal)
            ->when($producto, fn($q) => $q->where('IdProducto', '!=', $producto->IdProducto))
            ->where(fn($q) => $q->whereIn('codigo_barra', $codigos)->orWhereIn('procod', $codigos))
            ->first(['pronom', 'codigo_barra', 'procod']);
        if ($otro) {
            $codigo = $codigos->contains($otro->codigo_barra) ? $otro->codigo_barra : $otro->procod;
            return "El código {$codigo} ya lo usa el producto {$otro->pronom}.";
        }

        $otraPres = DB::table('producto_presentacion as pp')->join('productos as p', 'p.IdProducto', '=', 'pp.IdProducto')
            ->where('p.id_empresa_negocio', $sucursal)->where('pp.estado', 1)->whereIn('pp.codigo_barra', $codigos)
            ->when($producto, fn($q) => $q->where('pp.IdProducto', '!=', $producto->IdProducto))
            ->first(['p.pronom', 'pp.nombre', 'pp.codigo_barra']);
        return $otraPres ? "El código {$otraPres->codigo_barra} ya lo usa la presentación {$otraPres->nombre} de {$otraPres->pronom}." : null;
    }

    /** Guarda la imagen en public/imagenes/productos/{RUC}/ y borra la anterior (no en public/productos: chocaría con la ruta /productos) */
    private function guardarImagen(Request $request, Producto $producto): void
    {
        $anterior = $producto->imagenproducto;
        if ($request->hasFile('imagen')) {
            $archivo = $request->file('imagen');
            $carpeta = 'imagenes/productos/' . preg_replace('/\D/', '', (string) Auth::user()->IdEmpresa);
            $nombre = $producto->IdProducto . '_' . uniqid() . '.' . strtolower($archivo->guessExtension() ?: 'jpg');
            $archivo->move(public_path($carpeta), $nombre);
            $producto->update(['imagenproducto' => $carpeta . '/' . $nombre]);
        } elseif ($request->boolean('quitar_imagen')) {
            $producto->update(['imagenproducto' => null]);
        } else {
            return;
        }
        if ($anterior && str_starts_with($anterior, 'imagenes/productos/') && is_file(public_path($anterior))) {
            @unlink(public_path($anterior));
        }
    }

    /**
     * Presentaciones (solo productos que se venden tal cual) y precios dinámicos (todo lo que se vende).
     * Las presentaciones se actualizan por id y las quitadas quedan inactivas, porque una proforma puede usarlas.
     */
    private function guardarExtras(Request $request, Producto $producto): void
    {
        $tipo = (int) $producto->promocion;

        $vigentes = [];
        if ($tipo === 0) {
            $unidades = UnidadMedida::pluck('umenom', 'umecod');
            foreach ($request->presentaciones ?? [] as $p) {
                $nombre = trim((string) ($p['nombre'] ?? '')) ?: ($unidades[$p['umecod']] ?? $p['umecod']);
                $datos = [
                    'umecod'       => $p['umecod'],
                    'nombre'       => mb_strtoupper($nombre),
                    'factor'       => round((float) $p['factor'], 3),
                    'precio'       => round((float) $p['precio'], 2),
                    'codigo_barra' => trim((string) ($p['codigo_barra'] ?? '')) ?: null,
                    'estado'       => true,
                ];
                $existente = !empty($p['id'])
                    ? ProductoPresentacion::where('id_presentacion', $p['id'])->where('IdProducto', $producto->IdProducto)->first()
                    : null;
                if ($existente) {
                    $existente->update($datos);
                } else {
                    $existente = ProductoPresentacion::create($datos + ['IdProducto' => $producto->IdProducto]);
                }
                $vigentes[] = $existente->id_presentacion;
            }
        }
        ProductoPresentacion::where('IdProducto', $producto->IdProducto)
            ->when($vigentes, fn($q) => $q->whereNotIn('id_presentacion', $vigentes))
            ->update(['estado' => false]);

        ProductoPrecioDinamico::where('IdProducto', $producto->IdProducto)->delete();
        if ($tipo !== 4) {
            foreach ($request->precios ?? [] as $r) {
                ProductoPrecioDinamico::create([
                    'IdProducto'  => $producto->IdProducto,
                    'dia'         => (int) $r['dia'],
                    'hora_inicio' => $r['hora_inicio'],
                    'hora_fin'    => $r['hora_fin'],
                    'precio'      => round((float) $r['precio'], 2),
                    'activo'      => !empty($r['activo']),
                ]);
            }
        }
    }

    /** Equivalencia solo para insumos: 1 KGM = 1000 GRM */
    private function equivalencia(Request $request, int $tipo): array
    {
        $usa = $tipo === 4 && $request->ume_equivalente && $request->ume_equivalente !== $request->umecod;
        return [
            'ume_equivalente'    => $usa ? $request->ume_equivalente : null,
            'factor_equivalente' => $usa ? (float) $request->factor_equivalente : 1,
        ];
    }

    private function esDeMiSucursal(Producto $producto): void
    {
        abort_unless((int) $producto->id_empresa_negocio === (int) Auth::user()->id_empresa_negocio, 404);
    }

    public function store(Request $request)
    {
        $request->validate(['promocion' => 'required|in:0,2,4,6'] + $this->reglas((int) $request->promocion === 4), [], self::NOMBRES);

        if ($request->promocion == 6 && empty(array_filter((array) $request->combo_items))) {
            return back()->withInput()->withErrors(['combo_items' => 'Un combo debe tener al menos un producto o preparado dentro.']);
        }
        if ($error = $this->errorCodigos($request, null, (int) $request->promocion === 0)) {
            return back()->withInput()->withErrors(['codigo_barra' => $error]);
        }

        DB::transaction(function () use ($request) {
            $tipo = (int) $request->promocion;
            $producto = Producto::create([
                'procod'             => $request->procod ?: 'P'.time(),
                'codigo_barra'       => trim((string) $request->codigo_barra) ?: null,
                'pronom'             => $request->pronom,
                'umecod'             => $request->umecod ?: 'NIU',
                'costo'              => $request->costo ?: 0,
                'propun'             => $tipo === 4 ? ($request->propun ?: 0) : $request->propun,
                'promocion'          => $tipo,
                'cat_id'             => $request->cat_id,
                'subcat_id'          => $request->subcat_id,
                'stock_min'          => $request->stock_min ?: 0,
                'debe'               => trim((string) $request->debe) ?: null,
                'haber'              => trim((string) $request->haber) ?: null,
                'control_lote'       => in_array($tipo, [0, 4]) && $request->boolean('control_lote'),
                'es_combustible'     => $tipo === 0 && $request->boolean('es_combustible'),
                'proest'             => 'Activo',
                'IdEmpresa'          => Auth::user()->IdEmpresa,
                'id_empresa_negocio' => Auth::user()->id_empresa_negocio,
            ] + $this->equivalencia($request, $tipo));

            // si es COMBO, guardamos qué productos/preparados lleva dentro
            if ($tipo === 6 && !empty($request->combo_items)) {
                foreach ($request->combo_items as $itemId => $cantidad) {
                    if (!empty($cantidad) && $cantidad > 0) {
                        Combo::create([
                            'IdProducto_rel'  => $producto->IdProducto,
                            'IdProducto_comb' => $itemId,
                            'prod_comb_cant'  => $cantidad,
                        ]);
                    }
                }
            }

            $this->guardarExtras($request, $producto);
            $this->guardarImagen($request, $producto);
        });

        return redirect()->route('productos.index')->with('success', 'Registrado correctamente.');
    }

    public function edit(Producto $producto)
    {
        $this->esDeMiSucursal($producto);
        $sucursal = Auth::user()->id_empresa_negocio;
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get();
        $subcategorias = Subcategoria::where('id_empresa_negocio', $sucursal)->get();
        $unidades = UnidadMedida::orderBy('umenom')->get();
        $itemsParaCombo = Producto::where('id_empresa_negocio', $sucursal)
            ->whereIn('promocion', [0, 2])
            ->where('IdProducto', '!=', $producto->IdProducto)
            ->orderBy('pronom')->get();
        $comboActual = $producto->itemsCombo()->pluck('prod_comb_cant', 'IdProducto_comb');

        return view('empresas.productos.edit', compact('producto', 'categorias', 'subcategorias', 'unidades', 'itemsParaCombo', 'comboActual'));
    }

    public function update(Request $request, Producto $producto)
    {
        $this->esDeMiSucursal($producto);
        $tipo = (int) $producto->promocion;
        $request->validate($this->reglas($tipo === 4), [], self::NOMBRES);

        if ($error = $this->errorCodigos($request, $producto, $tipo === 0)) {
            return back()->withInput()->withErrors(['codigo_barra' => $error]);
        }

        DB::transaction(function () use ($request, $producto, $tipo) {
            $producto->update($request->only(['pronom', 'umecod', 'costo', 'cat_id', 'subcat_id', 'stock_min', 'proest'])
                + ['propun' => $tipo === 4 ? ($request->propun ?: 0) : $request->propun,
                   'codigo_barra' => trim((string) $request->codigo_barra) ?: null,
                   'debe' => trim((string) $request->debe) ?: null, 'haber' => trim((string) $request->haber) ?: null,
                   'control_lote' => in_array($tipo, [0, 4]) && $request->boolean('control_lote'),
                   'es_combustible' => $tipo === 0 && $request->boolean('es_combustible')]
                + $this->equivalencia($request, $tipo));

            if ($tipo === 6) {
                $producto->itemsCombo()->delete();
                foreach ($request->combo_items ?? [] as $itemId => $cantidad) {
                    if (!empty($cantidad) && $cantidad > 0) {
                        Combo::create([
                            'IdProducto_rel'  => $producto->IdProducto,
                            'IdProducto_comb' => $itemId,
                            'prod_comb_cant'  => $cantidad,
                        ]);
                    }
                }
            }

            $this->guardarExtras($request, $producto);
            $this->guardarImagen($request, $producto);
        });

        return redirect()->route('productos.index')->with('success', 'Actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        $this->esDeMiSucursal($producto);
        $producto->itemsCombo()->delete();
        ProductoPresentacion::where('IdProducto', $producto->IdProducto)->delete();
        ProductoPrecioDinamico::where('IdProducto', $producto->IdProducto)->delete();
        if ($producto->imagenproducto && str_starts_with($producto->imagenproducto, 'imagenes/productos/') && is_file(public_path($producto->imagenproducto))) {
            @unlink(public_path($producto->imagenproducto));
        }
        $producto->delete();
        return back()->with('success', 'Eliminado.');
    }

    // ------------------------------------------------------------------
    // Excel: plantilla, exportación e importación de productos
    // ------------------------------------------------------------------

    private const TIPOS = [0 => 'Producto', 4 => 'Insumo', 2 => 'Preparado', 6 => 'Combo'];
    private const COLUMNAS = ['Código', 'Nombre', 'Tipo', 'Categoría', 'Unidad', 'Costo', 'Precio venta', 'Stock mínimo', 'Estado', 'Stock inicial', 'Control lote'];

    private function descargar(string $ruta, string $nombre)
    {
        return response()->download($ruta, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
            ->deleteFileAfterSend();
    }

    /** Hojas de ayuda comunes a la plantilla y la exportación */
    private function hojasAyuda(Excel $x): Excel
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        return $x
            ->hoja('Instrucciones', ['Columna', 'Obligatorio', 'Detalle'], [
                ['Código', 'No', 'Si ya existe un producto con ese código se ACTUALIZA; si no, se crea. Vacío: se busca por nombre o se genera un código.'],
                ['Nombre', 'Sí', 'Nombre del producto (máx. 150 caracteres).'],
                ['Tipo', 'No', 'Producto, Insumo o Preparado (por defecto Producto). Los combos se crean desde el sistema.'],
                ['Categoría', 'No', 'Nombre de la categoría. Si no existe, se crea.'],
                ['Unidad', 'No', 'Código SUNAT (NIU, KGM, LTR...) o nombre de la unidad. Ver hoja Unidades. Por defecto NIU.'],
                ['Costo', 'No', 'Costo unitario de compra.'],
                ['Precio venta', 'Sí', 'Precio de venta con IGV. Obligatorio (mayor a 0) para Producto y Preparado.'],
                ['Stock mínimo', 'No', 'Cantidad para la alerta de stock bajo.'],
                ['Estado', 'No', 'Activo o Inactivo (por defecto Activo).'],
                ['Control lote', 'No', 'Sí = farmacia: al comprar pide lote y vencimiento y al vender sale primero el que vence antes. Vacío = no cambia.'],
                ['Stock inicial', 'No', 'Solo Producto e Insumo, y solo si el producto aún no tiene movimientos en el almacén elegido al importar. Para corregir stock existente usa Almacén > Inventarios.'],
            ])
            ->hoja('Categorías', ['Categorías registradas'],
                Categoria::where('id_empresa_negocio', $sucursal)->orderBy('cat_nom')->pluck('cat_nom')->map(fn($c) => [$c])->all())
            ->hoja('Unidades', ['Código', 'Unidad'],
                UnidadMedida::orderBy('umenom')->get(['umecod', 'umenom'])->map(fn($u) => [$u->umecod, $u->umenom])->all());
    }

    public function plantilla()
    {
        $x = (new Excel())->hoja('Productos', self::COLUMNAS, [
            ['P0001', 'PARACETAMOL 500MG X 100 TAB', 'Producto', 'ANALGESICOS', 'NIU', 6.50, 12.00, 5, 'Activo', 24, 'Sí'],
            ['', 'GASEOSA INCA KOLA 500ML', 'Producto', 'BEBIDAS', 'NIU', 1.80, 3.50, 5, 'Activo', 24, 'No'],
            ['', 'POLLO ENTERO', 'Insumo', 'INSUMOS', 'KGM', 9.50, 0, 2, 'Activo', 10, ''],
        ]);
        return $this->descargar($this->hojasAyuda($x)->guardar(), 'plantilla_productos.xlsx');
    }

    /** Exporta los productos con el mismo formato de la plantilla (se puede editar y volver a importar) */
    public function exportar(Request $request)
    {
        $sucursal = Auth::user()->id_empresa_negocio;
        $almacen = Kardex::almacenPredeterminado($sucursal);
        $tipo = (string) $request->get('tipo', '');

        $filas = Producto::where('productos.id_empresa_negocio', $sucursal)
            ->leftJoin('categorias as c', 'c.cat_id', '=', 'productos.cat_id')
            ->leftJoin('producto_stock as ps', function ($j) use ($almacen) {
                $j->on('ps.IdProducto', '=', 'productos.IdProducto')->where('ps.id_almacen', $almacen?->id_almacen ?? 0);
            })
            ->when($tipo !== '', fn($q) => $q->where('productos.promocion', $tipo))
            ->orderBy('productos.pronom')
            ->get(['productos.*', 'c.cat_nom', DB::raw('ps.stock as stock_actual')])
            ->map(fn($p) => [
                (string) $p->procod, $p->pronom, self::TIPOS[(int) $p->promocion] ?? 'Producto', $p->cat_nom ?? '', $p->umecod,
                (float) $p->costo, (float) $p->propun, (float) $p->stock_min, $p->proest,
                in_array((int) $p->promocion, [0, 4]) ? (float) ($p->stock_actual ?? 0) : '',
                $p->control_lote ? 'Sí' : 'No',
            ])->all();

        // La última columna trae el stock actual del almacén predeterminado
        $columnas = self::COLUMNAS;
        $columnas[9] = 'Stock ' . ($almacen?->descripcion ?? '');
        $x = (new Excel())->hoja('Productos', $columnas, $filas);
        return $this->descargar($this->hojasAyuda($x)->guardar(), 'productos_' . now()->format('Ymd_His') . '.xlsx');
    }

    /** Número del Excel: acepta "3.50", "3,50", "1,250.00" y "S/ 3.50"; null si está vacío, false si no es número */
    private static function numero(string $v): float|false|null
    {
        $v = str_replace([' ', 'S/', 's/'], '', $v);
        $v = str_contains($v, ',') && !str_contains($v, '.') ? str_replace(',', '.', $v) : str_replace(',', '', $v);
        return $v === '' ? null : (is_numeric($v) ? (float) $v : false);
    }

    private static function normalizar(string $t): string
    {
        return strtr(mb_strtolower(trim($t)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    }

    public function importar(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->esAdmin(), 403, 'Solo el Administrador importa productos.');
        $request->validate([
            'archivo'    => 'required|file|max:10240|mimes:xlsx,csv,txt',
            'id_almacen' => 'nullable|integer',
        ], [], ['archivo' => 'Archivo Excel']);

        $sucursal = $user->id_empresa_negocio;
        $almacen = $request->id_almacen
            ? Almacen::where('id_almacen', $request->id_almacen)->where('id_empresa_negocio', $sucursal)->first()
            : Kardex::almacenPredeterminado($sucursal);

        try {
            $filas = ExcelLector::filas($request->file('archivo')->getRealPath(), $request->file('archivo')->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo: ' . $e->getMessage()]);
        }

        // Ubica cada columna por su título (el orden no importa; "Stock ALMACEN PRINCIPAL" de la exportación cuenta como stock)
        $cab = array_map(fn($t) => self::normalizar($t), $filas[0] ?? []);
        $col = function (array $nombres) use ($cab) {
            foreach ($nombres as $n) {
                foreach ($cab as $i => $t) {
                    if ($t === $n || str_starts_with($t, $n . ' ')) {
                        return $i;
                    }
                }
            }
            return null;
        };
        $c = [
            'cod' => $col(['codigo']), 'nom' => $col(['nombre', 'producto']), 'tipo' => $col(['tipo']),
            'cat' => $col(['categoria']), 'ume' => $col(['unidad']), 'costo' => $col(['costo']),
            'precio' => $col(['precio venta', 'precio']), 'min' => $col(['stock minimo']), 'est' => $col(['estado']),
            'lote' => $col(['control lote']),
            // "Stock inicial" de la plantilla o "Stock <almacén>" de la exportación (nunca "Stock mínimo")
            'stock' => $col(['stock inicial']) ?? collect($cab)->search(fn($t) => str_starts_with($t, 'stock') && $t !== 'stock minimo'),
        ];
        $c['stock'] = $c['stock'] === false ? null : $c['stock'];
        if ($c['nom'] === null || $c['precio'] === null) {
            return back()->withErrors(['archivo' => 'El Excel debe tener al menos las columnas "Nombre" y "Precio venta". Descarga la plantilla.']);
        }
        if (count($filas) < 2) {
            return back()->withErrors(['archivo' => 'El Excel no tiene productos.']);
        }

        $tipos = ['producto' => 0, 'insumo' => 4, 'preparado' => 2];
        $unidades = UnidadMedida::get(['umecod', 'umenom']);
        $porCodUme = $unidades->keyBy(fn($u) => mb_strtoupper($u->umecod));
        $porNomUme = $unidades->keyBy(fn($u) => mb_strtoupper($u->umenom));
        $categorias = Categoria::where('id_empresa_negocio', $sucursal)->get()->keyBy(fn($x) => mb_strtoupper(trim((string) $x->cat_nom)));
        $existentes = Producto::where('id_empresa_negocio', $sucursal)->get();
        $porCodigo = $existentes->filter(fn($p) => $p->procod !== '')->keyBy(fn($p) => mb_strtoupper(trim($p->procod)))->all();
        $porNombre = $existentes->keyBy(fn($p) => mb_strtoupper(trim($p->pronom)))->all();
        $v = fn(array $f, string $k) => $c[$k] === null ? '' : trim((string) ($f[$c[$k]] ?? ''));

        $errores = [];
        $creados = $actualizados = 0;
        $stockInicial = [];

        DB::transaction(function () use ($filas, $v, $tipos, $porCodUme, $porNomUme, &$categorias, &$porCodigo, &$porNombre,
            $user, $sucursal, $almacen, &$errores, &$creados, &$actualizados, &$stockInicial) {

            foreach (array_slice($filas, 1) as $i => $f) {
                $n = $i + 2;
                if (implode('', $f) === '') {
                    continue;
                }
                $nombre = mb_strtoupper(preg_replace('/\s+/u', ' ', $v($f, 'nom')));
                $codigo = mb_substr($v($f, 'cod'), 0, 20);
                if ($nombre === '') {
                    $errores[] = "Fila {$n}: falta el nombre.";
                    continue;
                }

                $tipoTxt = self::normalizar($v($f, 'tipo'));
                if ($tipoTxt === 'combo') {
                    $errores[] = "Fila {$n} ({$nombre}): los combos se crean y editan desde el sistema; se omitió.";
                    continue;
                }
                if ($tipoTxt !== '' && !isset($tipos[$tipoTxt])) {
                    $errores[] = "Fila {$n} ({$nombre}): tipo \"{$v($f, 'tipo')}\" no válido (Producto, Insumo o Preparado).";
                    continue;
                }

                $costo = self::numero($v($f, 'costo'));
                $precio = self::numero($v($f, 'precio'));
                $min = self::numero($v($f, 'min'));
                $stock = self::numero($v($f, 'stock'));
                if ($costo === false || $precio === false || $min === false || $stock === false) {
                    $errores[] = "Fila {$n} ({$nombre}): costo, precio, stock mínimo y stock deben ser números.";
                    continue;
                }

                $producto = ($codigo !== '' ? $porCodigo[mb_strtoupper($codigo)] ?? null : null) ?? $porNombre[$nombre] ?? null;
                if ($producto && (int) $producto->promocion === 6) {
                    $errores[] = "Fila {$n} ({$nombre}): es un combo; los combos se editan desde el sistema.";
                    continue;
                }
                $tipo = $tipoTxt !== '' ? $tipos[$tipoTxt] : ($producto ? (int) $producto->promocion : 0);
                $precioFinal = $precio ?? ($producto ? (float) $producto->propun : 0);
                if (in_array($tipo, [0, 2]) && $precioFinal <= 0) {
                    $errores[] = "Fila {$n} ({$nombre}): el precio de venta debe ser mayor a 0.";
                    continue;
                }

                // Unidad por código SUNAT o por nombre
                $umeTxt = mb_strtoupper($v($f, 'ume'));
                $ume = $umeTxt === '' ? null : ($porCodUme[$umeTxt] ?? $porNomUme[$umeTxt] ?? null);
                if ($umeTxt !== '' && !$ume) {
                    $errores[] = "Fila {$n} ({$nombre}): la unidad \"{$umeTxt}\" no existe (ver hoja Unidades).";
                    continue;
                }

                // Categoría por nombre; se crea si no existe
                $catId = $producto?->cat_id;
                $catTxt = mb_strtoupper(mb_substr($v($f, 'cat'), 0, 50));
                if ($catTxt !== '') {
                    if (!isset($categorias[$catTxt])) {
                        $categorias[$catTxt] = Categoria::create(['cat_nom' => $catTxt, 'IdEmpresa' => $user->IdEmpresa,
                            'id_empresa_negocio' => $sucursal, 'visible' => 1]);
                    }
                    $catId = $categorias[$catTxt]->cat_id;
                }

                $estado = self::normalizar($v($f, 'est'));
                $datos = [
                    'pronom'    => mb_substr($nombre, 0, 150),
                    'promocion' => $tipo,
                    'cat_id'    => $catId,
                    'umecod'    => $ume->umecod ?? ($producto->umecod ?? 'NIU'),
                    'costo'     => $costo ?? ($producto->costo ?? 0),
                    'propun'    => $precioFinal,
                    'stock_min' => $min ?? ($producto->stock_min ?? 0),
                    'control_lote' => in_array($tipo, [0, 4]) && ($v($f, 'lote') === '' ? (bool) ($producto->control_lote ?? false)
                        : in_array(self::normalizar($v($f, 'lote')), ['si', 's', '1', 'x', 'verdadero'])),
                    'proest'    => $estado === '' ? ($producto->proest ?? 'Activo') : ($estado === 'inactivo' ? 'Inactivo' : 'Activo'),
                ];

                if ($producto) {
                    if ($codigo !== '') {
                        $datos['procod'] = $codigo;
                    }
                    $producto->update($datos);
                    $actualizados++;
                } else {
                    $producto = Producto::create($datos + [
                        'procod' => $codigo !== '' ? $codigo : mb_substr('P' . time() . $n, 0, 20),
                        'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $sucursal,
                    ]);
                    $creados++;
                }
                $porNombre[mb_strtoupper($producto->pronom)] = $producto;
                $porCodigo[mb_strtoupper($producto->procod)] = $producto;

                // Stock inicial: solo productos e insumos que aún no tienen kardex en el almacén
                if ($stock !== null && $stock > 0 && in_array($tipo, [0, 4])) {
                    if (!$almacen) {
                        $errores[] = "Fila {$n} ({$nombre}): no hay almacén para cargar el stock inicial.";
                    } elseif (DB::table('movimientos_productos')->where('IdProducto', $producto->IdProducto)->where('id_almacen', $almacen->id_almacen)->exists()) {
                        $errores[] = "Fila {$n} ({$nombre}): ya tiene movimientos en {$almacen->descripcion}; su stock no se cambió (usa Inventarios).";
                    } else {
                        $stockInicial[$producto->IdProducto] = ['IdProducto' => $producto->IdProducto, 'cantidad' => $stock, 'costo' => null];
                    }
                }
            }

            if ($stockInicial) {
                $productos = Producto::whereIn('IdProducto', array_keys($stockInicial))->get()->keyBy('IdProducto');
                InventarioController::procesar($almacen, now()->toDateString(), 'STOCK INICIAL - IMPORTACIÓN DE PRODUCTOS',
                    'PRODUCTOS', array_values($stockInicial), $productos);
            }
        });

        $mensaje = "Importación terminada: {$creados} productos creados y {$actualizados} actualizados";
        $mensaje .= $stockInicial ? '; stock inicial cargado a ' . count($stockInicial) . " productos en {$almacen->descripcion}." : '.';

        return redirect()->route('productos.index')->with('success', $mensaje)->with('import_errores', $errores);
    }
}