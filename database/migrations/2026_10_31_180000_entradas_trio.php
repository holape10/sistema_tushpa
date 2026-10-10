<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entradas y "arma tu trío", simple:
 *  - Las entradas (SOPA, TEQUEÑO, ENSALADA...) se crean en Restaurante > Entradas, con su receta y costo.
 *  - Un plato con "lleva entrada" hace que el mozo elija la entrada en la comanda.
 *  - Un plato "arma tu trío" (trio_cantidad = 3) hace que el mozo elija 3 platos de los marcados "puede ir en un trío".
 * Reemplaza a los grupos de opciones genéricos (producto_opcion_grupos / items), que eran difíciles de usar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'lleva_entrada')) {
                $t->boolean('lleva_entrada')->default(false);
                $t->boolean('en_trio')->default(false);
                $t->unsignedTinyInteger('trio_cantidad')->nullable();
            }
        });
        Schema::dropIfExists('producto_opcion_items');
        Schema::dropIfExists('producto_opcion_grupos');

        if (! DB::table('modulos')->where('mod_url', '/entradas')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Entradas', 'mod_url' => '/entradas', 'mod_gen' => 'Restaurante']);
        }
        $mesas = DB::table('modulos')->where('mod_url', '/mesas')->value('mod_id');
        $entradas = DB::table('modulos')->where('mod_url', '/entradas')->value('mod_id');
        $admins = $mesas ? DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $mesas)->where('r.role_id', 2)->distinct()->pluck('mu.user_IdUsuario') : collect();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $entradas)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $entradas, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['lleva_entrada', 'en_trio', 'trio_cantidad']));
        DB::table('modulos')->where('mod_url', '/entradas')->delete();
    }
};
