<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gimnasio. El cliente del gimnasio es un socio (tabla socios): así reutiliza el carnet con QR y el portal ({subdominio}/socio)
 * con su DNI y contraseña propia.
 *  - socios: foto, código de huella (el número con que quedó registrado en el lector) y entrenador asignado.
 *  - gym_planes: planes que se venden (MENSUAL 30 días, RUTINA DEL DÍA 1 día...). Cada plan es un producto para el comprobante.
 *  - gym_membresias: lo que compró el cliente. fin = inicio + días del plan - 1 + días congelados.
 *  - gym_congelamientos: del cliente (desde su portal, no lo puede deshacer) o del administrador. Corre solo al terminar.
 *  - gym_asistencias: cada intento de ingreso (permitido o denegado, con el motivo).
 *  - gym_nutricion: planes de nutrición y medidas que registra el entrenador; el cliente los ve en su portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('socios', 'foto')) {
            Schema::table('socios', function (Blueprint $t) {
                $t->string('foto')->nullable();
                $t->string('huella', 30)->nullable();
                $t->unsignedInteger('entrenador_id')->nullable();
            });
        }

        if (! Schema::hasTable('gym_planes')) {
            Schema::create('gym_planes', function (Blueprint $t) {
                $t->increments('plan_id');
                $t->string('nombre', 80);
                $t->unsignedSmallInteger('dias');
                $t->decimal('precio', 10, 2);
                $t->unsignedSmallInteger('congelar_max')->default(0);   // días que el cliente puede congelar por membresía (0 = no puede)
                $t->unsignedInteger('IdProducto')->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (! Schema::hasTable('gym_membresias')) {
            Schema::create('gym_membresias', function (Blueprint $t) {
                $t->increments('mem_id');
                $t->unsignedInteger('soc_id')->index();
                $t->unsignedInteger('plan_id')->nullable();
                $t->string('plan', 80);
                $t->unsignedSmallInteger('dias');
                $t->unsignedSmallInteger('congelar_max')->default(0);
                $t->date('inicio');
                $t->date('fin');
                $t->decimal('precio', 10, 2)->default(0);
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->string('estado', 10)->default('ACTIVA');               // ACTIVA | ANULADA
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('creado');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->index(['soc_id', 'estado', 'fin']);
            });
        }

        if (! Schema::hasTable('gym_congelamientos')) {
            Schema::create('gym_congelamientos', function (Blueprint $t) {
                $t->increments('con_id');
                $t->unsignedInteger('mem_id')->index();
                $t->unsignedInteger('soc_id')->index();
                $t->date('desde');
                $t->date('hasta');
                $t->unsignedSmallInteger('dias');
                $t->string('motivo', 30);
                $t->string('detalle', 200)->nullable();
                $t->string('origen', 8);                                  // CLIENTE | ADMIN
                $t->string('estado', 10)->default('ACTIVO');              // ACTIVO | ANULADO
                $t->dateTime('creado');
                $t->unsignedInteger('IdUsuario')->nullable();             // quién lo registró o lo editó por última vez (admin)
                $t->string('nota', 200)->nullable();                      // qué cambió el administrador
            });
        }

        if (! Schema::hasTable('gym_asistencias')) {
            Schema::create('gym_asistencias', function (Blueprint $t) {
                $t->increments('asi_id');
                $t->unsignedInteger('soc_id')->index();
                $t->dateTime('fecha_hora');
                $t->string('metodo', 10);                                 // DNI | QR | HUELLA | CODIGO | MANUAL
                $t->string('resultado', 10);                              // PERMITIDO | DENEGADO
                $t->string('motivo', 200)->nullable();
                $t->unsignedInteger('mem_id')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();             // quien aprobó (ingreso con autorización)
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->index(['id_empresa_negocio', 'fecha_hora']);
            });
        }

        if (! Schema::hasTable('gym_nutricion')) {
            Schema::create('gym_nutricion', function (Blueprint $t) {
                $t->increments('nut_id');
                $t->unsignedInteger('soc_id')->index();
                $t->unsignedInteger('IdUsuario');                         // entrenador
                $t->date('fecha');
                $t->string('objetivo', 40);
                $t->decimal('peso', 5, 2)->nullable();
                $t->decimal('talla', 4, 2)->nullable();                   // metros
                $t->decimal('grasa', 4, 1)->nullable();
                $t->unsignedSmallInteger('calorias')->nullable();
                $t->text('desayuno')->nullable();
                $t->text('media_manana')->nullable();
                $t->text('almuerzo')->nullable();
                $t->text('media_tarde')->nullable();
                $t->text('cena')->nullable();
                $t->text('indicaciones')->nullable();
                $t->tinyInteger('activo')->default(1);
                $t->dateTime('creado');
            });
        }

        // Rol entrenador y menú (se activan por usuario en Usuarios)
        if (! DB::table('roles')->where('id', 11)->exists()) {
            DB::table('roles')->insert(['id' => 11, 'name' => 'entrenador', 'description' => 'Entrenador']);
        }
        foreach (['/gimnasio' => 'Gimnasio', '/gimnasio/acceso' => 'Acceso Gimnasio', '/gimnasio/entrenador' => 'Entrenador'] as $url => $nom) {
            if (! DB::table('modulos')->where('mod_url', $url)->exists()) {
                DB::table('modulos')->insert(['mod_nom' => $nom, 'mod_url' => $url, 'mod_gen' => 'Gimnasio']);
            }
        }
    }

    public function down(): void
    {
        $mods = DB::table('modulos')->whereIn('mod_url', ['/gimnasio', '/gimnasio/acceso', '/gimnasio/entrenador'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $mods)->delete();
        DB::table('modulos')->whereIn('mod_id', $mods)->delete();
        foreach (['gym_nutricion', 'gym_asistencias', 'gym_congelamientos', 'gym_membresias', 'gym_planes'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('socios', fn (Blueprint $t) => $t->dropColumn(['foto', 'huella', 'entrenador_id']));
    }
};
