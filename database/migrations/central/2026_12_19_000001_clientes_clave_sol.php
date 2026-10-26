<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clave SOL principal del cliente, solo como referencia para el panel (el sistema no la usa para enviar a SUNAT).
 * La contraseña se guarda cifrada con la APP_KEY (cast "encrypted" en el modelo Cliente).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasColumn('clientes', 'sol_usuario')) {
            return;
        }
        Schema::connection('central')->table('clientes', function (Blueprint $t) {
            $t->string('sol_usuario', 20)->nullable();
            $t->text('sol_clave')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['sol_usuario', 'sol_clave']));
    }
};
