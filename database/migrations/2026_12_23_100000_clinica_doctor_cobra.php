<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clínicas: el doctor puede cobrar sus atenciones ahí mismo (abre su propio turno de caja). Apagado por defecto: así
 * sigue como antes, el doctor termina la atención y recepción la cobra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'clinica_doctor_cobra')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('clinica_doctor_cobra')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'clinica_doctor_cobra')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('clinica_doctor_cobra'));
        }
    }
};
