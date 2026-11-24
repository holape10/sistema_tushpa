<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Huella de cada PDF de manual: para no subir (y pagar por leer) dos veces el mismo archivo */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('asistente_manuales') && ! Schema::connection('central')->hasColumn('asistente_manuales', 'huella')) {
            Schema::connection('central')->table('asistente_manuales', function (Blueprint $t) {
                $t->char('huella', 64)->nullable()->index()->after('archivo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('central')->hasColumn('asistente_manuales', 'huella')) {
            Schema::connection('central')->table('asistente_manuales', fn (Blueprint $t) => $t->dropColumn('huella'));
        }
    }
};
