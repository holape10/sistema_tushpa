<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Todos los usuarios tienen el menú "Inicio" (la bienvenida del sistema).
 * - empresa_negocios.comandas_solo_mesas: mozos y promotores solo atienden mesas (sin Para llevar, Delivery ni Punto de
 *   venta). Activo para el RUC 20616065034. Sin la opción, mozos y promotores tienen Para llevar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'comandas_solo_mesas')) {
                $t->boolean('comandas_solo_mesas')->default(false);
            }
        });
        DB::table('empresa_negocios')->where('IdEmpresa', '20616065034')->update(['comandas_solo_mesas' => 1]);

        $inicio = DB::table('modulos')->where('mod_url', '/inicio')->value('mod_id');
        if ($inicio) {
            $sinInicio = DB::table('users')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('modulos_usuario')
                ->whereColumn('modulos_usuario.user_IdUsuario', 'users.IdUsuario')->where('modulos_usuario.mod_id', $inicio))->pluck('IdUsuario');
            foreach ($sinInicio->chunk(500) as $grupo) {
                DB::table('modulos_usuario')->insert($grupo->map(fn ($u) => ['user_IdUsuario' => $u, 'mod_id' => $inicio, 'created_at' => now(), 'updated_at' => now()])->all());
            }
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('comandas_solo_mesas'));
    }
};
