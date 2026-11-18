<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opción de la sucursal: en el Punto Venta se elige quién hizo la venta (vendedor), aunque la PC esté abierta con otro
 * usuario. Sale en el ticket y en el reporte de ventas por vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'pv_elegir_vendedor')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('pv_elegir_vendedor')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'pv_elegir_vendedor')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('pv_elegir_vendedor'));
        }
    }
};
