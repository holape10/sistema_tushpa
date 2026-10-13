<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuántas copias salen de cada comanda (cocina/bar) y de cada comprobante en la ticketera. Por defecto 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'copias_comanda')) {
                $t->unsignedTinyInteger('copias_comanda')->default(1);
                $t->unsignedTinyInteger('copias_comprobante')->default(1);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['copias_comanda', 'copias_comprobante']));
    }
};
