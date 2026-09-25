<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->integer('tip_pro_id')->default(0);
            $table->increments('cat_id');
            $table->string('cat_nom', 50)->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->tinyInteger('impresora')->nullable();
            $table->string('tipo', 10)->nullable();
            $table->string('color', 10)->nullable();
            $table->boolean('cat_acom')->default(0);
            $table->boolean('visible')->default(1);
            $table->boolean('predeterminado')->default(0);
            $table->index('cat_nom');
        });
    }
    public function down(): void { Schema::dropIfExists('categorias'); }
};