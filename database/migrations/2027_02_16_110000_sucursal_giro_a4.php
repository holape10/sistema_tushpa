<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Giro o actividad de la sucursal (sale en cursiva en la cabecera del comprobante A4, como en los comprobantes antiguos) */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'giro_a4')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('giro_a4', 300)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'giro_a4')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('giro_a4'));
        }
    }
};
