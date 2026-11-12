<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A4 simple: "CANCELADO" grande al medio en las ventas al contado (sin fecha ni firma). Apagado por defecto: lo activa cada sucursal */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'a4_cancelado_grande')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('a4_cancelado_grande')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'a4_cancelado_grande')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('a4_cancelado_grande'));
        }
    }
};
