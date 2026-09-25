<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->increments('IdProducto');
            $table->string('procod', 20)->default('');
            $table->string('pronom', 150)->default('');
            $table->string('umecod', 3)->default('UNI');
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('propun', 15, 2)->default(0); // precio de venta con IGV
            $table->unsignedBigInteger('cat_id')->nullable();
            $table->unsignedBigInteger('subcat_id')->nullable();
            $table->unsignedBigInteger('tip_pro_id')->nullable();
            $table->unsignedBigInteger('id_almacen')->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('IdEmpresa', 11)->default('');
            $table->string('imagenproducto')->nullable();
            $table->string('proest', 8)->default('Activo');
            $table->decimal('stock_min', 10, 2)->default(0);
            $table->timestamps();

            $table->index('pronom');
            $table->index('procod');
        });
    }
    public function down(): void { Schema::dropIfExists('productos'); }
};