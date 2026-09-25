<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('producto_stock', function (Blueprint $table) {
            $table->increments('pro_sto_id');
            $table->unsignedBigInteger('IdProducto');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unsignedBigInteger('id_almacen')->nullable();
            $table->decimal('stock', 10, 2)->default(0);
            $table->decimal('stock_inicial', 10, 2)->default(0);
        });
    }
    public function down(): void { Schema::dropIfExists('producto_stock'); }
};