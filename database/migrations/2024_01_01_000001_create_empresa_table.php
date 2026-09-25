<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->string('IdEmpresa', 11)->primary(); // RUC
            $table->string('NomEmpresa')->nullable();
            $table->string('LogEmpresa')->nullable();
            $table->string('Rubro')->nullable();
            $table->string('DirEmpresa')->nullable();
            $table->string('TelEmpresa', 20)->nullable();
            $table->string('CorEmpresa', 250)->nullable();
            $table->string('EstEmpresa', 10)->default('Activo');
            $table->string('wsusuario')->default('FACTURA1');
            $table->string('claveSunat')->default('Factura1');
            $table->string('passcert')->nullable();
            $table->string('certificado')->nullable();
            $table->string('produccion')->nullable();
            $table->decimal('icbper', 10, 2)->nullable();
            $table->integer('tipo_envio')->default(1);
            $table->integer('imp_pedido')->default(1);
            $table->integer('imp_venta')->default(1);
            $table->string('formato')->default('ticket');
            $table->string('tip_env_fac_id', 2)->nullable();
            $table->string('correo_envio')->nullable();
            $table->string('contrasena_envio')->nullable();
            $table->string('ticket_pantalla')->default('0');
            $table->date('fec_ini_cer')->nullable();
            $table->date('fec_fin_cer')->nullable();
            $table->integer('id_tipo_sistema')->default(1);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('empresa'); }
};