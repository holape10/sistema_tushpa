<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instalador del agente de impresión: al descargarlo, su nombre lleva un código de un solo uso. Al instalar,
 * el instalador lo canjea (agente/instalar/{codigo}) por la clave de la sucursal y la agrega a config.ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('agente_codigos')) {
            Schema::create('agente_codigos', function (Blueprint $t) {
                $t->id();
                $t->string('codigo', 12)->unique();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->timestamp('expira');
                $t->timestamp('usado')->nullable();
                $t->string('usado_ip', 45)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agente_codigos');
    }
};
