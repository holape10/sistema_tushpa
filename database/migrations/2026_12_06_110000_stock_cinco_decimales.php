<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El stock con 5 decimales (como el kardex): un insumo contado en botellas y onzas (4 botellas + 2 onzas = 4.08)
 * o en litros y mililitros (2 L + 35 ml = 2.035) se guarda exacto y no pierde lo que se cuenta en la unidad equivalente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('producto_stock')) {
            Schema::table('producto_stock', function (Blueprint $t) {
                $t->decimal('stock', 15, 5)->default(0)->change();
                $t->decimal('stock_inicial', 15, 5)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        //
    }
};
