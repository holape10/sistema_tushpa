<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advertencia antes de suspender: el panel deja un mensaje (ej. deuda del mes) con fecha límite.
 * El cliente lo ve en su sistema y, si se marcó, se suspende solo cuando pasa la fecha.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('clientes', 'aviso_mensaje')) {
            Schema::connection('central')->table('clientes', function (Blueprint $t) {
                $t->text('aviso_mensaje')->nullable();
                $t->date('aviso_fecha')->nullable();
                $t->tinyInteger('aviso_suspender')->default(0);
                $t->dateTime('aviso_creado')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['aviso_mensaje', 'aviso_fecha', 'aviso_suspender', 'aviso_creado']));
    }
};
