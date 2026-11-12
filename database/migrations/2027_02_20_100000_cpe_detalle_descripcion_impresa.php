<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se imprime de la línea cuando lleva líneas de texto (líneas libres en S/ 0 del Punto Venta, ej. el detalle de un
 * servicio en varias líneas): se imprimen con el ítem, pero a SUNAT va solo la descripción del ítem (cdedes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cpe_detalle') && ! Schema::hasColumn('cpe_detalle', 'descripcion_impresa')) {
            Schema::table('cpe_detalle', fn (Blueprint $t) => $t->text('descripcion_impresa')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cpe_detalle', 'descripcion_impresa')) {
            Schema::table('cpe_detalle', fn (Blueprint $t) => $t->dropColumn('descripcion_impresa'));
        }
    }
};
