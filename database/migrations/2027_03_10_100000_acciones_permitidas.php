<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anular, editar ventas, notas de crédito y anular compras o movimientos de caja pasan a ser solo del administrador:
 * a otro usuario se le dan marcándolos en su ficha (se guardan aquí). Nadie más los tiene al actualizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'acciones_permitidas')) {
            Schema::table('users', fn (Blueprint $t) => $t->text('acciones_permitidas')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'acciones_permitidas')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('acciones_permitidas'));
        }
    }
};
