<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de Inventario Permanente de SUNAT, armado con los movimientos del kardex (movimientos_productos):
 *  - Formato 12.1: en unidades físicas (obligatorio con ingresos de 500 a 1,500 UIT).
 *  - Formato 13.1: valorizado (más de 1,500 UIT), por promedio ponderado o PEPS (tabla 14).
 * Con su archivo de texto para el PLE (estructura del Anexo 2, R.S. 042-2018/SUNAT): campos separados por "|",
 * un saldo inicial por producto y almacén, y luego cada entrada y salida del mes con su comprobante y su operación (tabla 12).
 */
class KardexSunat
{
    /** Formato => [nombre corto, nombre oficial, código del libro en el PLE] */
    public const FORMATOS = [
        '121' => ['12.1', 'Registro del inventario permanente en unidades físicas', '120100'],
        '131' => ['13.1', 'Registro del inventario permanente valorizado', '130100'],
    ];

    /** Tabla 14 */
    public const METODOS = ['1' => 'Promedio ponderado', '2' => 'PEPS (primeras entradas, primeras salidas)'];

    /** Tabla 5 */
    public const TIPOS_EXISTENCIA = ['01' => 'Mercaderías', '02' => 'Productos terminados', '03' => 'Materias primas', '04' => 'Envases',
        '05' => 'Materiales auxiliares', '06' => 'Suministros', '07' => 'Repuestos', '08' => 'Embalajes', '09' => 'Subproductos', '99' => 'Otros'];

    /** Tabla 10: los comprobantes que pueden sustentar un movimiento (lo interno, como la nota de venta, va como 00) */
    private const TABLA_10 = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '12', '31', '50', '52', '53'];

    /** Tabla 12: operaciones que exigen un comprobante (campo 11 distinto de 00) */
    private const CON_COMPROBANTE = ['01', '02', '03', '04', '05', '06'];

    /** Código de existencia válido: UNSPSC (8 dígitos) o ítem del CUBSO del OECE (16 dígitos) */
    public const REGLA_CODIGO = '/^(\d{8}|\d{16})$/';

    /** Buscador oficial del CUBSO (consulta pública del OECE) */
    public const BUSCADOR_CUBSO = 'https://www.gob.pe/36792-acceder-al-buscador-del-cubso';

    /** Campo 9 del PLE (16 dígitos): el ítem CUBSO tal cual, o el UNSPSC seguido de 8 ceros; vacío si no tiene */
    public static function codigoExistencia(?string $codigo): string
    {
        $codigo = preg_replace('/\D/', '', (string) $codigo);

        return match (strlen($codigo)) {
            16 => $codigo,
            8 => $codigo.'00000000',
            default => '',
        };
    }

    /**
     * Códigos para elegir al poner el código de existencia de un producto:
     *  - porCategoria: el más usado en cada categoría de la empresa (se sugiere solo al elegir la categoría);
     *  - usados: los que la empresa ya puso, con un producto de ejemplo (para no volver a buscarlos);
     *  - catalogo: el catálogo 25 de SUNAT (y los genéricos por rubro), para buscarlo escribiendo.
     *
     * @return array{porCategoria: array<int, string>, usados: array<string, string>, catalogo: array<string, string>}
     */
    public static function codigosUsados(int $sucursal): array
    {
        $filas = DB::table('productos')->where('id_empresa_negocio', $sucursal)->whereNotNull('unspsc')->where('unspsc', '!=', '')
            ->get(['cat_id', 'unspsc', 'pronom']);

        return [
            'porCategoria' => $filas->whereNotNull('cat_id')->groupBy('cat_id')->map(fn ($g) => $g->countBy('unspsc')->sortDesc()->keys()->first())->all(),
            'usados' => $filas->groupBy('unspsc')->map(fn ($g) => 'Ya usado: '.mb_substr($g->first()->pronom, 0, 60).($g->count() > 1 ? ' y '.($g->count() - 1).' más' : ''))->sortKeys()->all(),
            'catalogo' => Schema::hasTable('sunat_catalogo_25')
                ? DB::table('sunat_catalogo_25')->orderBy('categoria')->orderBy('descripcion')->get()
                    ->mapWithKeys(fn ($c) => [$c->codigo => $c->descripcion.' · '.$c->categoria.($c->anexo !== 'GENERICO' ? ' (catálogo '.$c->anexo.')' : '')])->all()
                : [],
        ];
    }

    /** Tipo de existencia: el elegido en el producto o, si no, según su tipo (insumo = materia prima, preparado = producto terminado) */
    public static function tipoExistencia(object $p): string
    {
        return $p->tipo_existencia ?: match ((int) $p->promocion) {
            4 => '03',
            2, 6 => '02',
            default => '01',
        };
    }

    // ------------------------------------------------------------------ valorización

    /** @return array{cantidad: float, valor: float, capas: array<int, array{0: float, 1: float}>, ultimo: float} */
    public static function saldoVacio(float $costoRespaldo = 0): array
    {
        return ['cantidad' => 0.0, 'valor' => 0.0, 'capas' => [], 'ultimo' => $costoRespaldo];
    }

    /**
     * Entra mercadería a su costo (si no lo tiene, como una devolución, al costo del momento).
     *
     * @return array{0: float, 1: float} [costo unitario, total]
     */
    public static function entrar(array &$s, float $cantidad, float $costo, string $metodo): array
    {
        $promedio = $s['cantidad'] > 0.000001 ? $s['valor'] / $s['cantidad'] : null;
        // Sin costo (devolución de un cliente): promedio del momento, o en PEPS al costo con que salió lo último
        $unitario = $costo > 0 ? $costo : ($metodo === '1' ? ($promedio ?? $s['ultimo']) : $s['ultimo']);
        $total = round($cantidad * $unitario, 2);
        if ($metodo === '2') {
            // Con stock negativo (se vendió sin stock), lo que entra primero cubre ese faltante
            $resto = $s['cantidad'] < 0 ? $cantidad + $s['cantidad'] : $cantidad;
            if ($resto > 0.000001) {
                $s['capas'][] = [$resto, $unitario];
            }
        }
        $s['cantidad'] += $cantidad;
        $s['valor'] = $metodo === '2' ? self::valorCapas($s, $unitario) : $s['valor'] + $total;
        $s['ultimo'] = $unitario;
        self::normalizar($s, $unitario);

        return [round($unitario, 8), $total];
    }

    /**
     * Sale mercadería: al costo promedio del momento, o consumiendo las capas más antiguas (PEPS).
     *
     * @return array{0: float, 1: float} [costo unitario, total]
     */
    public static function salir(array &$s, float $cantidad, string $metodo): array
    {
        if ($metodo === '2') {
            [$pendiente, $total] = [$cantidad, 0.0];
            while ($pendiente > 0.000001 && $s['capas']) {
                [$q, $u] = $s['capas'][0];
                $toma = min($q, $pendiente);
                $total += $toma * $u;
                $pendiente -= $toma;
                $s['ultimo'] = $u;
                $q - $toma > 0.000001 ? $s['capas'][0][0] = $q - $toma : array_shift($s['capas']);
            }
            $total += $pendiente * $s['ultimo'];   // sin capas (stock negativo): al último costo
            $unitario = $cantidad > 0 ? $total / $cantidad : $s['ultimo'];
            $s['cantidad'] -= $cantidad;
            $s['valor'] = self::valorCapas($s, $s['ultimo']);
        } else {
            $unitario = $s['cantidad'] > 0.000001 ? $s['valor'] / $s['cantidad'] : $s['ultimo'];
            $total = $cantidad * $unitario;
            $s['cantidad'] -= $cantidad;
            $s['valor'] -= round($total, 2);
            $s['ultimo'] = $unitario;
        }
        self::normalizar($s, $s['ultimo']);

        return [round($unitario, 8), round($total, 2)];
    }

    private static function valorCapas(array $s, float $ultimo): float
    {
        return $s['cantidad'] < 0 ? $s['cantidad'] * $ultimo : round(array_sum(array_map(fn ($c) => $c[0] * $c[1], $s['capas'])), 2);
    }

    /** Sin existencias no queda valor; con stock negativo se valoriza al último costo */
    private static function normalizar(array &$s, float $unitario): void
    {
        if (abs($s['cantidad']) < 0.000001) {
            [$s['cantidad'], $s['valor'], $s['capas']] = [0.0, 0.0, []];
        } elseif ($s['cantidad'] < 0) {
            $s['valor'] = round($s['cantidad'] * $unitario, 2);
            $s['capas'] = [];
        }
    }

    /** @return array{0: float, 1: float, 2: float} [cantidad, costo unitario, total] */
    public static function foto(array $s): array
    {
        $cantidad = round($s['cantidad'], 8);

        return [$cantidad, abs($cantidad) > 0.000001 ? round(abs($s['valor'] / $cantidad), 8) : 0.0, round($s['valor'], 2)];
    }

    // ------------------------------------------------------------------ registro del mes

    /**
     * Registro del periodo: por cada producto y almacén, su saldo inicial y sus movimientos del mes valorizados.
     *
     * @param  string  $periodo  AAAA-MM
     * @return array{grupos: array<int, array<string, mixed>>, avisos: array<string, mixed>, establecimiento: string}
     */
    public static function generar(int $sucursal, string $periodo, string $metodo, ?int $idAlmacen = null): array
    {
        $desde = Carbon::parse($periodo.'-01')->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();
        $establecimiento = str_pad(substr(preg_replace('/\D/', '', (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('codigofiscal')), 0, 4), 4, '0', STR_PAD_LEFT);
        // Posiciones 5 a 7 del código de establecimiento: un correlativo por almacén
        $almacenes = DB::table('almacenes')->where('id_empresa_negocio', $sucursal)->orderBy('id_almacen')->get(['id_almacen', 'descripcion'])->values()
            ->mapWithKeys(fn ($a, $i) => [$a->id_almacen => (object) ['nombre' => $a->descripcion, 'codigo' => $establecimiento.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)]]);
        $productos = DB::table('productos')->where('id_empresa_negocio', $sucursal)
            ->get(['IdProducto', 'procod', 'pronom', 'umecod', 'promocion', 'costo', 'unspsc', 'tipo_existencia'])->keyBy('IdProducto');

        $movimientos = DB::table('movimientos_productos')->where('id_empresa_negocio', $sucursal)->where('fecha_mov', '<=', $hasta->toDateString())
            ->when($idAlmacen, fn ($q) => $q->where('id_almacen', $idAlmacen))
            ->orderBy('IdProducto')->orderBy('id_almacen')->orderBy('fecha_mov')->orderBy('mov_pro_id')
            ->select('mov_pro_id', 'IdProducto', 'id_almacen', 'mov_tip', 'cantidad', 'costo', 'fecha_mov', 'tdocod', 'serie', 'numero', 'cod_tip_ope')
            ->cursor();

        $grupos = [];
        $avisos = ['sin_unspsc' => [], 'sin_comprobante' => 0, 'sin_costo' => 0, 'negativos' => []];
        $actual = null;
        $cerrar = function () use (&$actual, &$grupos, &$avisos) {
            if (! $actual) {
                return;
            }
            // Sin movimientos en el mes: su saldo inicial es lo que quedó de antes
            $actual['inicial'] ??= self::foto($actual['saldo']);
            if ($actual['filas'] || abs($actual['inicial'][0]) > 0.000001) {
                $avisos['negativos'] += $actual['saldo']['cantidad'] < -0.000001 ? [$actual['producto']->IdProducto => $actual['producto']->pronom] : [];
                $grupos[] = $actual;
            }
        };
        $desdeTexto = $desde->toDateString();

        foreach ($movimientos as $m) {
            $clave = $m->IdProducto.'-'.$m->id_almacen;
            if (! $actual || $actual['clave'] !== $clave) {
                $cerrar();
                $p = $productos[$m->IdProducto] ?? null;
                $actual = $p ? ['clave' => $clave, 'producto' => $p, 'almacen' => $almacenes[$m->id_almacen] ?? (object) ['nombre' => '—', 'codigo' => $establecimiento.'001'],
                    'saldo' => self::saldoVacio((float) $p->costo), 'inicial' => null, 'filas' => []] : null;
                if (! $actual) {
                    continue;
                }
            }
            if ($m->fecha_mov >= $desdeTexto && $actual['inicial'] === null) {
                $actual['inicial'] = self::foto($actual['saldo']);
            }
            $cantidad = (float) $m->cantidad;
            [$unitario, $total] = $m->mov_tip === 'I'
                ? self::entrar($actual['saldo'], $cantidad, (float) $m->costo, $metodo)
                : self::salir($actual['saldo'], $cantidad, $metodo);
            if ($m->fecha_mov < $desdeTexto) {
                continue;
            }
            $op = $m->cod_tip_ope ?: '99';
            $tdoc = in_array($m->tdocod, self::TABLA_10, true) ? $m->tdocod : '00';
            // Una venta o compra sin comprobante de pago (nota de venta, ticket interno) no puede ir como operación 01/02 con documento 00
            if ($tdoc === '00' && in_array($op, self::CON_COMPROBANTE, true)) {
                $op = '99';
                $avisos['sin_comprobante']++;
            }
            if ($m->mov_tip === 'I' && (float) $m->costo <= 0 && in_array($op, ['02', '18'], true)) {
                $avisos['sin_costo']++;
            }
            $actual['filas'][] = ['id' => $m->mov_pro_id, 'fecha' => $m->fecha_mov, 'tdoc' => $tdoc,
                'serie' => $tdoc === '00' ? '0' : (trim((string) $m->serie) ?: '0'), 'numero' => $tdoc === '00' ? (string) $m->mov_pro_id : (ltrim((string) $m->numero, '0') ?: '0'),
                'op' => str_pad($op, 2, '0', STR_PAD_LEFT), 'tipo' => $m->mov_tip,
                'cantidad' => $cantidad, 'unitario' => $unitario, 'total' => $total, 'saldo' => self::foto($actual['saldo'])];
        }
        $cerrar();

        foreach ($grupos as &$g) {
            $g['final'] = self::foto($g['saldo']);
            if (self::codigoExistencia($g['producto']->unspsc) === '') {
                $avisos['sin_unspsc'][$g['producto']->IdProducto] = $g['producto']->pronom;
            }
        }
        unset($g);
        usort($grupos, fn ($a, $b) => [$a['producto']->pronom, $a['almacen']->codigo] <=> [$b['producto']->pronom, $b['almacen']->codigo]);

        return ['grupos' => $grupos, 'avisos' => $avisos, 'establecimiento' => $establecimiento, 'desde' => $desde, 'hasta' => $hasta];
    }

    // ------------------------------------------------------------------ PLE

    /** Número sin comas de miles, con 2 decimales como mínimo y hasta $max */
    public static function numero(float $n, int $max = 2): string
    {
        $texto = number_format(round($n, $max), $max, '.', '');
        if ($max > 2) {
            $texto = preg_replace('/(\.\d{2}\d*?)0+$/', '$1', $texto);
        }

        return $texto === '-0.00' ? '0.00' : $texto;
    }

    private static function texto(string $t, int $largo): string
    {
        return mb_substr(trim(preg_replace('/[|\r\n\t]+/', ' ', $t)), 0, $largo);
    }

    /**
     * Nombre oficial del archivo: LE + RUC + AAAAMM00 + libro + oportunidad (00) + con operaciones + con contenido + moneda (1 = soles) + generado por PLE (1).
     */
    public static function nombreArchivo(string $ruc, string $periodo, string $formato, bool $conDatos): string
    {
        return 'LE'.$ruc.str_replace('-', '', $periodo).'00'.self::FORMATOS[$formato][2].'00'.($conDatos ? '1' : '0').($conDatos ? '1' : '0').'11.txt';
    }

    /** Contenido del archivo de texto del PLE (12.1 o 13.1) */
    public static function ple(array $registro, string $formato, string $metodo): string
    {
        $periodo = $registro['desde']->format('Ym').'00';
        $lineas = [];
        foreach ($registro['grupos'] as $g) {
            $p = $g['producto'];
            $unspsc = self::codigoExistencia($p->unspsc);
            $comunes = fn (string $cuo, string $correlativo, string $fecha, string $tdoc, string $serie, string $numero, string $op) => [
                $periodo, $cuo, $correlativo, $g['almacen']->codigo, '9', self::tipoExistencia($p), self::texto($p->procod ?: (string) $p->IdProducto, 24),
                $unspsc ? '1' : '', $unspsc, Carbon::parse($fecha)->format('d/m/Y'), $tdoc, self::texto($serie, 20), self::texto($numero, 20), $op,
                self::texto($p->pronom, 80), Unidades::sunat($p->umecod),
            ];
            // Primera línea: el saldo inicial del mes (va en las entradas)
            [$q0, $u0, $t0] = $g['inicial'];
            $apertura = $registro['desde']->month === 1 ? 'A1' : 'M1';
            $base = $comunes('SI'.$p->IdProducto.'-'.substr($g['almacen']->codigo, -3), $apertura, $registro['desde']->toDateString(), '00', '0', '0', '16');
            $lineas[] = $formato === '121'
                ? [...$base, self::numero($q0), '0.00', '1']
                : [...$base, $metodo, self::numero($q0, 8), self::numero($u0, 8), self::numero($t0), '0.00', '0.00', '0.00', self::numero($q0, 8), self::numero($u0, 8), self::numero($t0), '1'];
            foreach ($g['filas'] as $f) {
                $base = $comunes('K'.$f['id'], 'M1', $f['fecha'], $f['tdoc'], $f['serie'], $f['numero'], $f['op']);
                $entra = $f['tipo'] === 'I';
                [$qs, $us, $ts] = $f['saldo'];
                // Las salidas van en negativo
                $lineas[] = $formato === '121'
                    ? [...$base, $entra ? self::numero($f['cantidad']) : '0.00', $entra ? '0.00' : self::numero(-$f['cantidad']), '1']
                    : [...$base, $metodo,
                        $entra ? self::numero($f['cantidad'], 8) : '0.00', $entra ? self::numero($f['unitario'], 8) : '0.00', $entra ? self::numero($f['total']) : '0.00',
                        $entra ? '0.00' : self::numero(-$f['cantidad'], 8), $entra ? '0.00' : self::numero($f['unitario'], 8), $entra ? '0.00' : self::numero(-$f['total']),
                        self::numero($qs, 8), self::numero($us, 8), self::numero($ts), '1'];
            }
        }

        return implode('', array_map(fn ($l) => implode('|', $l)."|\r\n", $lineas));
    }
}
