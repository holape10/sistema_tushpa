<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->increments('ped_id');
            $table->string('ped_tip', 20)->default('Salon'); // Salon | Llevar | Delivery
            $table->date('ped_fec')->nullable();
            $table->dateTime('fecha_hora')->nullable();
            $table->dateTime('fecha_hora_modificacion')->nullable();
            $table->string('ped_est', 20)->default('Aperturado'); // Aperturado | Cerrado | Anulado
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unsignedBigInteger('pis_id')->nullable();
            $table->unsignedBigInteger('mes_id')->nullable();
            $table->unsignedInteger('mozo')->nullable();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->string('ped_cli_nom')->nullable();
            $table->string('ped_num_doc', 15)->nullable();
            $table->string('ped_dir')->nullable();
            $table->string('ped_tel', 20)->nullable();
            $table->decimal('ped_tot', 12, 2)->default(0);
            $table->decimal('icbper_val', 10, 2)->default(0);
            $table->decimal('icbper_tot', 10, 2)->default(0);
            $table->string('ped_obs')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('pedidos'); }
};