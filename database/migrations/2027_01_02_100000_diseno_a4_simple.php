<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseño del comprobante A4 por sucursal: MODERNO (el de siempre, con colores) o SIMPLE (blanco y negro, como el del
 * sistema antiguo, para gastar menos tinta), y unas líneas libres para la cabecera (ej. "DE: …", el giro del negocio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'diseno_a4')) {
                $t->string('diseno_a4', 10)->default('MODERNO');
                $t->string('cabecera_a4', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            $t->dropColumn(['diseno_a4', 'cabecera_a4']);
        });
    }
};
