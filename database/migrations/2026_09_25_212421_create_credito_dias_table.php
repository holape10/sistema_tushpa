<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credito_dias', function (Blueprint $table) {
            $table->increments('cre_dia_id');
            $table->string('cre_dia_nom')->nullable();
            $table->integer('cre_dia_fac')->nullable();
            $table->string('cre_dia_tip')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('credito_dias'); }
};