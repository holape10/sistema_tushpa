<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto para redes: en la comanda de la mesa, el mozo o promotor toma una foto al cliente (si acepta), le pone el marco
 * con el logo del local y la comparte desde su celular. Apagado hasta que se active en la sucursal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'foto_redes')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('foto_redes')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'foto_redes')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('foto_redes'));
        }
    }
};
