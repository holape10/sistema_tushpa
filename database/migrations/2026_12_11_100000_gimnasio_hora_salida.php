<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gimnasio: hora de salida. Quien ya entró y vuelve a marcar (pasados unos minutos) marca su salida; así se sabe
 * cuánto tiempo estuvo, como en las hojas diarias que llevaban en Excel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gym_asistencias') && ! Schema::hasColumn('gym_asistencias', 'salida')) {
            Schema::table('gym_asistencias', fn (Blueprint $t) => $t->dateTime('salida')->nullable()->after('fecha_hora'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gym_asistencias', 'salida')) {
            Schema::table('gym_asistencias', fn (Blueprint $t) => $t->dropColumn('salida'));
        }
    }
};
