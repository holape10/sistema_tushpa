<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa_negocios', function (Blueprint $table) {
            $table->id('id_empresa_negocio');
            $table->string('IdEmpresa', 11);
            $table->string('tipo_negocio')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('estado')->default('Activo');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('web')->nullable();
            $table->string('FseEmpresa', 4)->default('F001');
            $table->unsignedInteger('FnuEmpresa')->default(0);
            $table->string('BseEmpresa', 4)->default('B001');
            $table->unsignedInteger('BnuEmpresa')->default(0);
            $table->string('codigofiscal', 10)->nullable();
            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();
            $table->string('ubigeo')->nullable();
            $table->string('cod_for_com')->default('01');
            $table->string('logo_suc')->nullable();
            $table->timestamps();

            $table->foreign('IdEmpresa')->references('IdEmpresa')->on('empresa')
                  ->onDelete('cascade')->onUpdate('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('empresa_negocios'); }
};