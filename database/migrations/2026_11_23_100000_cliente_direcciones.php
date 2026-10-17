<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes con varias sucursales (un RUC, varias direcciones): la dirección principal sigue en cliente.clidir y las
 * demás se guardan aquí al vender con otra dirección. Al buscar el RUC se ofrecen todas para elegir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente_direcciones')) {
            return;
        }
        Schema::create('cliente_direcciones', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('clicod');
            $t->string('direccion', 200);
            $t->timestamp('usado')->nullable();
            $t->unique(['clicod', 'direccion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_direcciones');
    }
};
