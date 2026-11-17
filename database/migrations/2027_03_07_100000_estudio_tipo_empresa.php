<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de cada empresa del estudio contable (SERVICIO, COMERCIO, PRODUCCION u OTROS): en el tienfact.dbf de ventas el
 * total de cada comprobante va al campo de su tipo (SERVI, MERCA, PROTE u OTR).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estudio_empresas') && ! Schema::hasColumn('estudio_empresas', 'tipo_empresa')) {
            Schema::table('estudio_empresas', fn (Blueprint $t) => $t->string('tipo_empresa', 12)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('estudio_empresas', 'tipo_empresa')) {
            Schema::table('estudio_empresas', fn (Blueprint $t) => $t->dropColumn('tipo_empresa'));
        }
    }
};
