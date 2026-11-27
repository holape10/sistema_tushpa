<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pausar caja: el cajero deja la caja bloqueada con un mensaje grande («Caja cerrada», «En descanso», «Use otra caja»)
 * sin cerrar su turno; para volver pone su clave (o la de un administrador). Queda cuánto duró cada pausa.
 *  - caja_pausas: cada pausa (quién, turno, motivo, desde y hasta cuándo, quién la quitó)
 *  - empresa_negocios.pausa_inactividad: minutos sin usar la caja para que se pause sola (0 = nunca)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('caja_pausas')) {
            Schema::create('caja_pausas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->unsignedInteger('IdUsuario');
                $t->unsignedInteger('id_turno')->nullable();
                $t->string('motivo', 12);
                $t->unsignedSmallInteger('minutos')->nullable();
                $t->string('pc', 40)->nullable();
                $t->timestamp('inicio')->useCurrent();
                $t->timestamp('fin')->nullable();
                $t->unsignedInteger('IdUsuario_fin')->nullable();
                $t->index(['IdUsuario', 'fin']);
                $t->index(['id_empresa_negocio', 'inicio']);
            });
        }
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'pausa_inactividad')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->unsignedSmallInteger('pausa_inactividad')->default(0));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_pausas');
        if (Schema::hasColumn('empresa_negocios', 'pausa_inactividad')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('pausa_inactividad'));
        }
    }
};
