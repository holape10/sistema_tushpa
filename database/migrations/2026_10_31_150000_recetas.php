<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recetas de los platos (productos Preparados): qué insumos lleva un plato y cuánto de cada uno.
 * Con ellas se calcula el costo del plato y su food cost, y al vender se descuentan los insumos del almacén.
 * La cantidad se guarda como la escribió el usuario (220 g) con su unidad; el sistema la convierte a la unidad del insumo (0.220 kg).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_receta')) {
            Schema::create('producto_receta', function (Blueprint $t) {
                $t->increments('id_receta');
                $t->unsignedBigInteger('IdProducto')->index();
                $t->unsignedBigInteger('IdInsumo')->index();
                $t->decimal('cantidad', 14, 4);
                $t->string('umecod', 3);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamps();
            });
        }
        if (! DB::table('modulos')->where('mod_url', '/recetas')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Recetas y Food Cost', 'mod_url' => '/recetas', 'mod_gen' => 'Restaurante']);
        }

        // Los administradores de restaurantes (los que tienen Mesas) reciben el módulo
        $mesas = DB::table('modulos')->where('mod_url', '/mesas')->value('mod_id');
        $recetas = DB::table('modulos')->where('mod_url', '/recetas')->value('mod_id');
        $admins = $mesas ? DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $mesas)->where('r.role_id', 2)->distinct()->pluck('mu.user_IdUsuario') : collect();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $recetas)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $recetas, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_receta');
        DB::table('modulos')->where('mod_url', '/recetas')->delete();
    }
};
