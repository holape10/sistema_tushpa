<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad de equipos y guía inicial:
 *  - dispositivos: cada equipo (PC, celular, tablet) desde el que entra cada usuario. Un equipo nuevo se avisa al dueño
 *    ("¿fuiste tú?"); desde uno de confianza se puede confirmar, bloquear ("no fui yo") o cerrarle la sesión.
 *  - dispositivo_sesiones: qué sesión abierta es de qué equipo (para cerrarla).
 *  - Guía de primeros pasos: empresa.guia_oculta (el administrador la cerró) y users.bienvenida_vista (el usuario nuevo
 *    ya vio su bienvenida).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispositivos')) {
            Schema::create('dispositivos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('IdUsuario')->index();
                $t->string('token', 64);
                $t->string('nombre', 100)->nullable();
                $t->string('equipo_pc', 60)->nullable();
                $t->string('navegador', 40)->nullable();
                $t->string('sistema', 40)->nullable();
                $t->string('tipo', 10)->default('PC');
                $t->string('ip', 45)->nullable();
                $t->string('ciudad', 120)->nullable();
                $t->dateTime('primera_vez');
                $t->dateTime('ultima_vez');
                $t->boolean('confiable')->default(false);
                $t->boolean('por_revisar')->default(false);
                $t->dateTime('bloqueado_el')->nullable();
                $t->string('revisado_por', 120)->nullable();
                $t->unique(['IdUsuario', 'token']);
            });
        }
        if (! Schema::hasTable('dispositivo_sesiones')) {
            Schema::create('dispositivo_sesiones', function (Blueprint $t) {
                $t->string('session_id')->primary();
                $t->unsignedBigInteger('dispositivo_id')->index();
                $t->unsignedInteger('IdUsuario')->index();
                $t->dateTime('created_at');
            });
        }
        if (Schema::hasTable('empresa') && ! Schema::hasColumn('empresa', 'guia_oculta')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->boolean('guia_oculta')->default(false));
        }
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'bienvenida_vista')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('bienvenida_vista')->default(false));
            // Los que ya usaban el sistema no la ven: es solo para los usuarios nuevos
            DB::table('users')->update(['bienvenida_vista' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivo_sesiones');
        Schema::dropIfExists('dispositivos');
        if (Schema::hasColumn('empresa', 'guia_oculta')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn('guia_oculta'));
        }
        if (Schema::hasColumn('users', 'bienvenida_vista')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('bienvenida_vista'));
        }
    }
};
