<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre de caja ciego: el cajero cuenta el efectivo sin ver cuánto debería haber; la diferencia se ve al cerrar y
 * le llega al dueño. Apagado hasta que la sucursal lo active.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'cierre_ciego')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('cierre_ciego')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'cierre_ciego')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('cierre_ciego'));
        }
    }
};
