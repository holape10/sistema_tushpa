<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

// Cuentas por cobrar (ventas al crédito) y por pagar (compras al crédito), con los nombres del sistema antiguo.
// Cada cuenta tiene sus pagos (_detalle) y los medios de cada pago (_medios).
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cuentas_cobrar', function (Blueprint $table) {
            $table->increments('cue_cob_id');
            $table->unsignedInteger('IdCpe_cabecera')->unique();
            $table->unsignedInteger('clicod')->nullable();
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('abono', 12, 2)->default(0);     // acumulado pagado
            $table->decimal('saldo', 12, 2)->default(0);
            $table->date('fec_ven')->nullable();
            $table->string('estado_cob', 15)->default('PENDIENTE');   // PENDIENTE | PARCIAL | PAGADO | ANULADO
            $table->date('fec_pago')->nullable();            // fecha del último pago
            $table->timestamps();
            $table->index(['id_empresa_negocio', 'estado_cob']);
        });

        Schema::create('cuentas_cobrar_detalle', function (Blueprint $table) {
            $table->increments('cue_cob_det_id');
            $table->unsignedInteger('cue_cob_id')->index();
            $table->string('numero_recibo', 20)->nullable();
            $table->date('fec_dep')->nullable();              // fecha del cobro
            $table->decimal('abono', 12, 2)->default(0);
            $table->decimal('saldo_detalle', 12, 2)->default(0);   // saldo que quedó después de este cobro
            $table->string('num_oper')->nullable();
            $table->string('comentario')->nullable();
            $table->string('est_cue_cob_det', 10)->default('REGISTRADO');   // REGISTRADO | ANULADO
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('mov_caj_id')->nullable();   // ingreso de caja del efectivo
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->unsignedInteger('IdUsuario_anula')->nullable();
            $table->string('motivo_anula', 100)->nullable();
            $table->dateTime('fec_reg')->nullable();
        });

        Schema::create('cuentas_cobrar_medios', function (Blueprint $table) {
            $table->increments('cue_cob_med_id');
            $table->unsignedInteger('cue_cob_det_id')->index();
            $table->unsignedInteger('med_pag_id');
            $table->decimal('monto', 12, 2);
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });

        Schema::create('cuentas_pagar', function (Blueprint $table) {
            $table->increments('cue_pag_id');
            $table->unsignedInteger('com_cab_id')->unique();
            $table->unsignedInteger('clicod')->nullable();    // proveedor (prov_id), como en el sistema antiguo
            $table->string('IdEmpresa', 11)->nullable();
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('abono', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0);
            $table->date('fec_ven')->nullable();
            $table->string('estado_cob', 15)->default('PENDIENTE');
            $table->date('fec_pago')->nullable();
            $table->timestamps();
            $table->index(['id_empresa_negocio', 'estado_cob']);
        });

        Schema::create('cuentas_pagar_detalle', function (Blueprint $table) {
            $table->increments('cue_pag_det_id');
            $table->unsignedInteger('cue_pag_id')->index();
            $table->string('numero_recibo', 20)->nullable();
            $table->date('fec_dep')->nullable();
            $table->decimal('abono', 12, 2)->default(0);
            $table->decimal('saldo_detalle', 12, 2)->default(0);
            $table->string('num_oper')->nullable();
            $table->string('comentario')->nullable();
            $table->string('est_cue_pag_det', 10)->default('REGISTRADO');
            $table->unsignedInteger('id_turno')->nullable();
            $table->unsignedInteger('mov_caj_id')->nullable();   // salida de caja si se pagó con efectivo de caja
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->unsignedInteger('IdUsuario_anula')->nullable();
            $table->string('motivo_anula', 100)->nullable();
            $table->dateTime('fec_reg')->nullable();
        });

        Schema::create('cuentas_pagar_medios', function (Blueprint $table) {
            $table->increments('cue_pag_med_id');
            $table->unsignedInteger('cue_pag_det_id')->index();
            $table->unsignedInteger('med_pag_id');
            $table->decimal('monto', 12, 2);
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
        });

        // Salida de caja para los pagos a proveedores en efectivo (la entrada 007 CUENTAS POR COBRAR ya existe)
        DB::table('tiposcaja')->insertOrIgnore(['tip_caj_id' => '009', 'tip_caj_nom' => 'PAGO A PROVEEDORES', 'tipo' => 'SALIDA']);

        // Cuentas de las ventas y compras al crédito que ya existen
        foreach (DB::table('cpe_cabecera')->where('estadopago', 'CREDITO')->whereNull('ccabaj')->get() as $c) {
            DB::table('cuentas_cobrar')->insert([
                'IdCpe_cabecera' => $c->IdCpe_cabecera, 'clicod' => $c->clicod, 'IdEmpresa' => $c->IdEmpresa,
                'id_empresa_negocio' => $c->id_empresa_negocio, 'total' => $c->ccaitv, 'saldo' => $c->ccaitv,
                'fec_ven' => $c->ccafve, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (DB::table('compras_cabecera')->where('est_compra', 'Registrado')->where('tot_cre', '>', 0)->get() as $c) {
            DB::table('cuentas_pagar')->insert([
                'com_cab_id' => $c->com_cab_id, 'clicod' => $c->prov_id, 'IdEmpresa' => $c->IdEmpresa,
                'id_empresa_negocio' => $c->id_empresa_negocio, 'total' => $c->total_com, 'saldo' => $c->total_com,
                'fec_ven' => $c->com_fec_ven, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Menú
        DB::table('modulos')->where('mod_gen', 'Cuentas Cobrar')->where('mod_nom', 'Cuentas Cobrar')->update(['mod_url' => '/cuentas/cobrar']);
        DB::table('modulos')->where('mod_gen', 'Cuentas Cobrar')->where('mod_nom', 'Reporte: Cuentas por Cobrar')->update(['mod_url' => '/cuentas/cobrar/reporte']);
        DB::table('modulos')->where('mod_gen', 'Cuentas Pagar')->where('mod_nom', 'Cuentas Pagar')->update(['mod_url' => '/cuentas/pagar']);
        $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'Reporte: Cuentas por Pagar', 'mod_url' => '/cuentas/pagar/reporte', 'mod_gen' => 'Cuentas Pagar']);
        foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario') as $userId) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_nom', 'Reporte: Cuentas por Pagar')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        DB::table('modulos')->whereIn('mod_gen', ['Cuentas Cobrar', 'Cuentas Pagar'])->update(['mod_url' => '#']);
        DB::table('tiposcaja')->where('tip_caj_id', '009')->delete();
        foreach (['cuentas_pagar_medios', 'cuentas_pagar_detalle', 'cuentas_pagar', 'cuentas_cobrar_medios', 'cuentas_cobrar_detalle', 'cuentas_cobrar'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
