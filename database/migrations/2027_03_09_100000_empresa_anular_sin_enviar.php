<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opción de la empresa (apagada de fábrica): anular una boleta o factura como una nota de venta mientras aún no se
 * envió a SUNAT. Ya enviada, solo se puede con comunicación de baja o nota de crédito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa', 'anular_sin_enviar')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->boolean('anular_sin_enviar')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa', 'anular_sin_enviar')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn('anular_sin_enviar'));
        }
    }
};
