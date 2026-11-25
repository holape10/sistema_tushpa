<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Precio x Mayor automático: desde esta cantidad la caja cobra solo el Precio x Mayor (precio2), sin elegirlo */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('productos') && ! Schema::hasColumn('productos', 'mayor_desde')) {
            Schema::table('productos', fn (Blueprint $t) => $t->decimal('mayor_desde', 12, 3)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'mayor_desde')) {
            Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('mayor_desde'));
        }
    }
};
