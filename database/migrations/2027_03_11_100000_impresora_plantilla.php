<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseño "Personalizado" del ticket de cada ticketera: qué partes lleva, en qué orden y con qué formato. El campo del
 * diseño se agranda para que entre "PERSONALIZADO".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->string('diseno', 15)->default('CLASICO')->change());
        if (! Schema::hasColumn('configuracion_impresoras', 'plantilla')) {
            Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->text('plantilla')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuracion_impresoras', 'plantilla')) {
            Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->dropColumn('plantilla'));
        }
    }
};
