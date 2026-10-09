<?php

namespace App\Support;

use App\Models\Almacen;
use App\Models\Combo;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único punto para mover stock: actualiza producto_stock y deja la huella en movimientos_productos (kardex).
 * Debe llamarse dentro de una transacción.
 */
class Kardex
{
    /**
     * @param  string  $movTip  'I' ingreso | 'E' egreso
     * @param  array  $doc  datos opcionales: cod_tip_ope, tdocod, serie, numero, IdCpe_cabecera, mov_cab_id, com_cab_id,
     *                      cliente, descripcion, precio, costo, fecha_mov, IdProducto_rel, lote, vencimiento,
     *                      inv_cab_id, id_almacen_origen, id_almacen_destino,
     *                      lote_preferido (salida: lote elegido por el cajero; si no, FEFO)
     * @return array partes por lote que se movieron: [['lote' => ?string, 'vencimiento' => ?string, 'cantidad' => float]]
     *               (en el kardex queda una fila por cada lote)
     */
    public static function registrar(int $idProducto, int $idAlmacen, float $cantidad, string $movTip, array $doc = []): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $user = Auth::user();
        $sucursal = $doc['id_empresa_negocio'] ?? $user->id_empresa_negocio;

        // Bloquea la fila de stock para que dos ventas simultáneas no calculen el mismo saldo
        $fila = DB::table('producto_stock')
            ->where('IdProducto', $idProducto)->where('id_almacen', $idAlmacen)
            ->lockForUpdate()->first();

        if (! $fila) {
            DB::table('producto_stock')->insert([
                'IdProducto' => $idProducto, 'id_almacen' => $idAlmacen,
                'id_empresa_negocio' => $sucursal, 'stock' => 0, 'stock_inicial' => 0,
            ]);
            $stockAntes = 0.0;
        } else {
            $stockAntes = (float) $fila->stock;
        }

        // ---- Lotes: un ingreso con lote suma a ese lote; una salida sin lote indicado sigue FEFO ----
        $lote = mb_substr(trim((string) ($doc['lote'] ?? '')), 0, 50) ?: null;
        $vencimiento = ($doc['vencimiento'] ?? null) ?: null;
        if ($movTip === 'I') {
            if ($lote) {
                Lotes::ingresar($idProducto, $idAlmacen, $sucursal, $lote, $vencimiento, $cantidad);
            }
            $partes = [['lote' => $lote, 'vencimiento' => $vencimiento, 'cantidad' => $cantidad]];
        } else {
            $partes = $lote
                ? Lotes::retirarLote($idProducto, $idAlmacen, $lote, $cantidad)
                : Lotes::retirar($idProducto, $idAlmacen, $cantidad, $stockAntes, ($doc['lote_preferido'] ?? null) ?: null);
        }

        $stockDespues = $movTip === 'I' ? $stockAntes + $cantidad : $stockAntes - $cantidad;

        DB::table('producto_stock')
            ->where('IdProducto', $idProducto)->where('id_almacen', $idAlmacen)
            ->update(['stock' => $stockDespues]);

        $saldo = $stockAntes;
        foreach ($partes as $p) {
            $antes = $saldo;
            $saldo = $movTip === 'I' ? $saldo + $p['cantidad'] : $saldo - $p['cantidad'];
            self::fila($idProducto, $idAlmacen, $sucursal, $user, $movTip, $p['cantidad'], $antes, $saldo,
                ['lote' => $p['lote'], 'vencimiento' => $p['vencimiento']] + $doc);
        }

        return $partes;
    }

    private static function fila(int $idProducto, int $idAlmacen, $sucursal, $user, string $movTip, float $cantidad,
        float $stockAntes, float $stockDespues, array $doc): void
    {
        DB::table('movimientos_productos')->insert([
            'IdProducto' => $idProducto,
            'IdProducto_rel' => $doc['IdProducto_rel'] ?? null,
            'precio' => $doc['precio'] ?? 0,
            'costo' => $doc['costo'] ?? 0,
            'cantidad' => $cantidad,
            'stock_inicial' => $stockAntes,
            'stock' => $stockDespues,
            'mov_tip' => $movTip,
            'tipo' => $movTip === 'I' ? 1 : 2,
            'cod_tip_ope' => $doc['cod_tip_ope'] ?? null,
            'tdocod' => $doc['tdocod'] ?? null,
            'serie' => $doc['serie'] ?? null,
            'numero' => $doc['numero'] ?? null,
            'IdCpe_cabecera' => $doc['IdCpe_cabecera'] ?? null,
            'mov_cab_id' => $doc['mov_cab_id'] ?? null,
            'com_cab_id' => $doc['com_cab_id'] ?? null,
            'inv_cab_id' => $doc['inv_cab_id'] ?? null,
            'id_almacen_origen' => $doc['id_almacen_origen'] ?? null,
            'id_almacen_destino' => $doc['id_almacen_destino'] ?? null,
            'mov_lote' => $doc['lote'] ?? null,
            'mov_vencimiento' => $doc['vencimiento'] ?? null,
            'cliente' => isset($doc['cliente']) ? mb_substr($doc['cliente'], 0, 150) : null,
            'descripcion' => isset($doc['descripcion']) ? mb_substr($doc['descripcion'], 0, 100) : null,
            'fecha_mov' => $doc['fecha_mov'] ?? now()->toDateString(),
            'fecha_hora' => now(),
            'fecha_registro' => now(),
            'id_almacen' => $idAlmacen,
            'id_empresa_negocio' => $sucursal,
            'IdUsuario' => $user?->IdUsuario,
        ]);
    }

    /**
     * Salida por venta. Productos simples (0) descuentan directo; los combos (6) descuentan sus componentes simples.
     * Preparados (2) no llevan stock porque aún no tienen receta. Devuelve los lotes del producto simple (para el ticket).
     */
    public static function salidaPorVenta(Producto $producto, int $idAlmacen, float $cantidad, array $doc): array
    {
        $tipo = (int) $producto->promocion;

        if ($tipo === 0) {
            return self::registrar($producto->IdProducto, $idAlmacen, $cantidad, 'E', $doc + ['costo' => $producto->costo]);
        }

        // Plato preparado: salen los insumos de su receta (si tiene)
        if ($tipo === 2) {
            self::salidaPorReceta($producto->IdProducto, $idAlmacen, $cantidad, $doc);
        }

        if ($tipo === 6) {
            $componentes = Combo::where('IdProducto_rel', $producto->IdProducto)->with('itemProducto')->get();
            foreach ($componentes as $c) {
                $item = $c->itemProducto;
                if ($item && (int) $item->promocion === 0) {
                    self::registrar($c->IdProducto_comb, $idAlmacen, $cantidad * (float) $c->prod_comb_cant, 'E',
                        $doc + ['IdProducto_rel' => $producto->IdProducto, 'costo' => $item->costo, 'precio' => 0]);
                } elseif ($item && (int) $item->promocion === 2) {
                    self::salidaPorReceta($item->IdProducto, $idAlmacen, $cantidad * (float) $c->prod_comb_cant, $doc);
                }
            }
        }

        return [];
    }

    /** Salida de los insumos de la receta de un plato (cantidad de platos x lo que lleva cada uno, en la unidad del insumo) */
    private static function salidaPorReceta(int $idPlato, int $idAlmacen, float $platos, array $doc): void
    {
        foreach (Recetas::de($idPlato) as $r) {
            $cantidad = round($platos * $r->cantidad_base, 4);
            if ($cantidad > 0) {
                self::registrar($r->IdInsumo, $idAlmacen, $cantidad, 'E',
                    $doc + ['IdProducto_rel' => $idPlato, 'costo' => $r->costo_unitario, 'precio' => 0]);
            }
        }
    }

    /**
     * Anulación de una venta: devuelve al stock exactamente lo que salió con ese comprobante
     * (las salidas que quedaron en el kardex), como ingreso por devolución (operación 05).
     */
    public static function revertirVenta(int $idCpeCabecera, array $doc): int
    {
        $salidas = DB::table('movimientos_productos')
            ->where('IdCpe_cabecera', $idCpeCabecera)->where('mov_tip', 'E')
            ->orderBy('mov_pro_id')->get();

        foreach ($salidas as $s) {
            self::registrar((int) $s->IdProducto, (int) $s->id_almacen, (float) $s->cantidad, 'I', $doc + [
                'cod_tip_ope' => '05', 'IdCpe_cabecera' => $idCpeCabecera, 'IdProducto_rel' => $s->IdProducto_rel,
                'costo' => $s->costo, 'precio' => $s->precio, 'tdocod' => $s->tdocod, 'serie' => $s->serie, 'numero' => $s->numero,
                'id_empresa_negocio' => $s->id_empresa_negocio,
                // Regresa al mismo lote del que salió
                'lote' => $s->mov_lote, 'vencimiento' => $s->mov_vencimiento,
            ]);
        }

        return $salidas->count();
    }

    public static function almacenPredeterminado(int $idEmpresaNegocio): ?Almacen
    {
        return Almacen::where('id_empresa_negocio', $idEmpresaNegocio)->orderByDesc('predeterminado')->first();
    }
}
