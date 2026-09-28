<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cpe_cabecera', function (Blueprint $table) {
            $table->increments('IdCpe_cabecera');
            $table->string('tdocod', 2);
            $table->string('serdoc', 4);
            $table->unsignedInteger('numdoc');
            $table->string('topcod', 4)->default('0101');
            $table->date('ccafem');
            $table->date('ccafve')->nullable();
            $table->timestamp('fecha_hora')->useCurrent();

            $table->string('tdicod', 1)->default('1');
            $table->string('ccandi', 15)->default('00000000');
            $table->string('ccanom', 120)->default('VENTA AL PORTADOR');
            $table->string('direccion', 150)->nullable();
            $table->unsignedInteger('clicod')->nullable();
            $table->string('clicorcli', 50)->nullable();
            $table->string('telefono_cliente', 20)->nullable();

            $table->string('moncod', 3)->default('PEN');
            $table->decimal('ccatvg', 10, 2)->default(0);   // gravado
            $table->decimal('ccaigv', 10, 2)->default(0);
            $table->decimal('ccatexo', 10, 2)->default(0);  // exonerado
            $table->decimal('ccatinaf', 10, 2)->default(0);
            $table->decimal('ccaitv', 12, 2)->default(0);   // importe total
            $table->decimal('totalcontado', 10, 2)->default(0);
            $table->decimal('totalcredito', 10, 2)->default(0);
            $table->decimal('paga', 10, 2)->default(0);
            $table->decimal('vuelto', 10, 2)->default(0);

            $table->string('estadopago', 15)->default('CONTADO');
            $table->unsignedInteger('cre_dia_id')->nullable();
            $table->string('ccaobs', 100)->nullable();
            $table->tinyInteger('consumo')->default(0);

            $table->unsignedInteger('ped_id')->nullable();
            $table->string('ped_tip', 10)->nullable();
            $table->unsignedInteger('pis_id')->nullable();
            $table->unsignedInteger('mes_id')->nullable();
            $table->unsignedInteger('mozo')->nullable();
            $table->unsignedInteger('IdUsuario');
            $table->unsignedInteger('IdUsuario_ven')->nullable();

            $table->string('IdEmpresa', 11);
            $table->unsignedBigInteger('id_empresa_negocio');
            $table->unsignedInteger('id_almacen')->nullable();
            $table->unsignedInteger('id_turno')->default(0);
            $table->string('cuenta12', 8)->default('121201');

            $table->string('ccabaj', 30)->nullable();       // comunicado de baja (los reportes filtran whereNull)
            $table->string('est_sunat', 30)->nullable();    // PENDIENTE hasta que exista el envío
            $table->tinyInteger('enviado')->default(0);

            $table->unique(['id_empresa_negocio', 'tdocod', 'serdoc', 'numdoc'], 'cpe_doc_unico');
            $table->index('ped_id');
            $table->index('ccafem');
        });

        Schema::create('cpe_detalle', function (Blueprint $table) {
            $table->increments('IdCpe_detalle');
            $table->unsignedInteger('IdCpe_cabecera');
            $table->unsignedInteger('IdProducto')->nullable();
            $table->unsignedInteger('IdProducto_rel')->nullable();
            $table->string('procod', 20)->default('');
            $table->string('umecod', 3)->default('NIU');
            $table->decimal('cdecan', 10, 2)->default(0);
            $table->string('cdedes', 150)->default('');
            $table->decimal('cdevun', 10, 2)->nullable();   // valor unitario
            $table->decimal('cdepuni', 10, 2)->default(0);  // precio unitario
            $table->decimal('cdepve', 10, 2)->default(0);   // subtotal sin IGV
            $table->decimal('cdeigv', 10, 2)->default(0);
            $table->decimal('cdevve', 10, 2)->default(0);   // total de la línea
            $table->string('tigcod', 2)->default('20');
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('cpe_det_factor', 10, 2)->default(1);
            $table->unsignedInteger('id_almacen_pro')->nullable();

            $table->foreign('IdCpe_cabecera')->references('IdCpe_cabecera')->on('cpe_cabecera')->onDelete('cascade');
        });

        Schema::create('venta_medio_pago', function (Blueprint $table) {
            $table->increments('ven_med_pag_id');
            $table->unsignedInteger('IdCpe_cabecera')->index();
            $table->unsignedInteger('id_med_pag');
            $table->decimal('monto', 10, 2);
            $table->unsignedInteger('id_turno')->default(0);
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('venta_medio_pago');
        Schema::dropIfExists('cpe_detalle');
        Schema::dropIfExists('cpe_cabecera');
    }
};