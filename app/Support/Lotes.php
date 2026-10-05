<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Lotes y vencimientos (farmacia). Lo usa Kardex::registrar; debe llamarse dentro de una transacción.
 *
 * Orden de salida (FEFO, "primero en vencer, primero en salir"):
 *   1. el lote que eligió el cajero (si eligió uno y no está vencido)
 *   2. lotes vigentes, del que vence antes al que vence después
 *   3. stock sin lote (anterior a usar lotes)
 *   4. lotes vencidos (último recurso, para que el stock siempre cuadre)
 *   5. lo que falte sale sin lote (stock negativo, igual que hoy en el sistema)
 */
class Lotes
{
    /** Suma stock a un lote (lo crea si no existe) */
    public static function ingresar(int $idProducto, int $idAlmacen, ?int $sucursal, string $lote, ?string $vencimiento, float $cantidad): void
    {
        $fila = DB::table('producto_lote')->where('IdProducto', $idProducto)->where('id_almacen', $idAlmacen)
            ->where('lote', $lote)->lockForUpdate()->first();

        if ($fila) {
            DB::table('producto_lote')->where('id_lote', $fila->id_lote)->update([
                'stock' => $fila->stock + $cantidad,
                'vencimiento' => $vencimiento ?: $fila->vencimiento,
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('producto_lote')->insert([
            'IdProducto' => $idProducto, 'id_almacen' => $idAlmacen, 'id_empresa_negocio' => $sucursal,
            'lote' => $lote, 'vencimiento' => $vencimiento, 'stock' => $cantidad,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Salida de un lote indicado (anulación de compra, devolución). El lote no baja de 0:
     * si sale más de lo que tiene, el exceso lo absorbe el stock sin lote.
     *
     * @return array partes [['lote' => ?string, 'vencimiento' => ?string, 'cantidad' => float]]
     */
    public static function retirarLote(int $idProducto, int $idAlmacen, string $lote, float $cantidad): array
    {
        $fila = DB::table('producto_lote')->where('IdProducto', $idProducto)->where('id_almacen', $idAlmacen)
            ->where('lote', $lote)->lockForUpdate()->first();

        if ($fila) {
            DB::table('producto_lote')->where('id_lote', $fila->id_lote)
                ->update(['stock' => max(0, $fila->stock - $cantidad), 'updated_at' => now()]);
        }

        return [['lote' => $lote, 'vencimiento' => $fila->vencimiento ?? null, 'cantidad' => $cantidad]];
    }

    /**
     * Salida automática por FEFO. $stockTotal es el stock del producto en el almacén antes de la salida.
     *
     * @return array partes [['lote' => ?string, 'vencimiento' => ?string, 'cantidad' => float]]
     */
    public static function retirar(int $idProducto, int $idAlmacen, float $cantidad, float $stockTotal, ?string $preferido = null): array
    {
        $lotes = DB::table('producto_lote')->where('IdProducto', $idProducto)->where('id_almacen', $idAlmacen)
            ->where('stock', '>', 0)->lockForUpdate()->get();

        if ($lotes->isEmpty()) {
            return [['lote' => null, 'vencimiento' => null, 'cantidad' => $cantidad]];
        }

        $orden = self::ordenar($lotes, $preferido);
        $sinLote = max(0, round($stockTotal - $lotes->sum('stock'), 5));
        $hoy = now()->toDateString();

        // Inserta el stock sin lote antes de los vencidos
        $plan = [];
        $puesto = false;
        foreach ($orden as $l) {
            if (!$puesto && $l->vencimiento && $l->vencimiento < $hoy && !($preferido !== null && $l->lote === $preferido)) {
                $plan[] = null;
                $puesto = true;
            }
            $plan[] = $l;
        }
        if (!$puesto) {
            $plan[] = null;
        }

        $partes = [];
        $falta = $cantidad;
        foreach ($plan as $l) {
            if ($falta <= 0) {
                break;
            }
            $disponible = $l ? (float) $l->stock : $sinLote;
            $toma = min($falta, $disponible);
            if ($toma <= 0) {
                continue;
            }
            if ($l) {
                DB::table('producto_lote')->where('id_lote', $l->id_lote)->update(['stock' => $l->stock - $toma, 'updated_at' => now()]);
            }
            $partes[] = ['lote' => $l->lote ?? null, 'vencimiento' => $l->vencimiento ?? null, 'cantidad' => round($toma, 5)];
            $falta = round($falta - $toma, 5);
        }

        if ($falta > 0) {
            $partes[] = ['lote' => null, 'vencimiento' => null, 'cantidad' => $falta];
        }
        return self::juntarSinLote($partes);
    }

    /** Lote elegido primero; luego vigentes por vencimiento (los sin fecha al final); los vencidos al último */
    public static function ordenar($lotes, ?string $preferido = null)
    {
        $hoy = now()->toDateString();
        return collect($lotes)->sortBy(fn($l) => [
            $preferido !== null && $l->lote === $preferido ? 0 : 1,
            $l->vencimiento && $l->vencimiento < $hoy ? 1 : 0,
            $l->vencimiento ?: '9999-12-31',
            $l->id_lote ?? 0,
        ])->values();
    }

    private static function juntarSinLote(array $partes): array
    {
        $res = [];
        foreach ($partes as $p) {
            $k = $p['lote'] ?? '';
            if (isset($res[$k])) {
                $res[$k]['cantidad'] = round($res[$k]['cantidad'] + $p['cantidad'], 5);
            } else {
                $res[$k] = $p;
            }
        }
        return array_values($res);
    }

    /** Texto corto para el ticket: "L123 V:12/2026 (2), L130 V:03/2027 (1)" */
    public static function texto(array $partes): ?string
    {
        $con = array_filter($partes, fn($p) => $p['lote']);
        if (!$con) {
            return null;
        }
        $txt = implode(', ', array_map(fn($p) => $p['lote']
            . ($p['vencimiento'] ? ' V:' . date('m/Y', strtotime($p['vencimiento'])) : '')
            . (count($partes) > 1 ? ' (' . rtrim(rtrim(number_format($p['cantidad'], 2, '.', ''), '0'), '.') . ')' : ''), $con));
        return mb_substr($txt, 0, 255);
    }

    /** Días de aviso de la sucursal */
    public static function diasAlerta(int $sucursal): int
    {
        return (int) (DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('dias_alerta_vencimiento') ?: 90);
    }
}
