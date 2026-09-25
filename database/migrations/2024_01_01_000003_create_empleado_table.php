<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empleado', function (Blueprint $table) {
            $table->increments('emp_id');
            $table->string('emp_nom')->nullable();
            $table->string('emp_ape_pat')->nullable();
            $table->string('emp_ape_mat')->nullable();
            $table->string('emp_dir')->nullable();
            $table->string('emp_tel')->nullable();
            $table->string('emp_cel')->nullable();
            $table->string('emp_cor')->nullable();
            $table->string('emp_num_doc', 15)->nullable();
            $table->string('tdicod', 2)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('est_cod', 1)->nullable();
            $table->unsignedInteger('rol_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('empleado'); }
};