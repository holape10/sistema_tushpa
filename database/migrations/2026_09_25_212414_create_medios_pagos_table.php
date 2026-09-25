<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medios_pagos', function (Blueprint $table) {
            $table->increments('id_med_pag');
            $table->string('nom_med_pag')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->decimal('comision', 10, 2)->nullable();
            $table->string('predeterminado')->default('0');
            $table->string('cod_sunat')->nullable();
            $table->string('tipo_medio')->nullable();
            $table->decimal('comision_mont', 10, 2)->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('medios_pagos'); }
};