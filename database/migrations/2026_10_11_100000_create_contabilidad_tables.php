<?php
use App\Support\Contabilidad\Pcge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Contabilidad: plan contable (PCGE), cuentas que usa la centralización, asientos (libro diario) y cierre de periodos.
 * Todo por empresa (RUC): las sucursales de una empresa comparten la contabilidad.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('conta_plan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('IdEmpresa', 11);
            $table->string('cuenta', 12);
            $table->string('descripcion', 200);
            $table->unsignedTinyInteger('nivel');
            $table->string('tipo', 12);                 // ACTIVO | PASIVO | PATRIMONIO | GASTO | INGRESO | RESULTADO | ANALITICA | ORDEN
            $table->char('naturaleza', 1);              // D deudora | A acreedora
            $table->boolean('imputable')->default(true); // recibe movimientos (las de título no)
            $table->string('estado', 10)->default('Activo');
            $table->timestamps();
            $table->unique(['IdEmpresa', 'cuenta']);
        });

        Schema::create('conta_config', function (Blueprint $table) {
            $table->string('IdEmpresa', 11)->primary();
            $table->string('cta_caja', 12)->default('101101');
            $table->string('cta_banco', 12)->default('104101');
            $table->string('cta_por_cobrar', 12)->default('121201');
            $table->string('cta_igv', 12)->default('401111');
            $table->string('cta_ventas', 12)->default('701111');
            $table->string('cta_ventas_exo', 12)->default('701111');
            $table->string('cta_por_pagar', 12)->default('421201');
            $table->string('cta_compras', 12)->default('601111');
            $table->string('cta_mercaderias', 12)->default('201111');
            $table->string('cta_variacion', 12)->default('611111');
            $table->string('cta_costo_ventas', 12)->default('691111');
            $table->boolean('asiento_costo')->default(true);     // centralizar el costo de ventas (69 / 20)
            $table->boolean('asiento_destino')->default(true);   // asiento de destino de compras (20 / 61)
            $table->timestamps();
        });

        Schema::create('conta_asientos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('IdEmpresa', 11);
            $table->char('periodo', 6);                 // AAAAMM
            $table->char('subdiario', 2);               // 05 ventas | 11 compras | 01 caja y bancos | 35 diario | 00 apertura
            $table->unsignedInteger('numero');          // correlativo por periodo y subdiario
            $table->date('fecha');
            $table->string('glosa', 200);
            $table->string('origen', 12);               // VENTAS | COBRANZAS | COMPRAS | PAGOS | MANUAL | APERTURA
            $table->string('tdocod', 2)->nullable();
            $table->string('documento', 30)->nullable();
            $table->string('ref_tabla', 20)->nullable();
            $table->unsignedInteger('ref_id')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
            $table->unique(['IdEmpresa', 'periodo', 'subdiario', 'numero'], 'conta_asiento_numero');
            $table->index(['IdEmpresa', 'periodo', 'origen']);
        });

        Schema::create('conta_asiento_detalle', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('asiento_id');
            $table->string('cuenta', 12);
            $table->decimal('debe', 14, 2)->default(0);
            $table->decimal('haber', 14, 2)->default(0);
            $table->string('glosa', 150)->nullable();
            $table->string('anexo_doc', 15)->nullable();   // RUC / DNI del cliente o proveedor
            $table->string('anexo_nombre', 150)->nullable();
            $table->string('documento', 30)->nullable();
            $table->foreign('asiento_id')->references('id')->on('conta_asientos')->onDelete('cascade');
            $table->index('cuenta');
        });

        Schema::create('conta_periodos', function (Blueprint $table) {
            $table->string('IdEmpresa', 11);
            $table->char('periodo', 6);
            $table->string('estado', 10)->default('CERRADO');
            $table->unsignedInteger('IdUsuario')->nullable();
            $table->timestamps();
            $table->primary(['IdEmpresa', 'periodo']);
        });

        // Cuenta contable de cada medio de pago (vacío = caja si es efectivo, banco si no)
        Schema::table('medios_pagos', function (Blueprint $table) {
            $table->string('cuenta_contable', 12)->nullable();
        });

        // Plan contable y configuración para cada empresa (toma las cuentas de CONCAR si ya estaban configuradas)
        foreach (DB::table('empresa')->pluck('IdEmpresa') as $ruc) {
            DB::table('conta_plan')->insert(Pcge::filas($ruc));
            $concar = Schema::hasTable('concar_config') ? DB::table('concar_config')->where('IdEmpresa', $ruc)->first() : null;
            $config = ['IdEmpresa' => $ruc, 'created_at' => now(), 'updated_at' => now()];
            if ($concar) {
                foreach (['cta_por_cobrar', 'cta_igv', 'cta_ventas', 'cta_ventas_exo', 'cta_compras', 'cta_por_pagar'] as $c) {
                    $config[$c] = $concar->$c;
                }
            }
            DB::table('conta_config')->insert($config);
        }

        // Menú: los 4 módulos existentes + libros y estados financieros
        $urls = ['Plan Contable' => '/contabilidad/plan', 'Centralizar Ventas' => '/contabilidad/centralizar/ventas',
                 'Centralizar Compras' => '/contabilidad/centralizar/compras', 'Libro Diario' => '/contabilidad/diario'];
        foreach ($urls as $nom => $url) {
            DB::table('modulos')->where('mod_nom', $nom)->where('mod_gen', 'Contabilidad')->update(['mod_url' => $url]);
        }
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique();
        foreach (['Libro Mayor' => '/contabilidad/mayor', 'Balance de Comprobación' => '/contabilidad/balance',
                  'Estados Financieros' => '/contabilidad/estados'] as $nom => $url) {
            if (DB::table('modulos')->where('mod_url', $url)->exists()) {
                continue;
            }
            $id = DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Contabilidad']);
            foreach ($admins as $u) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['/contabilidad/mayor', '/contabilidad/balance', '/contabilidad/estados'] as $url) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id');
            if ($id) {
                DB::table('modulos_usuario')->where('mod_id', $id)->delete();
                DB::table('modulos')->where('mod_id', $id)->delete();
            }
        }
        DB::table('modulos')->where('mod_gen', 'Contabilidad')->update(['mod_url' => '#']);
        Schema::table('medios_pagos', fn(Blueprint $t) => $t->dropColumn('cuenta_contable'));
        foreach (['conta_periodos', 'conta_asiento_detalle', 'conta_asientos', 'conta_config', 'conta_plan'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
