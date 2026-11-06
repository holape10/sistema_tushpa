<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría: quién hizo qué cambio sensible, cuándo, desde dónde y qué valor había antes (precios, compras,
 * anulaciones, usuarios y permisos, configuración, claves, inicios de sesión, respaldos descargados…).
 * Solo se agregan filas: el sistema no tiene cómo editarlas ni borrarlas, y el reinicio de datos no la toca.
 * La opción del menú va en Mantenimiento y se les da a los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bitacora')) {
            Schema::create('bitacora', function (Blueprint $t) {
                $t->id();
                $t->dateTime('fecha');
                $t->unsignedInteger('IdUsuario')->nullable();
                // Copia del nombre y el rol de ese momento: si luego se cambia o se borra el usuario, la bitácora no cambia
                $t->string('usuario', 120)->nullable();
                $t->string('rol', 60)->nullable();
                $t->unsignedInteger('id_empresa_negocio')->nullable();
                $t->string('IdEmpresa', 11)->nullable();
                $t->string('modulo', 30);
                $t->string('accion', 60);
                $t->string('descripcion', 255);
                $t->string('referencia_tipo', 40)->nullable();
                $t->string('referencia_id', 40)->nullable();
                // {campo: {etiqueta, antes, despues}} y datos extra; en texto para que funcione igual en MySQL y MariaDB
                $t->mediumText('cambios')->nullable();
                $t->mediumText('datos')->nullable();
                $t->boolean('alerta')->default(false);
                $t->string('ip', 45)->nullable();
                $t->string('agente', 255)->nullable();
                $t->string('ruta', 120)->nullable();

                $t->index('fecha');
                $t->index(['IdUsuario', 'fecha']);
                $t->index(['modulo', 'fecha']);
                $t->index(['id_empresa_negocio', 'fecha']);
                $t->index(['referencia_tipo', 'referencia_id']);
            });
        }

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/bitacora')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/respaldo')->value('mod_gen') ?? 'Mantenimiento';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Bitácora de cambios', 'mod_url' => '/bitacora', 'mod_gen' => $grupo]);
            if (Schema::hasTable('role_user') && Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/bitacora')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        Schema::dropIfExists('bitacora');
    }
};
