<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery con seguimiento en vivo:
 *  - pedidos: referencia y ubicación de entrega, estado del reparto (PREPARANDO, LISTO, EN_LOCAL, EN_CAMINO, ENTREGADO),
 *    hora de cada paso y el código del enlace de seguimiento que se envía al cliente
 *  - motorizados: su enlace personal (token) y su última ubicación
 *  - motorizado_posiciones: el recorrido (una posición cada ~10 s mientras reparte)
 *  - empresa_negocios: ubicación del local (para el mapa)
 * Y la opción del menú "Delivery en vivo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $columnas = [
                'entrega_ref' => fn () => $t->string('entrega_ref', 255)->nullable(),
                'entrega_lat' => fn () => $t->decimal('entrega_lat', 10, 7)->nullable(),
                'entrega_lng' => fn () => $t->decimal('entrega_lng', 10, 7)->nullable(),
                'entrega_estado' => fn () => $t->string('entrega_estado', 15)->nullable()->index(),
                'entrega_token' => fn () => $t->string('entrega_token', 12)->nullable()->unique(),
                'entrega_listo' => fn () => $t->dateTime('entrega_listo')->nullable(),
                'entrega_local' => fn () => $t->dateTime('entrega_local')->nullable(),
                'entrega_salida' => fn () => $t->dateTime('entrega_salida')->nullable(),
                'entrega_fin' => fn () => $t->dateTime('entrega_fin')->nullable(),
            ];
            foreach ($columnas as $nombre => $crear) {
                if (! Schema::hasColumn('pedidos', $nombre)) {
                    $crear();
                }
            }
        });

        Schema::table('motorizados', function (Blueprint $t) {
            if (! Schema::hasColumn('motorizados', 'token')) {
                $t->string('token', 40)->nullable()->unique();
            }
            if (! Schema::hasColumn('motorizados', 'lat')) {
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                $t->dateTime('posicion_at')->nullable();
            }
        });

        if (! Schema::hasTable('motorizado_posiciones')) {
            Schema::create('motorizado_posiciones', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('mot_id');
                $t->decimal('lat', 10, 7);
                $t->decimal('lng', 10, 7);
                $t->unsignedSmallInteger('precision')->nullable();
                $t->dateTime('created_at');
                $t->index(['mot_id', 'created_at']);
            });
        }

        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'local_lat')) {
                $t->decimal('local_lat', 10, 7)->nullable();
                $t->decimal('local_lng', 10, 7)->nullable();
            }
        });

        // Menú "Delivery en vivo": para quien ya ve Motorizados o Comandas (administradores y caja)
        $id = DB::table('modulos')->where('mod_url', '/delivery')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/motorizados')->value('mod_gen')
                ?? DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Restaurante';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Delivery en vivo', 'mod_url' => '/delivery', 'mod_gen' => $grupo]);
        }
        $usuarios = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')
            ->whereIn('m.mod_url', ['/motorizados', '/comandas'])
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->whereIn('r.role_id', [2, 4])
            ->pluck('mu.user_IdUsuario')->unique();
        foreach ($usuarios as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/delivery')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
        Schema::dropIfExists('motorizado_posiciones');
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn(['entrega_ref', 'entrega_lat', 'entrega_lng', 'entrega_estado', 'entrega_token',
            'entrega_listo', 'entrega_local', 'entrega_salida', 'entrega_fin']));
        Schema::table('motorizados', fn (Blueprint $t) => $t->dropColumn(['token', 'lat', 'lng', 'posicion_at']));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['local_lat', 'local_lng']));
    }
};
