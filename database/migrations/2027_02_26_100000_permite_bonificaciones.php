<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonificaciones en el Punto de venta (distribuidoras: "lleva 20 y te regalo 2"): la línea marcada como bonificación va
 * gratis. Ante SUNAT es una operación gratuita (como la cortesía del restaurante), descuenta stock y se imprime BONIFICACIÓN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'permite_bonificaciones')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('permite_bonificaciones')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'permite_bonificaciones')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('permite_bonificaciones'));
        }
    }
};
