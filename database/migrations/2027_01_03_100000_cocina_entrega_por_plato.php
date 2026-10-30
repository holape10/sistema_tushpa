<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla de cocina: cada plato se despacha por separado (lo rápido sale antes) y el mozo marca cuándo lo llevó a la
 * mesa, plato por plato, sin esperar a que salga toda la comanda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cocina_items') && ! Schema::hasColumn('cocina_items', 'entregado')) {
            Schema::table('cocina_items', function (Blueprint $t) {
                $t->timestamp('entregado')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cocina_items', 'entregado')) {
            Schema::table('cocina_items', function (Blueprint $t) {
                $t->dropColumn('entregado');
            });
        }
    }
};
