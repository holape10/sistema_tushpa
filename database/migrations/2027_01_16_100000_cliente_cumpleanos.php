<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumpleaños del cliente (se anota al cobrar con su DNI): la caja ve "¡Hoy cumple años!" y la fidelización puede dar
 * puntos dobles en su mes de cumpleaños. El dueño ve quién cumple este mes para saludarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cliente', 'fecha_nac')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->date('fecha_nac')->nullable());
        }
        if (! Schema::hasColumn('empresa_negocios', 'fid_cumple_dobles')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('fid_cumple_dobles')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cliente', 'fecha_nac')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->dropColumn('fecha_nac'));
        }
        if (Schema::hasColumn('empresa_negocios', 'fid_cumple_dobles')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('fid_cumple_dobles'));
        }
    }
};
