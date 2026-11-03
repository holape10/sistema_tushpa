<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de las compras del SIRE: el XML de cada factura recibida (lo que se compró, línea por línea), la cuenta
 * contable de cada línea y las reglas que el sistema aprende (este proveedor + esta descripción → esta cuenta).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras_xml')) {
            Schema::create('compras_xml', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->string('ruc_emisor', 11);
                $t->string('razon_emisor', 200)->nullable();
                $t->string('tipo', 2);
                $t->string('serie', 4);
                $t->string('numero', 8);
                $t->date('fecha')->nullable();
                $t->string('moneda', 3)->default('PEN');
                $t->decimal('igv', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->string('ruc_comprador', 15)->nullable();
                $t->timestamps();
                $t->unique(['IdEmpresa', 'ruc_emisor', 'tipo', 'serie', 'numero'], 'compras_xml_unico');
            });
        }
        if (! Schema::hasTable('compras_xml_lineas')) {
            Schema::create('compras_xml_lineas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('compra_xml_id')->constrained('compras_xml')->cascadeOnDelete();
                $t->unsignedSmallInteger('orden')->default(1);
                $t->string('codigo', 60)->nullable();
                $t->string('descripcion', 500);
                $t->string('unidad', 10)->nullable();
                $t->decimal('cantidad', 14, 4)->default(0);
                $t->decimal('precio', 14, 4)->default(0);
                $t->decimal('base', 14, 2)->default(0);
                $t->decimal('igv', 14, 2)->default(0);
                $t->string('cuenta', 12)->nullable();
            });
        }
        if (! Schema::hasTable('compras_cuentas_reglas')) {
            Schema::create('compras_cuentas_reglas', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->string('ruc_emisor', 11);
                $t->string('clave', 120);
                $t->string('cuenta', 12);
                $t->timestamps();
                $t->unique(['IdEmpresa', 'ruc_emisor', 'clave'], 'compras_reglas_unico');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_cuentas_reglas');
        Schema::dropIfExists('compras_xml_lineas');
        Schema::dropIfExists('compras_xml');
    }
};
