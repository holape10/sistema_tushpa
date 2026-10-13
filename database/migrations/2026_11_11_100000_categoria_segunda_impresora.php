<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segunda impresora de comandas por categoría: la comanda sale en las dos (ej.: barra y caja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $t) {
            if (! Schema::hasColumn('categorias', 'impresora2')) {
                $t->unsignedInteger('impresora2')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('categorias', fn (Blueprint $t) => $t->dropColumn('impresora2'));
    }
};
