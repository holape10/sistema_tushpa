<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medio de pago y fecha de pago de cada compra del SIRE (almaingr.MPAGO y almaingr.FEPAGO), y la cuenta contable de
 * cada medio de pago de la empresa: al elegir el medio, la cuenta de cancelación se pone sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sire_ajustes')) {
            Schema::table('sire_ajustes', function (Blueprint $t) {
                if (! Schema::hasColumn('sire_ajustes', 'mpago')) {
                    $t->string('mpago', 2)->nullable();
                }
                if (! Schema::hasColumn('sire_ajustes', 'fepago')) {
                    $t->date('fepago')->nullable();
                }
            });
        }
        if (! Schema::hasTable('sire_medios_pago')) {
            Schema::create('sire_medios_pago', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->string('codigo', 2);
                $t->string('cuenta', 12)->nullable();
                $t->timestamps();
                $t->unique(['IdEmpresa', 'codigo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_medios_pago');
        foreach (['mpago', 'fepago'] as $c) {
            if (Schema::hasColumn('sire_ajustes', $c)) {
                Schema::table('sire_ajustes', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
