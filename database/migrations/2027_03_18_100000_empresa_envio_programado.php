<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío a SUNAT "Programado" (empresa.tipo_envio = 3): una vez al día, a la hora elegida (ej. 19:00), las facturas
 * y sus notas se envían una por una y las boletas en resumen diario. envio_programado_el = último día en que se hizo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa', 'envio_hora')) {
            Schema::table('empresa', function (Blueprint $t) {
                $t->string('envio_hora', 5)->nullable();
                $t->date('envio_programado_el')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa', 'envio_hora')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn(['envio_hora', 'envio_programado_el']));
        }
    }
};
