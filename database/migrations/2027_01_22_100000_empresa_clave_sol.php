<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estudio contable: cada empresa cliente guarda su propia Clave SOL principal (cifrada con la APP_KEY) para traer los
 * XML de sus compras por json.pe. Las empresas que no son de un estudio siguen usando la del panel TUSHPA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa', 'sol_usuario')) {
            Schema::table('empresa', function (Blueprint $t) {
                $t->string('sol_usuario', 20)->nullable();
                $t->text('sol_clave')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa', 'sol_usuario')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn(['sol_usuario', 'sol_clave']));
        }
    }
};
