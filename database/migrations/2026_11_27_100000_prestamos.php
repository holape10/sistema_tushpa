<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Préstamos con cobro diario ("gota a gota") o semanal/quincenal/mensual:
 *  - clientes con DNI, dirección de cobro, zona, negocio, aval y cobrador
 *  - planes (interés % y número de cuotas; ej. 20% en 24 cuotas de lunes a sábado)
 *  - préstamos con su cronograma de cuotas, pagos (con mora opcional) y gestiones de cobranza (no pagó, promesa)
 *  - rol Cobrador (entra desde el celular con su código) y el menú "Préstamos"
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('roles')->where('id', 14)->exists()) {
            DB::table('roles')->insert(['id' => 14, 'name' => 'cobrador', 'description' => 'Cobrador']);
        }

        if (! Schema::hasTable('prestamo_config')) {
            Schema::create('prestamo_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->string('dias_cobro', 20)->default('1,2,3,4,5,6');
                $t->decimal('mora_diaria', 10, 2)->default(0);
                $t->unsignedSmallInteger('dias_gracia')->default(0);
                $t->text('feriados')->nullable();
                $t->decimal('capital_inicial', 12, 2)->default(0);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('prestamo_planes')) {
            Schema::create('prestamo_planes', function (Blueprint $t) {
                $t->increments('plan_id');
                $t->string('nombre', 80);
                $t->decimal('interes', 6, 2);
                $t->unsignedSmallInteger('cuotas');
                $t->string('frecuencia', 10)->default('DIARIA');
                $t->decimal('monto_min', 12, 2)->default(0);
                $t->decimal('monto_max', 12, 2)->default(0);
                $t->boolean('primer_prestamo')->default(false);
                $t->boolean('activo')->default(true);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }
        if (! Schema::hasTable('prestamo_clientes')) {
            Schema::create('prestamo_clientes', function (Blueprint $t) {
                $t->increments('pcl_id');
                $t->unsignedInteger('clicod')->index();
                $t->string('direccion', 200)->nullable();
                $t->string('referencia', 200)->nullable();
                $t->string('zona', 60)->nullable();
                $t->string('negocio', 120)->nullable();
                $t->string('aval_nombre', 150)->nullable();
                $t->string('aval_dni', 15)->nullable();
                $t->string('aval_telefono', 20)->nullable();
                $t->unsignedInteger('cobrador_id')->nullable()->index();
                $t->decimal('limite', 12, 2)->nullable();
                $t->string('estado', 12)->default('ACTIVO');
                $t->text('obs')->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('prestamos')) {
            Schema::create('prestamos', function (Blueprint $t) {
                $t->increments('pre_id');
                $t->unsignedInteger('numero');
                $t->unsignedInteger('pcl_id')->index();
                $t->unsignedInteger('plan_id')->nullable();
                $t->decimal('monto', 12, 2);
                $t->decimal('interes_pct', 6, 2);
                $t->decimal('interes', 12, 2);
                $t->decimal('total', 12, 2);
                $t->unsignedSmallInteger('cuotas');
                $t->decimal('cuota', 12, 2);
                $t->string('frecuencia', 10)->default('DIARIA');
                $t->string('dias_cobro', 20)->default('1,2,3,4,5,6');
                $t->date('fecha');
                $t->date('primera_cuota');
                $t->date('ultima_cuota');
                $t->decimal('pagado', 12, 2)->default(0);
                $t->decimal('mora_pagada', 12, 2)->default(0);
                $t->decimal('entregado', 12, 2)->default(0);
                $t->string('medio_entrega', 20)->default('EFECTIVO');
                $t->unsignedInteger('renueva_pre_id')->nullable();
                $t->string('estado', 12)->default('VIGENTE');
                $t->dateTime('cancelado')->nullable();
                $t->unsignedInteger('cobrador_id')->nullable()->index();
                $t->string('obs', 255)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('creado')->nullable();
                $t->index(['id_empresa_negocio', 'estado']);
                $t->index(['id_empresa_negocio', 'fecha']);
            });
        }
        if (! Schema::hasTable('prestamo_cuotas')) {
            Schema::create('prestamo_cuotas', function (Blueprint $t) {
                $t->increments('cuo_id');
                $t->unsignedInteger('pre_id')->index();
                $t->unsignedSmallInteger('numero');
                $t->date('fecha');
                $t->decimal('monto', 12, 2);
                $t->index(['fecha', 'pre_id']);
            });
        }
        if (! Schema::hasTable('prestamo_pagos')) {
            Schema::create('prestamo_pagos', function (Blueprint $t) {
                $t->increments('pag_id');
                $t->unsignedInteger('pre_id')->index();
                $t->dateTime('fecha_hora');
                $t->decimal('monto', 12, 2);
                $t->decimal('mora', 12, 2)->default(0);
                $t->string('medio', 20)->default('EFECTIVO');
                $t->string('operacion', 30)->nullable();
                $t->string('tipo', 12)->default('PAGO');
                $t->string('obs', 200)->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->boolean('anulado')->default(false);
                $t->unsignedInteger('anulado_por')->nullable();
                $t->string('motivo_anulacion', 200)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->index(['id_empresa_negocio', 'fecha_hora']);
            });
        }
        if (! Schema::hasTable('prestamo_gestiones')) {
            Schema::create('prestamo_gestiones', function (Blueprint $t) {
                $t->increments('ges_id');
                $t->unsignedInteger('pre_id')->index();
                $t->dateTime('fecha_hora');
                $t->string('resultado', 15);
                $t->date('promesa')->nullable();
                $t->string('nota', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->index(['id_empresa_negocio', 'fecha_hora']);
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Préstamos', '/prestamos'], ['Clientes de Préstamos', '/prestamos/clientes'], ['Cobranza del Día', '/prestamos/cobranza'],
            ['Reportes de Préstamos', '/prestamos/reportes'], ['Configuración de Préstamos', '/prestamos/configuracion']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Préstamos']);
        }
        // Administradores con el menú de varios rubros (la empresa demo y "Todo el menú") reciben el nuevo menú
        // (tienen la opción principal de 3 o más rubros distintos)
        $amplio = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/clinica', '/hotel', '/estacionamiento', '/socios', '/pv-grifo', '/pv-farmacia'])->pluck('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = DB::table('modulos_usuario')->whereIn('user_IdUsuario', $admins)->whereIn('mod_id', $amplio)
            ->groupBy('user_IdUsuario')->havingRaw('COUNT(DISTINCT mod_id) >= 3')->pluck('user_IdUsuario');
        foreach ($usuarios as $usuario) {
            foreach ($nuevos as $mod) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->where('mod_gen', 'Préstamos')->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['prestamo_gestiones', 'prestamo_pagos', 'prestamo_cuotas', 'prestamos', 'prestamo_clientes', 'prestamo_planes', 'prestamo_config'] as $t) {
            Schema::dropIfExists($t);
        }
        DB::table('roles')->where('id', 14)->delete();
    }
};
