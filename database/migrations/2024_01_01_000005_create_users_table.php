<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('IdUsuario');
            $table->string('name', 100)->default('');
            $table->string('apeusu', 100)->default('');
            $table->string('email', 100)->unique();
            $table->string('password', 200);
            $table->rememberToken();
            $table->tinyInteger('estusu')->default(1);
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unsignedInteger('emp_id')->default(0);
            $table->timestamps();

            $table->foreign('emp_id')->references('emp_id')->on('empleado')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};