<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipo_producto', function (Blueprint $table) {
            $table->increments('tip_pro_id');
            $table->string('tip_pro_nom', 100)->nullable();
            $table->string('cta_contable_70', 15)->nullable();
            $table->string('cta_contable_12', 15)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('IdEmpresa', 20)->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('tipo_producto'); }
};