<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Punto de venta: el total de cada línea se redondea solo a 10 céntimos (2.35 × 11 = 25.85 → 25.90 o 25.80), sin cambiar la
 * cantidad ni el precio. Vacío = sin redondeo (como siempre); CERCANO = al más cercano; ABAJO = siempre a favor del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'redondeo_linea')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('redondeo_linea', 10)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'redondeo_linea')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('redondeo_linea'));
        }
    }
};
