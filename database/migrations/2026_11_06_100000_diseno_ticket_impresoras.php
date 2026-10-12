<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseño del comprobante en la ticketera: CLASICO (como el sistema anterior: nombre de la empresa grande y
 * columnas Cant. / UDM / P.U. / Total) o MODERNO (compacto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_impresoras', function (Blueprint $t) {
            if (! Schema::hasColumn('configuracion_impresoras', 'diseno')) {
                $t->string('diseno', 10)->default('CLASICO');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_impresoras', fn (Blueprint $t) => $t->dropColumn('diseno'));
    }
};
