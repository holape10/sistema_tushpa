<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Socios (clubes y asociaciones).
 *  - socio_config: lo que configura el propio usuario (meses para suspender, edad máxima de hijos, parentescos, producto de la cuota).
 *  - socio_categorias: categoría y su cuota ordinaria mensual.
 *  - socios: el titular es un cliente (cliente.clicod) para que el comprobante salga a su nombre; socio_familiares: sus dependientes.
 *  - socio_cargos: la deuda del socio (cuota del mes, extraordinaria, multa, ingreso). Se cancela o amortiza al cobrar.
 *  - socio_pagos: qué comprobante pagó qué cargo (si el comprobante se anula, el cargo vuelve a quedar pendiente).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('socio_config')) {
            Schema::create('socio_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->unsignedTinyInteger('meses_suspension')->default(3);   // 0 = no suspender automáticamente
                $t->unsignedTinyInteger('edad_max_hijos')->default(25);
                $t->string('parentescos', 255)->default('CONYUGE,HIJO(A),PADRE,MADRE');
                $t->unsignedInteger('IdProducto_ordinaria')->nullable();  // concepto (y cuentas contables) de la cuota mensual
                $t->string('ultimo_periodo', 6)->nullable();              // último mes generado
            });
        }

        if (!Schema::hasTable('socio_categorias')) {
            Schema::create('socio_categorias', function (Blueprint $t) {
                $t->increments('cat_soc_id');
                $t->string('nombre', 60);
                $t->decimal('cuota', 10, 2)->default(0);
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (!Schema::hasTable('socios')) {
            Schema::create('socios', function (Blueprint $t) {
                $t->increments('soc_id');
                $t->string('codigo', 20);
                $t->unsignedInteger('clicod')->index();
                $t->unsignedInteger('cat_soc_id')->nullable();
                $t->date('fecha_ingreso')->nullable();
                $t->date('fecha_nac')->nullable();
                $t->string('estado', 12)->default('ACTIVO');               // ACTIVO | SUSPENDIDO | RETIRADO | FALLECIDO
                $t->tinyInteger('suspendido_auto')->default(0);            // lo suspendió el sistema por deuda (vuelve solo al pagar)
                $t->string('token', 40)->unique();                         // QR del carnet
                $t->string('obs', 255)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('creado')->nullable()->useCurrent();
                $t->unique(['id_empresa_negocio', 'codigo']);
            });
        }

        if (!Schema::hasTable('socio_familiares')) {
            Schema::create('socio_familiares', function (Blueprint $t) {
                $t->increments('fam_id');
                $t->unsignedInteger('soc_id')->index();
                $t->string('nombre', 150);
                $t->string('dni', 15)->nullable();
                $t->string('parentesco', 30);
                $t->date('fecha_nac')->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->string('token', 40)->unique();
            });
        }

        if (!Schema::hasTable('socio_cargos')) {
            Schema::create('socio_cargos', function (Blueprint $t) {
                $t->increments('car_id');
                $t->unsignedInteger('soc_id');
                $t->unsignedInteger('IdProducto');                         // concepto: sus cuentas contables van al comprobante
                $t->string('descripcion', 150);
                $t->string('periodo', 6)->nullable();                      // 202609 para cuotas del mes
                $t->decimal('monto', 10, 2);
                $t->decimal('pagado', 10, 2)->default(0);
                $t->string('estado', 10)->default('PENDIENTE');            // PENDIENTE | PAGADO | ANULADO
                $t->dateTime('creado');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->index(['soc_id', 'estado']);
                $t->unique(['soc_id', 'IdProducto', 'periodo']);           // la cuota de un mes no se genera dos veces
            });
        }

        if (!Schema::hasTable('socio_pagos')) {
            Schema::create('socio_pagos', function (Blueprint $t) {
                $t->increments('pag_id');
                $t->unsignedInteger('car_id')->index();
                $t->unsignedInteger('IdCpe_cabecera')->index();
                $t->decimal('monto', 10, 2);
                $t->dateTime('fecha');
                $t->tinyInteger('anulado')->default(0);
            });
        }

        // Menú: se activa por usuario en Usuarios (solo lo necesitan clubes y asociaciones)
        if (!DB::table('modulos')->where('mod_url', '/socios')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Socios', 'mod_url' => '/socios', 'mod_gen' => 'Principal']);
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/socios')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        foreach (['socio_pagos', 'socio_cargos', 'socio_familiares', 'socios', 'socio_categorias', 'socio_config'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
