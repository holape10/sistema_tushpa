<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pisos', function (Blueprint $table) {
            $table->increments('pis_id');
            $table->string('pis_nom')->nullable();
            $table->string('emp_id', 11)->nullable(); // IdEmpresa
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('pisos'); }
};