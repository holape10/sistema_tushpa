<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Envío a SUNAT (individual y resumen diario) con los nombres de tablas y campos del sistema antiguo
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            // Respuesta de SUNAT
            $table->string('ccasunrescod', 20)->nullable()->after('est_sunat');   // código de respuesta (0 = aceptado)
            $table->string('ccadessun', 500)->nullable()->after('ccasunrescod');  // descripción / observaciones
            $table->string('ccaqr', 85)->nullable()->after('ccadessun');          // hash (DigestValue) para el QR
            $table->unsignedInteger('res_id')->nullable()->after('ccaqr');        // resumen diario en el que viajó
            // Notas de crédito / débito: documento que modifican
            $table->char('tipnot', 2)->nullable();                                // motivo (catálogo 09 / 10)
            $table->string('tdocod_ref', 2)->nullable();
            $table->string('serie_ref', 4)->nullable();
            $table->string('num_ref', 11)->nullable();
            $table->date('ccafem_ref')->nullable();
            $table->unsignedInteger('IdCpe_cabecera_ref')->nullable();
            $table->index(['est_sunat', 'tdocod']);
        });

        Schema::create('resumenes', function (Blueprint $table) {
            $table->increments('res_id');
            $table->date('res_fec_com')->nullable();       // fecha de emisión de los comprobantes
            $table->date('res_fec_gen')->nullable();       // fecha de generación del resumen
            $table->string('res_ticket')->nullable();
            $table->text('res_est')->nullable();           // descripción del estado
            $table->string('res_cod_est')->nullable();     // código de estado del ticket (0, 98, 99)
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->string('res_tip')->nullable();         // RC = resumen de boletas
            $table->text('error')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_code_ticket')->nullable();
            $table->text('error_ticket')->nullable();
            $table->string('tip_res_com', 3)->nullable();
            $table->string('nom_arch')->nullable();        // RUC-RC-YYYYMMDD-N
            $table->string('est_sunat')->nullable();       // ENVIADO | ACEPTADO | RECHAZADO | ERROR
            // campos propios del sistema nuevo
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedInteger('res_cor')->nullable(); // correlativo del día de generación (permite varios por día)
            $table->unsignedInteger('res_cant')->default(0);
            $table->decimal('res_total', 12, 2)->default(0);
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamp('fecha_hora')->nullable()->useCurrent();
            $table->unique(['IdEmpresa', 'res_fec_gen', 'res_cor'], 'resumen_correlativo_unico');
        });

        Schema::create('tipo_nota_credito', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('nccod', 2)->default('');
            $table->string('ncdes', 100)->nullable();
            $table->string('ncest', 8)->default('Activo');
        });
        DB::table('tipo_nota_credito')->insert(collect([
            '01' => 'ANULACION DE LA OPERACION', '02' => 'ANULACION POR ERROR EN EL RUC',
            '03' => 'CORRECION POR ERROR EN LA DESCRIPCION', '04' => 'DESCUENTO GLOBAL', '05' => 'DESCUENTO POR ITEM',
            '06' => 'DEVOLUCION TOTAL', '07' => 'DEVOLUCION POR ITEM', '08' => 'BONIFICACION', '09' => 'DISMINUCION EN EL VALOR',
        ])->map(fn($d, $c) => ['nccod' => $c, 'ncdes' => $d])->values()->all());

        Schema::create('tipo_nota_debito', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ndcod', 2)->default('');
            $table->string('nddes', 100)->nullable();
            $table->string('ndest', 8)->default('Activo');
        });
        DB::table('tipo_nota_debito')->insert([
            ['ndcod' => '01', 'nddes' => 'INTERESES POR MORA'],
            ['ndcod' => '02', 'nddes' => 'AUMENTO EN EL VALOR'],
            ['ndcod' => '03', 'nddes' => 'PENALIDADES'],
        ]);

        if (!Schema::hasTable('tipo_envio_facturacion')) {
            Schema::create('tipo_envio_facturacion', function (Blueprint $table) {
                $table->string('tip_env_fac_id', 11)->primary();
                $table->string('tip_env_fac_des')->nullable();
            });
            DB::table('tipo_envio_facturacion')->insert([
                ['tip_env_fac_id' => '01', 'tip_env_fac_des' => 'SUNAT'],
                ['tip_env_fac_id' => '02', 'tip_env_fac_des' => 'OSE'],
            ]);
        }

        // Notas en el catálogo de tipos de documento (no se emiten desde caja)
        foreach (['07' => 'NOTA DE CREDITO ELECTRONICA', '08' => 'NOTA DE DEBITO ELECTRONICA'] as $cod => $des) {
            if (!DB::table('tipo_documento')->where('tdocod', $cod)->exists()) {
                DB::table('tipo_documento')->insert(['tdocod' => $cod, 'tdodes' => $des, 'caja' => 0, 'ventas' => 1]);
            }
        }

        // Los comprobantes electrónicos ya emitidos quedan pendientes de envío
        DB::table('cpe_cabecera')->whereIn('tdocod', ['01', '03', '07', '08'])->whereNull('est_sunat')->update(['est_sunat' => 'PENDIENTE']);

        DB::table('modulos')->where('mod_nom', 'Envío de Comprobantes')->update(['mod_url' => '/sunat/envios']);
        DB::table('modulos')->where('mod_nom', 'Resumen Diario')->update(['mod_url' => '/sunat/resumenes']);
    }

    public function down(): void
    {
        DB::table('modulos')->whereIn('mod_nom', ['Envío de Comprobantes', 'Resumen Diario'])->update(['mod_url' => '#']);
        DB::table('tipo_documento')->whereIn('tdocod', ['07', '08'])->delete();
        Schema::dropIfExists('tipo_nota_debito');
        Schema::dropIfExists('tipo_nota_credito');
        Schema::dropIfExists('resumenes');
        Schema::table('cpe_cabecera', function (Blueprint $table) {
            $table->dropIndex(['est_sunat', 'tdocod']);
            $table->dropColumn(['ccasunrescod', 'ccadessun', 'ccaqr', 'res_id', 'tipnot', 'tdocod_ref', 'serie_ref',
                'num_ref', 'ccafem_ref', 'IdCpe_cabecera_ref']);
        });
    }
};
