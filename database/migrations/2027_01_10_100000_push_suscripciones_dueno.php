<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos al celular del dueño (anulaciones, platos quitados, cortesías, descuentos, faltantes de caja). Un mismo
 * celular puede recibir los de mozo (QR, plato listo), los de dueño o ambos. Los que ya había son de mozo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_suscripciones') && ! Schema::hasColumn('push_suscripciones', 'dueno')) {
            Schema::table('push_suscripciones', function (Blueprint $t) {
                $t->boolean('mozo')->default(true);
                $t->boolean('dueno')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('push_suscripciones', 'dueno')) {
            Schema::table('push_suscripciones', function (Blueprint $t) {
                $t->dropColumn(['mozo', 'dueno']);
            });
        }
    }
};
