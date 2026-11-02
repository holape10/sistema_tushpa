<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forma del ambiente en el plano del local, por piso: el ancho es siempre 1000 y el alto lo elige el dueño
 * (625 = más ancho que alto, como antes; 1000 = cuadrado; más de 1000 = más alto que ancho).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pisos', 'plano_alto')) {
            Schema::table('pisos', fn (Blueprint $t) => $t->unsignedSmallInteger('plano_alto')->default(625));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pisos', 'plano_alto')) {
            Schema::table('pisos', fn (Blueprint $t) => $t->dropColumn('plano_alto'));
        }
    }
};
