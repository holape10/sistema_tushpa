<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos especiales por acción: lo que el administrador le apagó a cada usuario (anular ventas, cobrar a otro precio…).
 * Vacío = todo permitido, como hasta ahora (ver App\Support\Acciones).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'acciones_bloqueadas')) {
            Schema::table('users', fn (Blueprint $t) => $t->text('acciones_bloqueadas')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'acciones_bloqueadas')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('acciones_bloqueadas'));
        }
    }
};
