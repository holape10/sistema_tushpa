<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos_detalle', function (Blueprint $table) {
            $table->increments('ped_det_id');
            $table->unsignedInteger('ped_id');
            $table->unsignedBigInteger('IdProducto');
            $table->string('IdEmpresa', 11)->nullable();
            $table->string('descripcion')->nullable();
            $table->string('detalle')->nullable();
            $table->decimal('ped_det_can', 10, 2)->default(1);
            $table->decimal('ped_det_pre', 12, 2)->default(0);
            $table->string('item_obs')->nullable();
            $table->string('estadoitem', 20)->default('Ingresado'); // Ingresado | En Preparacion | Despachado | Eliminado
            $table->string('impreso', 10)->default('imprimir');
            $table->tinyInteger('icbper_ind')->default(0);
            $table->decimal('item_facturado', 10, 2)->default(0);
            $table->dateTime('fecha_hora')->nullable();
            $table->dateTime('fecha_hora_despacho')->nullable();

            $table->foreign('ped_id')->references('ped_id')->on('pedidos')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('pedidos_detalle'); }
};