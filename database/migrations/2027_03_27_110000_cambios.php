<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios de producto ("cambio sí, devolución no"): el cliente devuelve algo de una venta y se lleva otro de igual o
 * mayor valor. La venta original no se toca; lo devuelto entra al stock, lo nuevo sale y la diferencia se cobra con su
 * propio comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cambios')) {
            Schema::create('cambios', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('numero');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unsignedInteger('IdCpe_cabecera')->index();        // la venta original
                $t->unsignedInteger('IdCpe_diferencia')->nullable();   // la venta de la diferencia (si se cobró)
                $t->unsignedInteger('id_almacen');
                $t->decimal('devuelto', 12, 2);
                $t->decimal('lleva', 12, 2);
                $t->decimal('diferencia', 12, 2);
                $t->string('motivo', 150)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('cambios_detalle')) {
            Schema::create('cambios_detalle', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cambio_id')->index();
                $t->string('tipo', 8);                                 // DEVUELVE | LLEVA
                $t->unsignedInteger('IdProducto');
                $t->unsignedInteger('IdCpe_detalle')->nullable();      // la línea de la venta original (lo devuelto)
                $t->string('descripcion', 150);
                $t->decimal('cantidad', 12, 3);
                $t->decimal('precio', 12, 2);
                $t->decimal('total', 12, 2);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_detalle');
        Schema::dropIfExists('cambios');
    }
};
