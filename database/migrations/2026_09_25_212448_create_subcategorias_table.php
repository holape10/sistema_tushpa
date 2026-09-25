<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subcategorias', function (Blueprint $table) {
            $table->increments('subcat_id');
            $table->string('subcat_nom', 50)->nullable();
            $table->unsignedBigInteger('cat_id')->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('color', 10)->nullable();
            $table->string('IdEmpresa', 11)->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('subcategorias'); }
};