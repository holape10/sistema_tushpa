<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes que el contador agrega a mano al libro de un mes (el proveedor mandó tarde su factura y SUNAT aún no la
 * tiene en la propuesta). Van junto a los de la propuesta: cuentas, .dbf, Excel, reporte y CONCAR.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sire_manuales')) {
            return;
        }
        Schema::create('sire_manuales', function (Blueprint $t) {
            $t->id();
            $t->string('IdEmpresa', 11);
            $t->string('libro', 10);
            $t->string('periodo', 6);
            $t->string('clave', 80);
            $t->string('tipo', 2);
            $t->string('serie', 20);
            $t->string('numero', 20);
            $t->date('fecha');
            $t->date('vence')->nullable();
            $t->string('ruc', 15);
            $t->string('nombre', 200);
            $t->string('moneda', 3)->default('PEN');
            $t->decimal('tc', 8, 3)->nullable();
            $t->text('montos');
            $t->text('ref')->nullable();
            $t->unsignedInteger('IdUsuario')->nullable();
            $t->timestamps();
            // Un comprobante se agrega una sola vez (en un solo mes)
            $t->unique(['IdEmpresa', 'libro', 'clave'], 'sire_manuales_unico');
            $t->index(['IdEmpresa', 'libro', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sire_manuales');
    }
};
