<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotores (discotecas, bares): usuarios con rol Promotor. El mozo elige (opcional) el promotor del pedido y
 * pasa al comprobante al cobrar, para el reporte "Ventas por Vendedor" agrupado por promotor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('id', 13)->exists()) {
            DB::table('roles')->insert(['id' => 13, 'name' => 'promotor', 'description' => 'Promotor']);
        }
        foreach (['pedidos', 'cpe_cabecera'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) use ($tabla) {
                if (! Schema::hasColumn($tabla, 'promotor_id')) {
                    $t->unsignedInteger('promotor_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['pedidos', 'cpe_cabecera'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('promotor_id'));
        }
        DB::table('roles')->where('id', 13)->delete();
    }
};
