<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medidas reales del local en el plano, por piso (1 unidad = 2 cm): ancho y largo. Las mesas guardan su tamaño en las
 * mismas unidades, así al achicar el local las mesas no se encogen. Los planos ya hechos quedan igual: 1000 = 20 m.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pisos', 'plano_ancho')) {
            Schema::table('pisos', fn (Blueprint $t) => $t->unsignedSmallInteger('plano_ancho')->default(1000));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pisos', 'plano_ancho')) {
            Schema::table('pisos', fn (Blueprint $t) => $t->dropColumn('plano_ancho'));
        }
    }
};
