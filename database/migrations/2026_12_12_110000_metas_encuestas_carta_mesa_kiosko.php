<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Atención en el restaurante:
 *  - metas_mozo: meta de venta diaria, semanal y mensual de cada mozo (IdUsuario vacío = la de todos los mozos).
 *  - encuestas: el cliente califica comida, atención y rapidez escaneando el QR de su ticket (una por comprobante).
 *  - carta_solicitudes: desde el QR de su mesa el cliente pide platos, llama al mozo o pide la cuenta; el mozo lo acepta.
 *  - empresa_negocios.encuesta_activa y carta_pedido_mesa: se activan por sucursal.
 *  - Menú "Metas de mozos", "Encuestas" y "Kiosko de autoservicio" para los administradores del restaurante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'encuesta_activa')) {
                $t->boolean('encuesta_activa')->default(false);
            }
            if (! Schema::hasColumn('empresa_negocios', 'carta_pedido_mesa')) {
                $t->boolean('carta_pedido_mesa')->default(false);
            }
        });
        if (! Schema::hasTable('metas_mozo')) {
            Schema::create('metas_mozo', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio');
                $t->unsignedInteger('IdUsuario')->default(0);   // 0 = meta de todos los mozos
                $t->decimal('diaria', 10, 2)->nullable();
                $t->decimal('semanal', 10, 2)->nullable();
                $t->decimal('mensual', 12, 2)->nullable();
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'IdUsuario']);
            });
        }
        if (! Schema::hasTable('encuestas')) {
            Schema::create('encuestas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio')->index();
                $t->unsignedInteger('IdCpe_cabecera')->unique();
                $t->unsignedTinyInteger('comida');
                $t->unsignedTinyInteger('atencion');
                $t->unsignedTinyInteger('rapidez');
                $t->string('comentario', 300)->nullable();
                $t->string('telefono', 15)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }
        if (! Schema::hasTable('carta_solicitudes')) {
            Schema::create('carta_solicitudes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio');
                $t->unsignedInteger('mes_id');
                $t->string('tipo', 10);                         // PEDIDO, MOZO, CUENTA
                $t->json('items')->nullable();
                $t->decimal('total', 10, 2)->nullable();
                $t->string('nombre', 60)->nullable();
                $t->string('nota', 150)->nullable();
                $t->string('estado', 12)->default('PENDIENTE');  // PENDIENTE, ACEPTADA, RECHAZADA, ATENDIDA
                $t->string('token', 40)->unique();               // el cliente sigue su pedido con él
                $t->unsignedInteger('ped_id')->nullable();
                $t->unsignedInteger('IdUsuario_atiende')->nullable();
                $t->dateTime('atendido_en')->nullable();
                $t->timestamps();
                $t->index(['id_empresa_negocio', 'estado']);
            });
        }

        $this->menu([
            ['Metas de mozos', '/metas-mozos'],
            ['Encuestas a clientes', '/encuestas'],
            ['Kiosko de autoservicio', '/autoservicio'],
        ]);
    }

    /** Opciones del menú para los administradores que ya usan Comandas */
    private function menu(array $opciones): void
    {
        if (! Schema::hasTable('modulos')) {
            return;
        }
        $grupo = DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
        $admins = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
            ->whereIn('m.mod_url', ['/comandas', '/comandas/punto-venta'])
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->where('r.role_id', 2)->pluck('mu.user_IdUsuario')->unique();
        foreach ($opciones as [$nombre, $url]) {
            $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                ?? DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $grupo]);
            foreach ($admins as $usuario) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', ['/metas-mozos', '/encuestas', '/autoservicio'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        Schema::dropIfExists('carta_solicitudes');
        Schema::dropIfExists('encuestas');
        Schema::dropIfExists('metas_mozo');
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['encuesta_activa', 'carta_pedido_mesa']));
    }
};
