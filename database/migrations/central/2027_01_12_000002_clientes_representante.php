<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Representante legal de la empresa cliente: sale en su contrato de servicio (y quien firma lo puede corregir) */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasColumn('clientes', 'representante')) {
            Schema::connection('central')->table('clientes', function (Blueprint $t) {
                $t->string('representante', 150)->nullable();
                $t->string('representante_dni', 15)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['representante', 'representante_dni']));
    }
};
