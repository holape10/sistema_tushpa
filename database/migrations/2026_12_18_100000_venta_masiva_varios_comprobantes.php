<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venta masiva con más de un comprobante por cliente (ej. un cliente con dos sucursales: un comprobante para cada una).
 *  - cliente.etiqueta_mensual: detalle del comprobante principal (ej. "LOCAL CENTRO"), opcional.
 *  - cliente_mensual_extras: los otros comprobantes del mes, cada uno con su detalle, comprobante y monto.
 * El detalle se agrega a la descripción: "SISTEMA DE FACTURACION ELECTRONICA OCTUBRE 2026 - LOCAL PUNCHANA".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cliente', 'etiqueta_mensual')) {
            Schema::table('cliente', function (Blueprint $t) {
                $t->string('etiqueta_mensual', 80)->nullable();
            });
        }
        if (! Schema::hasTable('cliente_mensual_extras')) {
            Schema::create('cliente_mensual_extras', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('clicod')->index();
                $t->string('etiqueta', 80)->nullable();
                $t->string('comprobante', 2)->default('13');
                $t->decimal('monto', 10, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_mensual_extras');
        if (Schema::hasColumn('cliente', 'etiqueta_mensual')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->dropColumn('etiqueta_mensual'));
        }
    }
};
