<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Precios por tipo de cliente: "mayor" (Precio x Mayor) o "especial" (Precio Especial); vacío = precios normales */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente') && ! Schema::hasColumn('cliente', 'lista_precio')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->string('lista_precio', 10)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente', 'lista_precio')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->dropColumn('lista_precio'));
        }
    }
};
