<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cómo se muestra la imagen de una promoción (ventana emergente y su página):
 *  - imagen_ajuste: RECORTAR (llena el espacio, se elige qué parte se ve) o COMPLETA (se ve entera, ideal para afiches).
 *  - imagen_pos: la parte que se ve al recortar, en % horizontal y vertical ("50 20" = centrada y arriba).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasColumn('web_promociones', 'imagen_ajuste')) {
            return;
        }
        Schema::connection('central')->table('web_promociones', function (Blueprint $t) {
            $t->string('imagen_ajuste', 10)->default('RECORTAR');
            $t->string('imagen_pos', 10)->default('50 50');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('web_promociones', fn (Blueprint $t) => $t->dropColumn(['imagen_ajuste', 'imagen_pos']));
    }
};
