<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motorizados del delivery: se eligen al hacer la comanda (o al cobrar) y el reporte Ventas Delivery filtra por ellos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('motorizados')) {
            Schema::create('motorizados', function (Blueprint $t) {
                $t->increments('mot_id');
                $t->string('nombre', 100);
                $t->string('telefono', 20)->nullable();
                $t->string('placa', 10)->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }
        if (! Schema::hasColumn('pedidos', 'mot_id')) {
            Schema::table('pedidos', fn (Blueprint $t) => $t->unsignedInteger('mot_id')->nullable()->index());
        }
        if (! DB::table('modulos')->where('mod_url', '/motorizados')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Motorizados', 'mod_url' => '/motorizados', 'mod_gen' => 'Restaurante']);
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn('mot_id'));
        Schema::dropIfExists('motorizados');
        DB::table('modulos')->where('mod_url', '/motorizados')->delete();
    }
};
