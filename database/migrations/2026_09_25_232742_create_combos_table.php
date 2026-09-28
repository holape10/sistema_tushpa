<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->increments('comb_id');
            $table->unsignedBigInteger('IdProducto_rel'); // el combo (cabecera)
            $table->unsignedBigInteger('IdProducto_comb'); // el producto/preparado que contiene
            $table->decimal('prod_comb_cant', 15, 2)->default(1);
        });
    }
    public function down(): void { Schema::dropIfExists('combos'); }
};