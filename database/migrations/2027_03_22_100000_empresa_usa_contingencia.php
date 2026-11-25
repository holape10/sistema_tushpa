<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contingencia (pasar al sistema boletas y facturas hechas en papel) como opción de la empresa: el Punto Venta solo
 * muestra la casilla a quien la usa. Queda activada en las empresas que ya registraron alguna (serie de 4 números).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa') || Schema::hasColumn('empresa', 'usa_contingencia')) {
            return;
        }
        Schema::table('empresa', function (Blueprint $t) {
            $t->boolean('usa_contingencia')->default(false);
        });
        if (Schema::hasTable('cpe_cabecera')) {
            $usan = DB::table('cpe_cabecera')->whereRaw("serdoc REGEXP '^[0-9]{4}$'")->distinct()->pluck('IdEmpresa');
            DB::table('empresa')->whereIn('IdEmpresa', $usan)->update(['usa_contingencia' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa', 'usa_contingencia')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn('usa_contingencia'));
        }
    }
};
