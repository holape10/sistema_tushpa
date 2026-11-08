<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Clínica: además del aviso la noche anterior, un recordatorio unas horas antes de la cita (al paciente y al doctor) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clinica_config') && ! Schema::hasColumn('clinica_config', 'recordar_antes')) {
            Schema::table('clinica_config', function (Blueprint $t) {
                $t->boolean('recordar_antes')->default(true)->after('hora_envio');
                $t->unsignedTinyInteger('horas_antes')->default(2)->after('recordar_antes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('clinica_config') && Schema::hasColumn('clinica_config', 'recordar_antes')) {
            Schema::table('clinica_config', fn (Blueprint $t) => $t->dropColumn(['recordar_antes', 'horas_antes']));
        }
    }
};
