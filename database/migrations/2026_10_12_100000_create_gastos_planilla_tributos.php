<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Gastos (servicios, alquiler, honorarios…), planilla de remuneraciones con boletas de pago
 * y configuración tributaria (régimen de renta) para el resumen de impuestos.
 */
return new class extends Migration {
    public function up(): void
    {
        // ======================= GASTOS
        Schema::create('gasto_categorias', function (Blueprint $table) {
            $table->increments('id');
            $table->string('IdEmpresa', 11)->index();
            $table->string('nombre', 60);
            $table->string('cuenta_contable', 12)->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('gastos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('IdEmpresa', 11);
            $table->unsignedBigInteger('id_empresa_negocio')->nullable();
            $table->date('fecha');
            $table->string('tdocod', 2);                  // 01 factura | 03 boleta | 02 recibo por honorarios | 14 servicios públicos | 12 ticket | 00 otro
            $table->string('serie', 6)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('prov_doc', 15)->nullable();
            $table->string('prov_nombre', 200)->nullable();
            $table->unsignedInteger('categoria_id')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 8, 3)->nullable();
            $table->decimal('base', 12, 2)->default(0);           // base imponible gravada
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('no_gravado', 12, 2)->default(0);     // exonerado, inafecto u otros cargos
            $table->decimal('retencion', 12, 2)->default(0);      // retención de 4ta categoría (recibo por honorarios)
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('credito_fiscal')->default(true);     // el IGV se usa como crédito fiscal
            $table->string('forma_pago', 10)->default('CONTADO');
            $table->unsignedInteger('id_med_pag')->nullable();
            $table->string('estado', 10)->default('Registrado');
            $table->string('origen', 10)->default('MANUAL');      // MANUAL | SIRE
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
            $table->index(['IdEmpresa', 'fecha']);
        });

        // ======================= PLANILLA
        Schema::create('planilla_trabajadores', function (Blueprint $table) {
            $table->unsignedInteger('emp_id')->primary();
            $table->string('IdEmpresa', 11)->index();
            $table->string('cargo', 100)->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_cese')->nullable();
            $table->decimal('sueldo', 10, 2)->default(0);
            $table->boolean('asignacion_familiar')->default(false);
            $table->string('sistema_pension', 10)->default('ONP');    // ONP | AFP | NINGUNO
            $table->string('afp', 20)->nullable();                     // HABITAT | INTEGRA | PRIMA | PROFUTURO
            $table->string('afp_comision', 6)->default('FLUJO');       // FLUJO | MIXTA
            $table->string('cuspp', 15)->nullable();
            $table->string('banco', 30)->nullable();
            $table->string('cuenta', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('planilla_parametros', function (Blueprint $table) {
            $table->string('IdEmpresa', 11);
            $table->unsignedSmallInteger('anio');
            $table->string('regimen_laboral', 12)->default('GENERAL');   // GENERAL | PEQUENA | MICRO
            $table->decimal('rmv', 10, 2)->default(1130);
            $table->decimal('uit', 10, 2)->default(5350);
            $table->decimal('essalud', 5, 2)->default(9);
            $table->decimal('onp', 5, 2)->default(13);
            $table->decimal('afp_aporte', 5, 2)->default(10);
            $table->decimal('afp_prima', 5, 2)->default(1.37);
            $table->decimal('afp_tope_prima', 10, 2)->default(12027.91);
            $table->json('afp_comisiones')->nullable();      // {"HABITAT":1.47,"INTEGRA":1.55,"PRIMA":1.60,"PROFUTURO":1.69}
            $table->boolean('calcular_quinta')->default(true);
            $table->timestamps();
            $table->primary(['IdEmpresa', 'anio']);
        });

        Schema::create('planillas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('IdEmpresa', 11);
            $table->char('periodo', 6);
            $table->string('estado', 10)->default('BORRADOR');    // BORRADOR | CERRADA | PAGADA
            $table->date('fecha_pago')->nullable();
            $table->decimal('total_ingresos', 12, 2)->default(0);
            $table->decimal('total_descuentos', 12, 2)->default(0);
            $table->decimal('total_neto', 12, 2)->default(0);
            $table->decimal('total_aportes', 12, 2)->default(0);
            $table->unsignedInteger('asiento_id')->nullable();
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
            $table->unique(['IdEmpresa', 'periodo']);
        });

        Schema::create('planilla_detalle', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('planilla_id');
            $table->unsignedInteger('emp_id');
            $table->string('nombre', 200);
            $table->string('dni', 15)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->string('sistema_pension', 25)->nullable();
            $table->unsignedTinyInteger('dias')->default(30);
            $table->decimal('faltas', 5, 2)->default(0);
            $table->unsignedInteger('tardanza_min')->default(0);
            $table->decimal('he25', 6, 2)->default(0);     // horas extra al 25 %
            $table->decimal('he35', 6, 2)->default(0);     // horas extra al 35 %
            $table->decimal('sueldo', 10, 2)->default(0);
            $table->decimal('asig_familiar', 10, 2)->default(0);
            $table->decimal('horas_extra', 10, 2)->default(0);
            $table->decimal('bonos', 10, 2)->default(0);
            $table->decimal('total_ingresos', 10, 2)->default(0);
            $table->decimal('desc_faltas', 10, 2)->default(0);
            $table->decimal('desc_tardanza', 10, 2)->default(0);
            $table->decimal('onp', 10, 2)->default(0);
            $table->decimal('afp_aporte', 10, 2)->default(0);
            $table->decimal('afp_prima', 10, 2)->default(0);
            $table->decimal('afp_comision', 10, 2)->default(0);
            $table->decimal('renta_quinta', 10, 2)->default(0);
            $table->decimal('adelantos', 10, 2)->default(0);
            $table->decimal('otros_descuentos', 10, 2)->default(0);
            $table->decimal('total_descuentos', 10, 2)->default(0);
            $table->decimal('neto', 10, 2)->default(0);
            $table->decimal('essalud', 10, 2)->default(0);
            $table->foreign('planilla_id')->references('id')->on('planillas')->onDelete('cascade');
            $table->unique(['planilla_id', 'emp_id']);
        });

        // ======================= TRIBUTOS
        Schema::create('tributos_config', function (Blueprint $table) {
            $table->string('IdEmpresa', 11)->primary();
            $table->string('regimen', 4)->default('RMT');          // NRUS | RER | RMT | RG
            $table->decimal('coeficiente', 6, 4)->nullable();      // coeficiente de pagos a cuenta (RG / RMT)
            $table->boolean('exonerado_igv')->default(false);      // Amazonía u otra exoneración
            $table->decimal('saldo_favor_inicial', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('conta_config', function (Blueprint $table) {
            $table->string('cta_sueldos', 12)->default('6211');
            $table->string('cta_essalud_gasto', 12)->default('6271');
            $table->string('cta_remun_pagar', 12)->default('4111');
            $table->string('cta_essalud_pagar', 12)->default('4031');
            $table->string('cta_onp_pagar', 12)->default('4032');
            $table->string('cta_afp_pagar', 12)->default('407');
            $table->string('cta_renta5_pagar', 12)->default('40173');
            $table->string('cta_renta4_pagar', 12)->default('40172');
            $table->string('cta_adelantos', 12)->default('1411');
        });

        // Datos iniciales por empresa
        $categorias = [['ALQUILER', '635', '#7c3aed'], ['ENERGÍA ELÉCTRICA', '6361', '#f59e0b'], ['AGUA', '6363', '#0ea5e9'],
            ['TELÉFONO', '6364', '#14b8a6'], ['INTERNET', '6365', '#06b6d4'], ['TRANSPORTE Y MOVILIDAD', '631', '#64748b'],
            ['MANTENIMIENTO Y REPARACIONES', '634', '#84cc16'], ['PUBLICIDAD', '637', '#ec4899'], ['HONORARIOS Y ASESORÍA', '632', '#8b5cf6'],
            ['SUMINISTROS Y ÚTILES', '656', '#22c55e'], ['TRIBUTOS Y TASAS MUNICIPALES', '643', '#ef4444'], ['COMISIONES BANCARIAS', '6791', '#475569'],
            ['OTROS GASTOS', '659', '#9ca3af']];
        foreach (DB::table('empresa')->pluck('IdEmpresa') as $ruc) {
            foreach ($categorias as [$n, $c, $col]) {
                DB::table('gasto_categorias')->insert(['IdEmpresa' => $ruc, 'nombre' => $n, 'cuenta_contable' => $c, 'color' => $col, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('tributos_config')->insertOrIgnore(['IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()]);
            // Cuenta 6365 internet (no viene en todos los planes)
            if (DB::table('conta_plan')->where('IdEmpresa', $ruc)->exists() && !DB::table('conta_plan')->where('IdEmpresa', $ruc)->where('cuenta', '6365')->exists()) {
                DB::table('conta_plan')->insert(['IdEmpresa' => $ruc, 'cuenta' => '6365', 'descripcion' => 'INTERNET', 'nivel' => 4, 'tipo' => 'GASTO',
                    'naturaleza' => 'D', 'imputable' => 1, 'estado' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // ======================= MENÚ
        DB::table('modulos')->where('mod_nom', 'Gastos')->where('mod_gen', 'Compras')->update(['mod_url' => '/gastos']);
        DB::table('modulos')->where('mod_nom', 'Reporte: Gastos')->where('mod_gen', 'Compras')->update(['mod_url' => '/gastos/reporte']);
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique();
        foreach ([['Trabajadores', '/planilla/trabajadores', 'Planilla'], ['Planillas y Boletas', '/planilla', 'Planilla'],
                  ['Parámetros de Planilla', '/planilla/parametros', 'Planilla'], ['Resumen Tributario', '/tributos', 'SIRE']] as [$nom, $url, $gen]) {
            if (DB::table('modulos')->where('mod_url', $url)->exists()) {
                continue;
            }
            $id = DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => $gen]);
            foreach ($admins as $u) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['/planilla/trabajadores', '/planilla', '/planilla/parametros', '/tributos'] as $url) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id');
            if ($id) {
                DB::table('modulos_usuario')->where('mod_id', $id)->delete();
                DB::table('modulos')->where('mod_id', $id)->delete();
            }
        }
        DB::table('modulos')->whereIn('mod_nom', ['Gastos', 'Reporte: Gastos'])->where('mod_gen', 'Compras')->update(['mod_url' => '#']);
        Schema::table('conta_config', fn(Blueprint $t) => $t->dropColumn(['cta_sueldos', 'cta_essalud_gasto', 'cta_remun_pagar', 'cta_essalud_pagar',
            'cta_onp_pagar', 'cta_afp_pagar', 'cta_renta5_pagar', 'cta_renta4_pagar', 'cta_adelantos']));
        foreach (['tributos_config', 'planilla_detalle', 'planillas', 'planilla_parametros', 'planilla_trabajadores', 'gastos', 'gasto_categorias'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
