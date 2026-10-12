<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Números de serie por unidad (electrodomésticos, celulares: serie del fabricante o IMEI).
 * Un producto con control de serie se compra (o ingresa) indicando la serie de cada unidad y se vende eligiendo cuáles
 * salen; así se sabe a quién se vendió cada equipo y hasta cuándo tiene garantía. Estados: DISPONIBLE, VENDIDO, BAJA.
 * Se usa dentro de las mismas transacciones que el kardex.
 */
class Series
{
    /**
     * Series escritas o escaneadas: una por línea (también separadas por coma o punto y coma), en mayúsculas.
     *
     * @return array<int, string>
     */
    public static function lista(mixed $texto): array
    {
        $partes = is_array($texto) ? $texto : preg_split('/[\r\n,;]+/', (string) $texto);

        return array_values(array_filter(array_map(fn ($s) => mb_strtoupper(mb_substr(trim((string) $s), 0, 80)), $partes), fn ($s) => $s !== ''));
    }

    /**
     * Ingreso (compra o ingreso manual): una serie por unidad, sin repetir, y que no exista ya en ese producto.
     *
     * @param  array{com_cab_id?: ?int, mov_cab_id?: ?int, fecha?: ?string, id_empresa_negocio: int}  $ref
     */
    public static function ingresar(object $producto, int $idAlmacen, array $series, float $unidades, array $ref): void
    {
        self::cuadrar($producto, $series, $unidades);
        $existentes = DB::table('producto_series')->where('IdProducto', $producto->IdProducto)->whereIn('serie', $series)->pluck('serie')->all();
        if ($existentes) {
            throw new \RuntimeException("La serie {$existentes[0]} de {$producto->pronom} ya está registrada en el sistema.");
        }
        DB::table('producto_series')->insert(array_map(fn ($s) => [
            'IdProducto' => $producto->IdProducto, 'id_almacen' => $idAlmacen, 'id_empresa_negocio' => $ref['id_empresa_negocio'],
            'serie' => $s, 'estado' => 'DISPONIBLE', 'com_cab_id' => $ref['com_cab_id'] ?? null, 'mov_cab_id' => $ref['mov_cab_id'] ?? null,
            'fecha_ingreso' => $ref['fecha'] ?? now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ], $series));
    }

    /**
     * Venta: las series elegidas deben estar disponibles; quedan como vendidas con el comprobante, el cliente y su garantía.
     * Devuelve el texto para el comprobante ("ABC123, XYZ789").
     */
    public static function vender(object $producto, array $series, float $unidades, int $idCpe, string $cliente, string $fecha): string
    {
        if (! $series) {
            throw new \RuntimeException("{$producto->pronom} se vende con número de serie: véndelo desde el Punto de Venta eligiendo la serie de cada unidad.");
        }
        self::cuadrar($producto, $series, $unidades);
        $filas = DB::table('producto_series')->where('IdProducto', $producto->IdProducto)->whereIn('serie', $series)
            ->where('estado', 'DISPONIBLE')->lockForUpdate()->pluck('id', 'serie');
        foreach ($series as $s) {
            if (! isset($filas[$s])) {
                throw new \RuntimeException("La serie {$s} de {$producto->pronom} no está disponible (no se registró su ingreso o ya se vendió).");
            }
        }
        $garantia = (int) ($producto->garantia_meses ?? 0);
        DB::table('producto_series')->whereIn('id', $filas->values())->update([
            'estado' => 'VENDIDO', 'IdCpe_cabecera' => $idCpe, 'fecha_venta' => $fecha, 'cliente' => mb_substr($cliente, 0, 150),
            'garantia_hasta' => $garantia > 0 ? Carbon::parse($fecha)->addMonths($garantia)->toDateString() : null,
            'updated_at' => now(),
        ]);

        return implode(', ', $series);
    }

    /** Anulación de una venta: sus series vuelven a estar disponibles */
    public static function revertirVenta(int $idCpe): int
    {
        return DB::table('producto_series')->where('IdCpe_cabecera', $idCpe)->where('estado', 'VENDIDO')->update([
            'estado' => 'DISPONIBLE', 'IdCpe_cabecera' => null, 'fecha_venta' => null, 'cliente' => null, 'garantia_hasta' => null, 'updated_at' => now(),
        ]);
    }

    /** Anular o editar una compra: se quitan sus series (si alguna ya se vendió, no se puede) */
    public static function retirarCompra(int $comCabId): void
    {
        $vendida = DB::table('producto_series as s')->join('productos as p', 'p.IdProducto', '=', 's.IdProducto')
            ->where('s.com_cab_id', $comCabId)->where('s.estado', '!=', 'DISPONIBLE')->first(['s.serie', 'p.pronom']);
        if ($vendida) {
            throw new \RuntimeException("La serie {$vendida->serie} de {$vendida->pronom} de esta compra ya se vendió: anula primero esa venta.");
        }
        DB::table('producto_series')->where('com_cab_id', $comCabId)->delete();
    }

    /** Salida manual (merma, robo, devolución al proveedor): las series quedan de baja */
    public static function darDeBaja(object $producto, array $series, float $unidades, int $movCabId): void
    {
        self::cuadrar($producto, $series, $unidades);
        $filas = DB::table('producto_series')->where('IdProducto', $producto->IdProducto)->whereIn('serie', $series)
            ->where('estado', 'DISPONIBLE')->lockForUpdate()->pluck('id', 'serie');
        foreach ($series as $s) {
            if (! isset($filas[$s])) {
                throw new \RuntimeException("La serie {$s} de {$producto->pronom} no está disponible.");
            }
        }
        DB::table('producto_series')->whereIn('id', $filas->values())->update(['estado' => 'BAJA', 'mov_cab_id' => $movCabId, 'updated_at' => now()]);
    }

    /**
     * Series disponibles por producto (para elegir al vender).
     *
     * @return Collection<int, Collection<int, string>> IdProducto => series
     */
    public static function disponibles(array $idsProducto): Collection
    {
        return DB::table('producto_series')->whereIn('IdProducto', $idsProducto)->where('estado', 'DISPONIBLE')
            ->orderBy('fecha_ingreso')->orderBy('id')->get(['IdProducto', 'serie'])
            ->groupBy('IdProducto')->map(fn ($g) => $g->pluck('serie')->values());
    }

    /** Una serie por unidad (cantidad entera) y sin repetir */
    private static function cuadrar(object $producto, array $series, float $unidades): void
    {
        if (abs($unidades - round($unidades)) > 0.0001) {
            throw new \RuntimeException("{$producto->pronom} se maneja por número de serie: la cantidad debe ser entera.");
        }
        if (count($series) !== (int) round($unidades)) {
            throw new \RuntimeException("{$producto->pronom}: indica una serie por cada unidad (".(int) round($unidades).' unidad(es), '.count($series).' serie(s)).');
        }
        if (count(array_unique($series)) !== count($series)) {
            throw new \RuntimeException("{$producto->pronom}: hay una serie repetida.");
        }
    }
}
