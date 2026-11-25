<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separados (apartados): una venta al crédito con adelanto cuyo producto se queda en la tienda hasta que el cliente
 * termina de pagar. La venta, el stock, la caja y los abonos son los de siempre (venta por partes y Cuentas por cobrar);
 * aquí solo se guarda que está separado, hasta cuándo y cuándo se entregó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('separados')) {
            return;
        }
        Schema::create('separados', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('numero');
            $t->unsignedBigInteger('id_empresa_negocio')->index();
            $t->string('IdEmpresa', 11)->nullable();
            $t->unsignedInteger('IdCpe_cabecera')->unique();
            $t->string('clinum', 15)->nullable();
            $t->string('clinom', 150)->nullable();
            $t->string('telefono', 20)->nullable();
            $t->date('fecha');
            $t->date('vence');
            $t->string('estado', 12)->default('SEPARADO');   // SEPARADO | ENTREGADO (anulado = la venta anulada)
            $t->timestamp('entregado_at')->nullable();
            $t->unsignedInteger('IdUsuario')->nullable();
            $t->unsignedInteger('IdUsuario_entrega')->nullable();
            $t->string('observaciones', 255)->nullable();
            $t->timestamps();
            $t->index(['id_empresa_negocio', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('separados');
    }
};
