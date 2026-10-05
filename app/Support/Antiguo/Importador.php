<?php
namespace App\Support\Antiguo;

use App\Http\Controllers\InventarioController;
use App\Models\{Categoria, Combo, Producto, ProductoPrecioDinamico, ProductoPresentacion, Subcategoria, User};
use App\Support\Kardex;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Pasa los datos maestros del sistema antiguo (una base ya cargada) a la sucursal del usuario.
 * Lee siempre por nombre de columna (cada respaldo antiguo tiene las columnas en distinto orden y algunas faltan).
 * Se puede correr varias veces: busca lo que ya existe (nombre + tipo, documento) y solo crea o completa.
 * El procod antiguo NO es único (PROD0001 se repite por categoría): se conserva solo si está libre; si no, A{IdProducto}.
 *
 * Diferencias del modelo antiguo que se traducen aquí:
 *  - promocion: 0/1 producto, 2 preparado, 3 combo, 4 insumo  →  nuevo: 0 producto, 2 preparado, 6 combo, 4 insumo
 *  - las presentaciones eran filas de productos con tipo = 2, pro_rel = producto padre y factor; o la tabla presentaciones
 *  - umecod_cons / factor_cons de los insumos  →  unidad equivalente (1 KG = 1000 GR)
 *  - precios_dia_semana  →  precios dinámicos por día y hora
 */
class Importador
{
    public const CONEXION = 'antiguo';

    public const SECCIONES = [
        'categorias'  => 'Categorías y subcategorías',
        'productos'   => 'Productos, presentaciones, códigos de barras, precios dinámicos y combos',
        'stock'       => 'Stock actual (como inventario inicial)',
        'clientes'    => 'Clientes',
        'proveedores' => 'Proveedores',
        'medios'      => 'Medios de pago y formas de pago',
        'mesas'       => 'Pisos y mesas',
    ];

    private const TIPOS = [0 => 0, 1 => 0, 2 => 2, 3 => 6, 4 => 4, 6 => 6];
    private const IMAGENES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private array $columnas = [];
    private array $reporte = [];
    private array $avisos = [];
    /** IdProducto antiguo => nuevo */
    private array $mapa = [];
    private array $mapaCat = [];
    private array $mapaSub = [];

    public static function conectar(string $bd): void
    {
        config(['database.connections.' . self::CONEXION => array_merge(config('database.connections.mysql'), ['database' => $bd])]);
        DB::purge(self::CONEXION);
        DB::connection(self::CONEXION)->getPdo();
    }

    private function src(string $tabla)
    {
        return DB::connection(self::CONEXION)->table($tabla);
    }

    private function hay(string $tabla): bool
    {
        return $this->cols($tabla) !== [];
    }

    private function cols(string $tabla): array
    {
        return $this->columnas[$tabla] ??= array_map('strtolower', Schema::connection(self::CONEXION)->getColumnListing($tabla));
    }

    private function col(string $tabla, string $col): bool
    {
        return in_array(strtolower($col), $this->cols($tabla), true);
    }

    /** Valor de una fila antigua aunque la columna no exista en ese respaldo */
    private static function v(object $fila, string $col, $defecto = null)
    {
        return property_exists($fila, $col) && $fila->$col !== null ? $fila->$col : $defecto;
    }

    /** Filtra por la sucursal antigua (las filas sin sucursal también cuentan) */
    private function deSucursal($q, string $tabla, int $suc)
    {
        return $this->col($tabla, 'id_empresa_negocio')
            ? $q->where(fn($w) => $w->where('id_empresa_negocio', $suc)->orWhereNull('id_empresa_negocio'))
            : $q;
    }

    // ------------------------------------------------------------------ resumen

    public function resumen(): array
    {
        $contar = fn(string $t, ?callable $f = null) => $this->hay($t) ? ($f ? $f($this->src($t)) : $this->src($t))->count() : null;
        return [
            'Productos' => $contar('productos', fn($q) => $this->col('productos', 'tipo') ? $q->where(fn($w) => $w->where('tipo', '!=', 2)->orWhereNull('tipo')) : $q),
            'Presentaciones' => ($this->col('productos', 'tipo') ? $this->src('productos')->where('tipo', 2)->count() : 0) + ($contar('presentaciones') ?? 0),
            'Categorías' => $contar('categorias'),
            'Clientes' => $contar('cliente'),
            'Proveedores' => $contar('proveedor'),
            'Precios por día' => $contar('precios_dia_semana'),
            'Medios de pago' => $contar('medios_pagos'),
            'Mesas' => $contar('mesas'),
        ];
    }

    public function sucursales(): array
    {
        if (!$this->hay('empresa_negocios')) {
            return [['id' => 1, 'nombre' => 'Sucursal 1', 'ruc' => null]];
        }
        return $this->src('empresa_negocios')->orderBy('id_empresa_negocio')->get()
            ->map(fn($s) => ['id' => (int) $s->id_empresa_negocio, 'nombre' => self::v($s, 'nombre_comercial') ?: 'Sucursal ' . $s->id_empresa_negocio,
                             'ruc' => self::v($s, 'IdEmpresa')])->all();
    }

    public function almacenes(int $suc): array
    {
        if (!$this->hay('almacenes')) {
            return [];
        }
        return $this->deSucursal($this->src('almacenes'), 'almacenes', $suc)->orderByDesc('predeterminado')->get()
            ->map(fn($a) => ['id' => (int) $a->id_almacen, 'nombre' => self::v($a, 'descripcion') ?: 'Almacén ' . $a->id_almacen])->all();
    }

    // ------------------------------------------------------------------ importar

    /**
     * @param array $opc secciones[], dia0 ('domingo'|'lunes'), almacen (id antiguo), imagenes (ruta a un .zip o null)
     */
    public function importar(User $user, int $suc, array $opc): array
    {
        $secciones = $opc['secciones'] ?? [];
        $rucAntiguo = collect($this->sucursales())->firstWhere('id', $suc)['ruc'] ?? null;

        foreach (array_keys(self::SECCIONES) as $s) {
            if (!in_array($s, $secciones, true)) {
                continue;
            }
            $this->reporte[$s] = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0];
            try {
                DB::transaction(fn() => match ($s) {
                    'categorias'  => $this->categorias($user, $suc),
                    'productos'   => $this->productos($user, $suc, $opc),
                    'stock'       => $this->stock($user, $suc, $opc),
                    'clientes'    => $this->clientes($user, $rucAntiguo),
                    'proveedores' => $this->proveedores($user, $rucAntiguo),
                    'medios'      => $this->medios($user, $suc, $rucAntiguo),
                    'mesas'       => $this->mesas($user, $suc),
                });
            } catch (\Throwable $e) {
                report($e);
                $this->aviso(self::SECCIONES[$s] . ': no se importó por un error — ' . mb_substr($e->getMessage(), 0, 250));
                $this->reporte[$s] = ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'error' => true];
            }
        }

        return ['secciones' => $this->reporte, 'avisos' => $this->avisos];
    }

    private function sumar(string $s, string $k, int $n = 1): void
    {
        $this->reporte[$s][$k] = ($this->reporte[$s][$k] ?? 0) + $n;
    }

    private function aviso(string $texto): void
    {
        if (count($this->avisos) < 300) {
            $this->avisos[] = $texto;
        }
    }

    private static function clave(?string $t): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $t)));
    }

    // ---------- Categorías ----------
    private function categorias(User $user, int $suc): void
    {
        $this->cargarMapaCategorias($user, $suc, true);
    }

    /** Relaciona categorías antiguas con las nuevas por nombre; $crear = crea las que faltan */
    private function cargarMapaCategorias(User $user, int $suc, bool $crear): void
    {
        if (!$this->hay('categorias')) {
            return;
        }
        $nuevaSuc = $user->id_empresa_negocio;
        $existentes = Categoria::where('id_empresa_negocio', $nuevaSuc)->get()->keyBy(fn($c) => self::clave($c->cat_nom));
        foreach ($this->deSucursal($this->src('categorias'), 'categorias', $suc)->get() as $c) {
            $nombre = mb_substr(self::clave($c->cat_nom), 0, 50);
            if ($nombre === '') {
                continue;
            }
            if (isset($existentes[$nombre])) {
                $this->mapaCat[$c->cat_id] = $existentes[$nombre]->cat_id;
                $crear && $this->sumar('categorias', 'omitidos');
                continue;
            }
            if (!$crear) {
                continue;
            }
            $nueva = Categoria::create(['cat_nom' => $nombre, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc,
                'color' => self::v($c, 'color'), 'visible' => (int) self::v($c, 'visible', 1)]);
            $existentes[$nombre] = $nueva;
            $this->mapaCat[$c->cat_id] = $nueva->cat_id;
            $this->sumar('categorias', 'creados');
        }

        if (!$this->hay('subcategorias')) {
            return;
        }
        $subs = Subcategoria::where('id_empresa_negocio', $nuevaSuc)->get()->keyBy(fn($s) => self::clave($s->subcat_nom) . '|' . $s->cat_id);
        foreach ($this->deSucursal($this->src('subcategorias'), 'subcategorias', $suc)->get() as $s) {
            $nombre = mb_substr(self::clave($s->subcat_nom), 0, 50);
            $cat = $this->mapaCat[self::v($s, 'cat_id')] ?? null;
            if ($nombre === '') {
                continue;
            }
            $k = $nombre . '|' . $cat;
            if (isset($subs[$k])) {
                $this->mapaSub[$s->subcat_id] = $subs[$k]->subcat_id;
                continue;
            }
            if ($crear) {
                $nueva = Subcategoria::create(['subcat_nom' => $nombre, 'cat_id' => $cat, 'id_empresa_negocio' => $nuevaSuc,
                    'IdEmpresa' => $user->IdEmpresa, 'color' => self::v($s, 'color')]);
                $subs[$k] = $nueva;
                $this->mapaSub[$s->subcat_id] = $nueva->subcat_id;
                $this->sumar('categorias', 'creados');
            }
        }
    }

    // ---------- Productos ----------
    private function productos(User $user, int $suc, array $opc): void
    {
        if (!$this->hay('productos')) {
            $this->aviso('La base antigua no tiene la tabla productos.');
            return;
        }
        $nuevaSuc = $user->id_empresa_negocio;
        if (!$this->mapaCat) {
            $this->cargarMapaCategorias($user, $suc, false);
        }

        $unidades = DB::table('unidad_medida')->pluck('umenom', 'umecod');
        $imagenes = $this->indiceImagenes($opc['imagenes'] ?? null);
        $carpeta = 'imagenes/productos/' . preg_replace('/\D/', '', (string) $user->IdEmpresa);

        $existentes = Producto::where('id_empresa_negocio', $nuevaSuc)->get();
        $porCodigo = $existentes->filter(fn($p) => $p->procod !== '')->keyBy(fn($p) => self::clave($p->procod))->all();
        // Mismo nombre y mismo tipo = mismo producto (ALGARROBINA trago y ALGARROBINA insumo son distintos)
        $porNombre = $existentes->keyBy(fn($p) => self::clave($p->pronom) . '|' . (int) $p->promocion)->all();
        // Códigos de barras ya usados en la sucursal (no se repiten entre productos ni presentaciones)
        $usados = array_fill_keys(array_filter(array_merge(
            $existentes->pluck('codigo_barra')->all(),
            DB::table('producto_presentacion as pp')->join('productos as p', 'p.IdProducto', '=', 'pp.IdProducto')
                ->where('p.id_empresa_negocio', $nuevaSuc)->where('pp.estado', 1)->pluck('pp.codigo_barra')->all()
        )), true);
        $barraLibre = function (?string $c, ?int $deProducto = null) use (&$usados, $porCodigo) {
            $c = trim((string) $c);
            if ($c === '' || isset($usados[$c]) || (isset($porCodigo[self::clave($c)]) && $porCodigo[self::clave($c)]->IdProducto !== $deProducto)) {
                return null;
            }
            $usados[$c] = true;
            return mb_substr($c, 0, 50);
        };

        $codigosExtra = $this->hay('producto_codigo')
            ? $this->src('producto_codigo')->whereNotNull('cod_bar')->where('cod_bar', '!=', '')->orderBy('pro_cod_id')->get()->groupBy('IdProducto')
            : collect();

        $filas = $this->deSucursal($this->src('productos'), 'productos', $suc)->orderBy('IdProducto')->get();
        $principales = $filas->filter(fn($p) => (int) self::v($p, 'tipo', 1) !== 2);
        $presentacionesFila = $filas->filter(fn($p) => (int) self::v($p, 'tipo', 1) === 2);

        foreach ($principales as $a) {
            $nombre = mb_substr(self::clave($a->pronom), 0, 150);
            if ($nombre === '') {
                continue;
            }
            $tipo = self::TIPOS[(int) self::v($a, 'promocion', 0)] ?? 0;
            $codigo = mb_substr(trim((string) self::v($a, 'procod', '')), 0, 20);
            $prod = $porNombre[$nombre . '|' . $tipo] ?? null;

            $ume = strtoupper((string) self::v($a, 'umecod', 'NIU'));
            $ume = isset($unidades[$ume]) ? $ume : 'NIU';
            $umeCons = strtoupper((string) self::v($a, 'umecod_cons', ''));
            $factorCons = (float) self::v($a, 'factor_cons', 0);
            $estado = self::clave(self::v($a, 'proest', 'Activo')) === 'INACTIVO' ? 'Inactivo' : 'Activo';

            $datos = [
                'pronom' => $nombre, 'umecod' => $ume, 'promocion' => $tipo,
                'costo' => (float) self::v($a, 'costo', 0), 'propun' => (float) self::v($a, 'propun', 0),
                'cat_id' => $this->mapaCat[self::v($a, 'cat_id')] ?? ($prod->cat_id ?? null),
                'subcat_id' => $this->mapaSub[self::v($a, 'subcat_id')] ?? ($prod->subcat_id ?? null),
                'stock_min' => (float) self::v($a, 'stock_min', 0), 'proest' => $estado,
                'control_lote' => in_array($tipo, [0, 4]) && (int) self::v($a, 'requiere_lote_vencimiento', 0) === 1,
                'ume_equivalente' => $tipo === 4 && isset($unidades[$umeCons]) && $umeCons !== $ume && $factorCons > 0 ? $umeCons : null,
                'factor_equivalente' => $tipo === 4 && isset($unidades[$umeCons]) && $umeCons !== $ume && $factorCons > 0 ? $factorCons : 1,
            ];

            if ($prod) {
                $prod->update($datos);
                $this->sumar('productos', 'actualizados');
            } else {
                // Código antiguo si nadie lo usa en la sucursal; si está repetido, uno propio que lo identifica
                $nuevoCodigo = $codigo !== '' && !isset($porCodigo[self::clave($codigo)]) ? $codigo : 'A' . $a->IdProducto;
                if (isset($porCodigo[self::clave($nuevoCodigo)])) {
                    $nuevoCodigo = 'A' . $a->IdProducto . '-' . substr(uniqid(), -4);
                }
                $prod = Producto::create($datos + [
                    'procod' => $nuevoCodigo, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc,
                ]);
                $this->sumar('productos', 'creados');
            }
            $porCodigo[self::clave($prod->procod)] = $prod;
            $porNombre[$nombre . '|' . $tipo] = $prod;
            $this->mapa[$a->IdProducto] = $prod;

            // Código de barras: el del producto o el primero de producto_codigo
            if (!$prod->codigo_barra) {
                $barra = $barraLibre(self::v($a, 'codigo_barra'), $prod->IdProducto);
                foreach ($codigosExtra[$a->IdProducto] ?? [] as $c) {
                    $barra ??= $barraLibre($c->cod_bar, $prod->IdProducto);
                }
                if ($barra) {
                    $prod->update(['codigo_barra' => $barra]);
                }
            }

            // Imagen: se busca por nombre de archivo dentro del ZIP
            $img = self::v($a, 'imagenproducto');
            if ($img && $imagenes && !$prod->imagenproducto && ($contenido = $imagenes($img))) {
                $ext = strtolower(pathinfo($img, PATHINFO_EXTENSION));
                @mkdir(public_path($carpeta), 0775, true);
                $archivo = $carpeta . '/' . $prod->IdProducto . '_' . substr(md5($img), 0, 10) . '.' . $ext;
                file_put_contents(public_path($archivo), $contenido);
                $prod->update(['imagenproducto' => $archivo]);
            } elseif ($img && $imagenes && !$prod->imagenproducto) {
                $this->aviso("Imagen \"{$img}\" de {$nombre} no está en el ZIP.");
            }
        }

        $this->presentaciones($presentacionesFila, $barraLibre, $unidades);
        $this->preciosDinamicos($suc, $opc['dia0'] ?? 'domingo');
        $this->combos();
    }

    /** Presentaciones antiguas: filas tipo = 2 de productos (pro_rel + factor) y la tabla presentaciones */
    private function presentaciones($filas, callable $barraLibre, $unidades): void
    {
        $guardar = function (Producto $padre, string $ume, string $nombre, float $factor, float $precio, ?string $barra) use ($barraLibre) {
            if ((int) $padre->promocion !== 0 || $factor <= 0) {
                return;
            }
            $existe = ProductoPresentacion::where('IdProducto', $padre->IdProducto)->where('umecod', $ume)->where('factor', round($factor, 3))->first();
            $datos = ['nombre' => mb_substr($nombre, 0, 60), 'precio' => $precio > 0 ? $precio : round($padre->propun * $factor, 2), 'estado' => true];
            if ($existe) {
                $existe->update($datos);
            } else {
                ProductoPresentacion::create($datos + ['IdProducto' => $padre->IdProducto, 'umecod' => $ume, 'factor' => round($factor, 3),
                    'codigo_barra' => $barraLibre($barra)]);
                $this->sumar('productos', 'presentaciones');
            }
        };

        foreach ($filas as $f) {
            $padre = $this->mapa[self::v($f, 'pro_rel')] ?? null;
            if (!$padre) {
                $this->aviso('Presentación "' . $f->pronom . '": su producto principal no se importó.');
                continue;
            }
            $ume = strtoupper((string) self::v($f, 'umecod', 'NIU'));
            $guardar($padre, isset($unidades[$ume]) ? $ume : 'NIU', self::clave($f->pronom), (float) self::v($f, 'factor', 1),
                (float) self::v($f, 'propun', 0), self::v($f, 'codigo_barra'));
        }

        if ($this->hay('presentaciones')) {
            foreach ($this->src('presentaciones')->get() as $p) {
                $padre = $this->mapa[self::v($p, 'IdProducto')] ?? null;
                if (!$padre) {
                    continue;
                }
                $ume = strtoupper((string) self::v($p, 'umecod', 'NIU'));
                $ume = isset($unidades[$ume]) ? $ume : 'NIU';
                $factor = (float) (self::v($p, 'pres_fac') ?: self::v($p, 'pres_can', 1));
                $guardar($padre, $ume, self::clave(($unidades[$ume] ?? $ume) . ' X ' . rtrim(rtrim(number_format($factor, 3, '.', ''), '0'), '.')),
                    $factor, (float) self::v($p, 'pres_pre_pub', 0), null);
            }
        }
    }

    /** precios_dia_semana: el día 0 del sistema antiguo puede ser domingo o lunes (se elige al importar) */
    private function preciosDinamicos(int $suc, string $dia0): void
    {
        if (!$this->hay('precios_dia_semana')) {
            return;
        }
        $reglas = $this->deSucursal($this->src('precios_dia_semana'), 'precios_dia_semana', $suc)->get()
            ->filter(fn($r) => self::clave(self::v($r, 'estado', 'Activo')) !== 'INACTIVO' && isset($this->mapa[$r->IdProducto]))
            ->groupBy('IdProducto');

        foreach ($reglas as $idAntiguo => $suyas) {
            $prod = $this->mapa[$idAntiguo];
            ProductoPrecioDinamico::where('IdProducto', $prod->IdProducto)->delete();
            foreach ($suyas as $r) {
                $d = (int) $r->dia_semana;
                $dia = $dia0 === 'lunes' ? min(7, $d + 1) : ($d === 0 ? 7 : min(7, $d));
                ProductoPrecioDinamico::create([
                    'IdProducto' => $prod->IdProducto, 'dia' => $dia,
                    'hora_inicio' => self::v($r, 'hora_inicio_vigencia') ?: '00:00:00',
                    'hora_fin' => self::v($r, 'hora_fin_vigencia') ?: '00:00:00',
                    'precio' => (float) $r->precio_especial, 'activo' => true,
                ]);
                $this->sumar('productos', 'precios_dinamicos');
            }
        }
    }

    /** Combos (promocion 3 en el sistema antiguo): se reemplaza su contenido con los productos ya importados */
    private function combos(): void
    {
        if (!$this->hay('combos')) {
            return;
        }
        $omitidos = 0;
        foreach ($this->src('combos')->get()->groupBy('IdProducto_rel') as $idPadre => $items) {
            $padre = $this->mapa[$idPadre] ?? null;
            if (!$padre) {
                continue;
            }
            if ((int) $padre->promocion !== 6) {
                $omitidos += $items->count();   // en el sistema antiguo también eran recetas de preparados
                continue;
            }
            Combo::where('IdProducto_rel', $padre->IdProducto)->delete();
            foreach ($items as $i) {
                $hijo = $this->mapa[$i->IdProducto_comb] ?? null;
                if ($hijo && in_array((int) $hijo->promocion, [0, 2])) {
                    Combo::create(['IdProducto_rel' => $padre->IdProducto, 'IdProducto_comb' => $hijo->IdProducto, 'prod_comb_cant' => (float) $i->prod_comb_cant]);
                }
            }
        }
        if ($omitidos) {
            $this->aviso("{$omitidos} filas de combos pertenecían a productos que no son combo (recetas del sistema antiguo); no se importaron.");
        }
    }

    // ---------- Stock ----------
    private function stock(User $user, int $suc, array $opc): void
    {
        if (!$this->hay('producto_stock')) {
            return;
        }
        if (!$this->mapa) {
            // Sin importar productos en esta corrida: relaciona por código y nombre con los que ya existen
            $porNombre = Producto::where('id_empresa_negocio', $user->id_empresa_negocio)->get()
                ->keyBy(fn($p) => self::clave($p->pronom) . '|' . (int) $p->promocion);
            foreach ($this->deSucursal($this->src('productos'), 'productos', $suc)->get() as $a) {
                $tipo = self::TIPOS[(int) self::v($a, 'promocion', 0)] ?? 0;
                $p = $porNombre[self::clave($a->pronom) . '|' . $tipo] ?? null;
                if ($p) {
                    $this->mapa[$a->IdProducto] = $p;
                }
            }
        }

        $almacen = Kardex::almacenPredeterminado($user->id_empresa_negocio);
        if (!$almacen) {
            $this->aviso('No hay almacén en la sucursal para cargar el stock.');
            return;
        }
        $almacenAntiguo = (int) ($opc['almacen'] ?? 0) ?: ($this->almacenes($suc)[0]['id'] ?? null);

        $stock = $this->deSucursal($this->src('producto_stock'), 'producto_stock', $suc)
            ->when($almacenAntiguo && $this->col('producto_stock', 'id_almacen'), fn($q) => $q->where('id_almacen', $almacenAntiguo))
            ->selectRaw('IdProducto, SUM(stock) as stock')->groupBy('IdProducto')->pluck('stock', 'IdProducto');

        $items = [];
        foreach ($stock as $idAntiguo => $cantidad) {
            $prod = $this->mapa[$idAntiguo] ?? null;
            if (!$prod || !in_array((int) $prod->promocion, [0, 4]) || (float) $cantidad <= 0) {
                $this->sumar('stock', 'omitidos');
                continue;
            }
            if (DB::table('movimientos_productos')->where('IdProducto', $prod->IdProducto)->where('id_almacen', $almacen->id_almacen)->exists()) {
                $this->aviso("Stock de {$prod->pronom}: ya tiene movimientos en {$almacen->descripcion}; no se cambió (usa Inventarios).");
                $this->sumar('stock', 'omitidos');
                continue;
            }
            $items[$prod->IdProducto] = ['IdProducto' => $prod->IdProducto, 'cantidad' => round((float) $cantidad, 3), 'costo' => null];
        }

        if ($items) {
            $productos = Producto::whereIn('IdProducto', array_keys($items))->get()->keyBy('IdProducto');
            InventarioController::procesar($almacen, now()->toDateString(), 'STOCK INICIAL - IMPORTACIÓN DEL SISTEMA ANTIGUO',
                'ANTIGUO', array_values($items), $productos);
            $this->sumar('stock', 'creados', count($items));
        }
    }

    // ---------- Clientes y proveedores ----------
    private function clientes(User $user, ?string $rucAntiguo): void
    {
        if (!$this->hay('cliente')) {
            return;
        }
        $tipos = DB::table('tipo_documento_identidad')->pluck('tdicod')->map(fn($t) => (string) $t)->all();
        $existentes = array_fill_keys(DB::table('cliente')->where('rucemp', $user->IdEmpresa)->pluck('clinum')->all(), true);
        $nuevos = [];
        $vistos = [];
        // Facturación mensual (venta masiva): comprobante, mensual y monto del sistema antiguo
        $mensualDe = function (object $c) {
            $comp = (string) self::v($c, 'comprobante', '');
            return [
                'comprobante' => in_array($comp, ['01', '03', '13'], true) ? $comp : null,
                'mensual' => (int) self::v($c, 'mensual', 0) === 1,
                'monto' => round((float) self::v($c, 'monto', 0), 2),
            ];
        };

        $this->src('cliente')
            ->when($rucAntiguo && $this->col('cliente', 'rucemp'), fn($q) => $q->where('rucemp', $rucAntiguo))
            ->orderBy('clicod')->chunk(1000, function ($filas) use (&$existentes, &$nuevos, &$vistos, $tipos, $user, $mensualDe) {
                foreach ($filas as $c) {
                    $num = preg_replace('/\s+/', '', (string) self::v($c, 'clinum', ''));
                    $nom = mb_substr(self::clave(self::v($c, 'clinom', '')), 0, 200);
                    // El mismo documento dos veces en el sistema antiguo: queda el primero (un cliente = una fila)
                    if ($num !== '' && isset($vistos[$num])) {
                        if ((int) self::v($c, 'mensual', 0) === 1) {
                            $this->aviso("Cliente {$nom} ({$num}) está repetido en el sistema antiguo con otro monto mensual (S/ "
                                . number_format((float) self::v($c, 'monto', 0), 2) . '); se dejó el primero. Revísalo en Venta Masiva.');
                        }
                        $this->sumar('clientes', 'omitidos');
                        continue;
                    }
                    $vistos[$num] = true;
                    if ($num !== '' && isset($existentes[$num]) && (int) self::v($c, 'mensual', 0) === 1) {
                        // Ya existe: solo se completa su facturación mensual
                        DB::table('cliente')->where('rucemp', $user->IdEmpresa)->where('clinum', $num)->update($mensualDe($c));
                        $this->sumar('clientes', 'actualizados');
                        continue;
                    }
                    if ($num === '' || $num === '00000000' || $nom === '' || isset($existentes[$num])) {
                        $this->sumar('clientes', 'omitidos');
                        continue;
                    }
                    $td = (string) self::v($c, 'tdicod', '');
                    $td = in_array($td, $tipos, true) ? $td : (strlen($num) === 11 ? '6' : '1');
                    $existentes[$num] = true;
                    $nuevos[] = [
                        'tdicod' => $td, 'clinum' => mb_substr($num, 0, 20), 'clinom' => $nom, 'rucemp' => $user->IdEmpresa,
                        'clidir' => mb_substr(trim((string) self::v($c, 'clidir', '')) ?: '--', 0, 200),
                        'clicor' => mb_substr((string) self::v($c, 'clicor', ''), 0, 50) ?: null,
                        'telefono' => mb_substr((string) self::v($c, 'telefono', ''), 0, 20) ?: null,
                        'cliest' => 'Activo',
                    ] + $mensualDe($c);
                    if (count($nuevos) >= 500) {
                        DB::table('cliente')->insert($nuevos);
                        $this->sumar('clientes', 'creados', count($nuevos));
                        $nuevos = [];
                    }
                }
            });
        if ($nuevos) {
            DB::table('cliente')->insert($nuevos);
            $this->sumar('clientes', 'creados', count($nuevos));
        }
    }

    private function proveedores(User $user, ?string $rucAntiguo): void
    {
        if (!$this->hay('proveedor')) {
            return;
        }
        $existentes = array_fill_keys(DB::table('proveedor')->where('IdEmpresa', $user->IdEmpresa)->pluck('prov_ruc')->all(), true);
        $q = $this->src('proveedor')->when($rucAntiguo && $this->col('proveedor', 'IdEmpresa'), fn($q) => $q->where('IdEmpresa', $rucAntiguo));
        foreach ($q->orderBy('prov_id')->get() as $p) {
            $ruc = preg_replace('/\s+/', '', (string) self::v($p, 'prov_ruc', ''));
            $raz = self::clave(self::v($p, 'prov_raz', ''));
            if ($ruc === '' || $raz === '' || isset($existentes[$ruc])) {
                $this->sumar('proveedores', 'omitidos');
                continue;
            }
            $existentes[$ruc] = true;
            DB::table('proveedor')->insert([
                'tdicod' => self::v($p, 'tdicod') ?: (strlen($ruc) === 11 ? '6' : '1'), 'prov_ruc' => mb_substr($ruc, 0, 11),
                'prov_raz' => mb_substr($raz, 0, 255), 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $user->id_empresa_negocio,
                'prov_dir' => self::v($p, 'prov_dir') ?: '--', 'prov_cor' => self::v($p, 'prov_cor'),
                'prov_num_con' => self::v($p, 'prov_num_con'), 'prov_con' => self::v($p, 'prov_con'), 'prov_est' => '1',
            ]);
            $this->sumar('proveedores', 'creados');
        }
    }

    // ---------- Medios de pago y formas de pago ----------
    private function medios(User $user, int $suc, ?string $rucAntiguo): void
    {
        $nuevaSuc = $user->id_empresa_negocio;
        if ($this->hay('medios_pagos')) {
            $existentes = DB::table('medios_pagos')->where('id_empresa_negocio', $nuevaSuc)->pluck('nom_med_pag')->map(fn($n) => self::clave($n))->flip();
            $q = $this->deSucursal($this->src('medios_pagos'), 'medios_pagos', $suc)
                ->when($rucAntiguo && $this->col('medios_pagos', 'IdEmpresa'), fn($q) => $q->where(fn($w) => $w->where('IdEmpresa', $rucAntiguo)->orWhereNull('IdEmpresa')));
            foreach ($q->get() as $m) {
                $nom = mb_substr(self::clave($m->nom_med_pag), 0, 255);
                if ($nom === '' || isset($existentes[$nom])) {
                    $this->sumar('medios', 'omitidos');
                    continue;
                }
                $existentes[$nom] = true;
                DB::table('medios_pagos')->insert([
                    'nom_med_pag' => $nom, 'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc,
                    'comision' => self::v($m, 'comision'), 'predeterminado' => '0',
                    'cod_sunat' => self::v($m, 'cod_sunat'), 'tipo_medio' => self::v($m, 'tipo_medio'),
                ]);
                $this->sumar('medios', 'creados');
            }
        }

        if ($this->hay('credito_dias')) {
            $existentes = DB::table('credito_dias')->where('id_empresa_negocio', $nuevaSuc)->pluck('cre_dia_nom')->map(fn($n) => self::clave($n))->flip();
            foreach ($this->deSucursal($this->src('credito_dias'), 'credito_dias', $suc)->get() as $c) {
                $nom = mb_substr(self::clave($c->cre_dia_nom), 0, 255);
                if ($nom === '' || isset($existentes[$nom])) {
                    $this->sumar('medios', 'omitidos');
                    continue;
                }
                $existentes[$nom] = true;
                DB::table('credito_dias')->insert([
                    'cre_dia_nom' => $nom, 'cre_dia_fac' => (int) self::v($c, 'cre_dia_fac', 0),
                    'cre_dia_tip' => self::v($c, 'cre_dia_tip') ?: 'PERSONALIZADO',
                    'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc,
                ]);
                $this->sumar('medios', 'creados');
            }
        }
    }

    // ---------- Pisos y mesas ----------
    private function mesas(User $user, int $suc): void
    {
        if (!$this->hay('pisos') && !$this->hay('mesas')) {
            return;
        }
        $nuevaSuc = $user->id_empresa_negocio;
        $mapaPisos = [];
        if ($this->hay('pisos')) {
            $pisos = DB::table('pisos')->where('id_empresa_negocio', $nuevaSuc)->get()->keyBy(fn($p) => self::clave($p->pis_nom));
            foreach ($this->deSucursal($this->src('pisos'), 'pisos', $suc)->get() as $p) {
                $nom = mb_substr(self::clave($p->pis_nom), 0, 255);
                if (isset($pisos[$nom])) {
                    $mapaPisos[$p->pis_id] = $pisos[$nom]->pis_id;
                    continue;
                }
                $mapaPisos[$p->pis_id] = DB::table('pisos')->insertGetId(['pis_nom' => $nom, 'emp_id' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc]);
                $pisos[$nom] = (object) ['pis_id' => $mapaPisos[$p->pis_id]];
                $this->sumar('mesas', 'creados');
            }
        }
        if ($this->hay('mesas')) {
            $mesas = DB::table('mesas')->where('id_empresa_negocio', $nuevaSuc)->get()->keyBy(fn($m) => self::clave($m->mes_nom) . '|' . $m->pis_id);
            foreach ($this->deSucursal($this->src('mesas'), 'mesas', $suc)->get() as $m) {
                $piso = $mapaPisos[self::v($m, 'pis_id')] ?? null;
                $k = self::clave($m->mes_nom) . '|' . $piso;
                if (self::clave($m->mes_nom) === '' || isset($mesas[$k])) {
                    $this->sumar('mesas', 'omitidos');
                    continue;
                }
                DB::table('mesas')->insert(['mes_nom' => mb_substr(self::clave($m->mes_nom), 0, 255), 'mes_est' => 'Libre',
                    'IdEmpresa' => $user->IdEmpresa, 'id_empresa_negocio' => $nuevaSuc, 'pis_id' => $piso]);
                $mesas[$k] = true;
                $this->sumar('mesas', 'creados');
            }
        }
    }

    // ---------- Imágenes ----------
    /**
     * Índice de imágenes de un ZIP por nombre de archivo (sin carpetas, sin mayúsculas).
     * Devuelve una función nombre => contenido; no extrae nada al disco (evita rutas maliciosas dentro del ZIP).
     */
    private function indiceImagenes(?string $zip): ?\Closure
    {
        if (!$zip || !is_file($zip)) {
            return null;
        }
        $z = new \ZipArchive();
        if ($z->open($zip) !== true) {
            $this->aviso('No se pudo abrir el ZIP de imágenes.');
            return null;
        }
        $indice = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $nombre = $z->getNameIndex($i);
            $base = mb_strtolower(basename(str_replace('\\', '/', $nombre)));
            if (in_array(pathinfo($base, PATHINFO_EXTENSION), self::IMAGENES, true)) {
                $indice[$base] ??= $i;
            }
        }
        return function (string $archivo) use ($z, $indice) {
            $base = mb_strtolower(basename(str_replace('\\', '/', $archivo)));
            return isset($indice[$base]) ? $z->getFromIndex($indice[$base]) : null;
        };
    }
}
