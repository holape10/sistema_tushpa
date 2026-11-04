<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el contador revisa de la propuesta del SIRE antes de llevarla a su sistema contable (.dbf de FoxPro): las
 * cuentas de cada comprobante, los montos corregidos y los que no se llevan. La propuesta de SUNAT no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sire_ajustes')) {
            return;
        }
        Schema::create('sire_ajustes', function (Blueprint $t) {
            $t->id();
            $t->string('IdEmpresa', 11);
            $t->string('libro', 7);
            $t->string('periodo', 6);
            $t->string('clave', 40);
            $t->string('ruc', 15)->nullable();
            $t->boolean('excluido')->default(false);
            $t->string('ctacargo', 12)->nullable();
            $t->string('ctaabono', 12)->nullable();
            $t->json('montos')->nullable();
            $t->timestamps();
            $t->unique(['IdEmpresa', 'libro', 'periodo', 'clave'], 'sire_ajustes_unico');
            $t->index(['IdEmpresa', 'libro', 'ruc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_ajustes');
    }
};
