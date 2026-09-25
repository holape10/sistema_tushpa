<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->increments('mes_id');
            $table->string('mes_nom')->nullable();
            $table->string('mes_est')->default('Libre'); // Libre / Ocupada
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unsignedBigInteger('pis_id')->nullable();
            $table->index(['id_empresa_negocio', 'mes_est']);
        });
    }
    public function down(): void { Schema::dropIfExists('mesas'); }
};