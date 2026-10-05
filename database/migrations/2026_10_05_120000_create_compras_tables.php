<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// Compras: mismas columnas que el sistema antiguo (proveedor, compras_cabecera, compras_detalle) para poder migrar datos
return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedor', function (Blueprint $table) {
            $table->increments('prov_id');
            $table->string('tdicod', 2)->nullable();
            $table->string('prov_ruc', 11)->nullable();
            $table->string('prov_raz')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->string('prov_dir')->nullable();
            $table->string('prov_cor')->nullable();
            $table->string('prov_num_con')->nullable();   // teléfono de contacto
            $table->string('prov_con')->nullable();       // nombre de contacto
            $table->string('prov_est', 11)->default('1');
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->unique(['IdEmpresa', 'prov_ruc'], 'proveedor_ruc_unico');
            $table->index('prov_raz');
        });

        Schema::create('compras_cabecera', function (Blueprint $table) {
            $table->increments('com_cab_id');
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('tdocod', 2)->nullable();
            $table->string('com_doc_ser', 4)->nullable();
            $table->string('com_doc_num', 8)->nullable();
            $table->unsignedInteger('prov_id')->nullable();
            $table->string('prov_num', 18)->nullable();
            $table->date('com_fec')->nullable();          // emisión
            $table->date('com_fec_ven')->nullable();      // vencimiento (crédito)
            $table->date('com_fec_ing')->nullable();      // ingreso de la mercadería (fecha del kardex)
            $table->string('mon_id', 3)->default('PEN');
            $table->decimal('tip_cam', 15, 3)->nullable();
            $table->decimal('com_grav', 10, 2)->default(0);
            $table->decimal('com_exo', 10, 2)->default(0);
            $table->decimal('com_inaf', 10, 2)->default(0);
            $table->decimal('subtot_com', 10, 2)->default(0);
            $table->decimal('igv_com', 10, 2)->default(0);
            $table->decimal('total_com', 15, 2)->default(0);
            $table->decimal('tot_con', 10, 2)->default(0);
            $table->decimal('tot_cre', 10, 2)->default(0);
            $table->decimal('saldofactura', 10, 2)->default(0);   // por pagar (crédito)
            $table->unsignedInteger('cre_dia_id')->nullable();
            $table->unsignedInteger('id_almacen')->nullable();
            $table->unsignedInteger('id_turno')->nullable();
            $table->string('comp_obs')->nullable();
            $table->string('est_compra')->default('Registrado');   // Registrado | Anulado
            $table->string('tipocompra')->default('Producto');
            $table->string('cod_tip_ope', 4)->default('02');
            $table->string('sunat_estado', 50)->default('PENDIENTE');
            $table->string('orden_compra')->nullable();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->unsignedInteger('usu_elimino')->nullable();
            $table->timestamps();

            $table->index(['id_empresa_negocio', 'com_fec']);
            $table->index(['prov_id', 'tdocod', 'com_doc_ser', 'com_doc_num'], 'compra_documento');
        });

        Schema::create('compras_detalle', function (Blueprint $table) {
            $table->increments('com_det_id');
            $table->unsignedInteger('com_cab_id');
            $table->unsignedInteger('pro_id')->nullable();
            $table->string('ume_cod', 3)->nullable();
            $table->string('tip_igv', 2)->default('10');
            $table->decimal('cantidad', 20, 2)->default(0);
            $table->decimal('val_uni', 10, 4)->default(0);        // costo unitario sin IGV
            $table->decimal('pre_uni', 15, 4)->default(0);        // costo unitario con IGV
            $table->decimal('com_det_subtot', 10, 2)->default(0);
            $table->decimal('com_det_igv', 10, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('flete', 10, 2)->default(0);          // flete total de la línea
            $table->decimal('flete_und', 10, 4)->default(0);
            $table->decimal('precio_costo', 10, 4)->default(0);   // costo final por unidad (con flete, en soles)
            $table->string('lote')->nullable();
            $table->date('vencimiento')->nullable();
            $table->unsignedInteger('id_almacen_pro')->nullable();
            $table->string('IdEmpresa', 11)->nullable();

            $table->foreign('com_cab_id')->references('com_cab_id')->on('compras_cabecera')->onDelete('cascade');
        });

        DB::table('modulos')->where('mod_nom', 'Compras')->where('mod_gen', 'Compras')->update(['mod_url' => '/compras']);
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_nom', 'Compras')->where('mod_gen', 'Compras')->update(['mod_url' => '#']);
        Schema::dropIfExists('compras_detalle');
        Schema::dropIfExists('compras_cabecera');
        Schema::dropIfExists('proveedor');
    }
};
