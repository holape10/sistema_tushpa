<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Precio 2 y precio 3 del producto (por mayor, especial…): la caja los elige al vender sin pedir permiso de cambiar
 * precio. Y el rol Vendedor: arma pedidos (proformas) en el punto de venta sin abrir caja; la caja los cobra.
 * Ambos venían del sistema anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['precio2', 'precio3'] as $columna) {
            if (! Schema::hasColumn('productos', $columna)) {
                Schema::table('productos', fn (Blueprint $t) => $t->decimal($columna, 12, 2)->nullable());
            }
        }

        if (Schema::hasTable('roles') && ! DB::table('roles')->where('id', 5)->exists()) {
            DB::table('roles')->insert(['id' => 5, 'name' => 'vendedor', 'description' => 'Vendedor']);
        }
    }

    public function down(): void
    {
        foreach (['precio2', 'precio3'] as $columna) {
            if (Schema::hasColumn('productos', $columna)) {
                Schema::table('productos', fn (Blueprint $t) => $t->dropColumn($columna));
            }
        }
    }
};
