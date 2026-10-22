<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Colegios: alumnos con su padre, madre y apoderado; grados y secciones con su pensión, matrícula e inscripción;
 * grupo familiar (hermanos o referidos) con descuento al 2° y 3° alumno; matrícula que genera toda la deuda del año;
 * cobro de uno o varios meses (con descuento por adelantado) con boleta o factura a nombre de quien paga;
 * pensión gratis por sorteo a los padres puntuales; y el menú "Colegio".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('col_config')) {
            Schema::create('col_config', function (Blueprint $t) {
                $t->unsignedBigInteger('id_empresa_negocio')->primary();
                $t->unsignedSmallInteger('anio');
                $t->unsignedTinyInteger('mes_inicio')->default(3);
                $t->unsignedTinyInteger('mes_fin')->default(12);
                $t->unsignedTinyInteger('dia_vence')->default(10);
                $t->decimal('dscto_2', 5, 2)->default(10);              // % de pensión para el 2° alumno del grupo familiar
                $t->decimal('dscto_3', 5, 2)->default(15);              // % para el 3° y siguientes
                $t->unsignedTinyInteger('adelanto_meses')->default(3);  // pagando N pensiones juntas...
                $t->decimal('adelanto_pct', 5, 2)->default(5);         // ...se descuenta este %
                $t->unsignedInteger('IdProducto_pension')->nullable();
                $t->unsignedInteger('IdProducto_matricula')->nullable();
                $t->unsignedInteger('IdProducto_inscripcion')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('col_grados')) {
            Schema::create('col_grados', function (Blueprint $t) {
                $t->increments('grado_id');
                $t->string('nivel', 20);            // INICIAL | PRIMARIA | SECUNDARIA
                $t->string('grado', 40);            // 3 AÑOS, PRIMERO, SEGUNDO...
                $t->string('seccion', 20)->default('ÚNICA');
                $t->decimal('pension', 10, 2)->default(0);
                $t->decimal('matricula', 10, 2)->default(0);
                $t->decimal('inscripcion', 10, 2)->default(0);
                $t->unsignedSmallInteger('orden')->default(0);
                $t->boolean('activo')->default(true);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }
        if (! Schema::hasTable('col_grupos')) {
            Schema::create('col_grupos', function (Blueprint $t) {
                $t->increments('grupo_id');
                $t->string('nombre', 150);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }
        if (! Schema::hasTable('col_alumnos')) {
            Schema::create('col_alumnos', function (Blueprint $t) {
                $t->increments('alu_id');
                $t->string('codigo', 20);
                $t->string('dni', 15)->nullable();
                $t->string('nombres', 150);
                $t->date('fecha_nac')->nullable();
                $t->char('sexo', 1)->nullable();
                $t->unsignedInteger('grupo_id')->nullable()->index();
                $t->string('estado', 12)->default('ACTIVO');   // ACTIVO | RETIRADO
                $t->string('observacion', 255)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
            });
        }
        if (! Schema::hasTable('col_responsables')) {
            Schema::create('col_responsables', function (Blueprint $t) {
                $t->increments('res_id');
                $t->unsignedInteger('alu_id')->index();
                $t->string('tipo', 12);                  // PADRE | MADRE | APODERADO
                $t->unsignedInteger('clicod');           // datos del cliente (DNI o RUC, nombre, celular, dirección, correo)
                $t->boolean('paga')->default(false);     // quien paga normalmente
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }
        if (! Schema::hasTable('col_matriculas')) {
            Schema::create('col_matriculas', function (Blueprint $t) {
                $t->increments('mat_id');
                $t->unsignedInteger('alu_id');
                $t->unsignedSmallInteger('anio');
                $t->unsignedInteger('grado_id');
                $t->boolean('nuevo')->default(false);            // primera vez en el colegio: paga inscripción
                $t->unsignedTinyInteger('orden_grupo')->default(1); // 1°, 2°, 3° alumno de su grupo familiar
                $t->decimal('dscto_pct', 5, 2)->default(0);      // descuento en la pensión
                $t->string('estado', 12)->default('ACTIVA');     // ACTIVA | RETIRADA
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->dateTime('creado')->nullable();
                $t->unique(['alu_id', 'anio']);
            });
        }
        if (! Schema::hasTable('col_cargos')) {
            Schema::create('col_cargos', function (Blueprint $t) {
                $t->increments('car_id');
                $t->unsignedInteger('alu_id')->index();
                $t->unsignedInteger('mat_id')->nullable()->index();
                $t->string('concepto', 12);               // INSCRIPCION | MATRICULA | PENSION | OTRO
                $t->char('periodo', 6)->nullable();       // pensión: AAAAMM
                $t->string('descripcion', 150);
                $t->decimal('monto', 10, 2);              // precio sin descuento
                $t->decimal('descuento', 10, 2)->default(0); // descuento de grupo familiar
                $t->decimal('pagado', 10, 2)->default(0);
                $t->date('vence')->nullable();
                $t->string('estado', 10)->default('PENDIENTE'); // PENDIENTE | PAGADO | BECADO | ANULADO
                $t->string('motivo', 200)->nullable();     // de la beca o la anulación
                $t->unsignedInteger('IdProducto')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->dateTime('creado')->nullable();
                $t->index(['id_empresa_negocio', 'estado', 'vence']);
            });
        }
        if (! Schema::hasTable('col_pagos')) {
            Schema::create('col_pagos', function (Blueprint $t) {
                $t->increments('pag_id');
                $t->unsignedInteger('car_id')->index();
                $t->unsignedInteger('IdCpe_cabecera')->index();
                $t->decimal('monto', 10, 2);
                $t->decimal('descuento', 10, 2)->default(0);  // por pagar varios meses juntos
                $t->string('pagador', 12)->nullable();        // PADRE | MADRE | APODERADO | ALUMNO | OTRO
                $t->dateTime('fecha');
                $t->boolean('anulado')->default(false);
            });
        }
        if (! Schema::hasTable('col_sorteos')) {
            Schema::create('col_sorteos', function (Blueprint $t) {
                $t->increments('sor_id');
                $t->unsignedInteger('alu_id');
                $t->unsignedInteger('car_id');
                $t->char('periodo', 6);
                $t->unsignedInteger('candidatos');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('fecha');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $nuevos = [];
        foreach ([['Alumnos y pensiones', '/colegio'], ['Reportes del colegio', '/colegio/reportes'], ['Sorteo de pensión', '/colegio/sorteo'],
            ['Configuración del colegio', '/colegio/configuracion']] as [$nom, $url]) {
            $nuevos[] = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Colegio']);
        }
        // Administradores con el menú de varios rubros (la demo y "Todo el menú") reciben el nuevo menú
        $amplio = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/clinica', '/hotel', '/estacionamiento', '/socios', '/pv-grifo', '/pv-farmacia', '/prestamos'])->pluck('mod_id');
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
        $mods = DB::table('modulos')->where('mod_gen', 'Colegio')->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['col_sorteos', 'col_pagos', 'col_cargos', 'col_matriculas', 'col_responsables', 'col_alumnos', 'col_grupos', 'col_grados', 'col_config'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
