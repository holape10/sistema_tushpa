<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Precios por costo: cuándo el dueño ya revisó (subió) el precio por ese cambio de costo, para no volver a sugerirlo */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('costo_historial') && ! Schema::hasColumn('costo_historial', 'revisado_at')) {
            Schema::table('costo_historial', fn (Blueprint $t) => $t->timestamp('revisado_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('costo_historial', 'revisado_at')) {
            Schema::table('costo_historial', fn (Blueprint $t) => $t->dropColumn('revisado_at'));
        }
    }
};
