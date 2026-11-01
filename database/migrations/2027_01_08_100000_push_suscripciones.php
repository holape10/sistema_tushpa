<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos al celular del mozo (pedido del QR, llaman al mozo, piden la cuenta, plato listo). Solo los equipos donde
 * alguien tocó "Activar avisos": quien no lo usa no recibe nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_suscripciones')) {
            return;
        }
        Schema::create('push_suscripciones', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('IdUsuario')->index();
            $t->unsignedBigInteger('id_empresa_negocio')->index();
            $t->char('huella', 64)->unique();          // sha256 del endpoint (el endpoint es muy largo para un índice)
            $t->text('endpoint');
            $t->string('p256dh', 200);
            $t->string('auth', 100);
            $t->string('equipo', 150)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_suscripciones');
    }
};
