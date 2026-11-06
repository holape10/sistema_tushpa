<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de las ventas del SIRE: el XML de cada comprobante que emitió la empresa (lo que vendió, línea por línea),
 * para verlo junto a su registro, sacar su PDF y revisar los montos antes del .dbf. Lo sube el contador (sueltos o en .zip).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ventas_xml')) {
            Schema::create('ventas_xml', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->string('doc_cliente', 15)->nullable();
                $t->string('razon_cliente', 200)->nullable();
                $t->string('tipo', 2);
                $t->string('serie', 4);
                $t->string('numero', 8);
                $t->date('fecha')->nullable();
                $t->string('moneda', 3)->default('PEN');
                $t->decimal('igv', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->timestamps();
                // Lo emitió la empresa: tipo, serie y número no se repiten
                $t->unique(['IdEmpresa', 'tipo', 'serie', 'numero'], 'ventas_xml_unico');
            });
        }
        if (! Schema::hasTable('ventas_xml_lineas')) {
            Schema::create('ventas_xml_lineas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('venta_xml_id')->constrained('ventas_xml')->cascadeOnDelete();
                $t->unsignedSmallInteger('orden')->default(1);
                $t->string('codigo', 60)->nullable();
                $t->string('descripcion', 500);
                $t->string('unidad', 10)->nullable();
                $t->decimal('cantidad', 14, 4)->default(0);
                $t->decimal('precio', 14, 4)->default(0);
                $t->decimal('base', 14, 2)->default(0);
                $t->decimal('igv', 14, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_xml_lineas');
        Schema::dropIfExists('ventas_xml');
    }
};
