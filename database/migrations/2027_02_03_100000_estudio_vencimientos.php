<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vencimientos SUNAT del estudio contable: qué empresa ya declaró cada periodo (y quién lo marcó), y cuáles son buenos
 * contribuyentes (vencen en su propia fecha, después de los demás).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estudio_empresas') && ! Schema::hasColumn('estudio_empresas', 'buen_contribuyente')) {
            Schema::table('estudio_empresas', function (Blueprint $t) {
                $t->boolean('buen_contribuyente')->default(false)->after('activa');
            });
        }
        if (! Schema::hasTable('estudio_declaraciones')) {
            Schema::create('estudio_declaraciones', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->char('periodo', 6);
                $t->unsignedInteger('IdUsuario');
                $t->timestamps();
                $t->unique(['IdEmpresa', 'periodo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_declaraciones');
        if (Schema::hasColumn('estudio_empresas', 'buen_contribuyente')) {
            Schema::table('estudio_empresas', fn (Blueprint $t) => $t->dropColumn('buen_contribuyente'));
        }
    }
};
