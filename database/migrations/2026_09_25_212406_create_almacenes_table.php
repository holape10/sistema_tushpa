<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->increments('id_almacen');
            $table->string('descripcion', 150)->default('');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->tinyInteger('predeterminado')->nullable();
            $table->string('ubigeo', 100)->default('');
            $table->string('direccion', 150)->default('');
            $table->string('codigo', 4)->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('almacenes'); }
};