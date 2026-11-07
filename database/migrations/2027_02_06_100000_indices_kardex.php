<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El kardex (movimientos_productos) es la tabla que más crece: cada plato vendido saca sus insumos uno por uno. Con
 * varias sedes de mucho movimiento son millones de filas al año. Estos índices evitan recorrerla entera al anular una
 * venta o una compra, al ver una transferencia o un inventario, y en los reportes de consumo por almacén y fecha.
 * MySQL los agrega sin bloquear la tabla (las ventas siguen mientras tanto).
 */
return new class extends Migration
{
    private const INDICES = [
        'mov_prod_cpe_index' => ['IdCpe_cabecera'],
        'mov_prod_compra_index' => ['com_cab_id'],
        'mov_prod_mov_cab_index' => ['mov_cab_id'],
        'mov_prod_inventario_index' => ['inv_cab_id'],
        'mov_prod_almacen_fecha_index' => ['id_almacen', 'fecha_mov'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('movimientos_productos')) {
            return;
        }
        $existentes = collect(DB::select('SHOW INDEX FROM movimientos_productos'))->pluck('Key_name')->unique()->all();
        foreach (self::INDICES as $nombre => $columnas) {
            if (! in_array($nombre, $existentes, true) && collect($columnas)->every(fn ($c) => Schema::hasColumn('movimientos_productos', $c))) {
                Schema::table('movimientos_productos', fn (Blueprint $t) => $t->index($columnas, $nombre));
            }
        }
    }

    public function down(): void
    {
        $existentes = collect(DB::select('SHOW INDEX FROM movimientos_productos'))->pluck('Key_name')->unique()->all();
        foreach (array_keys(self::INDICES) as $nombre) {
            if (in_array($nombre, $existentes, true)) {
                Schema::table('movimientos_productos', fn (Blueprint $t) => $t->dropIndex($nombre));
            }
        }
    }
};
