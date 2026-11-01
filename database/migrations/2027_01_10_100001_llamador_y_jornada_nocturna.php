<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Pantalla Llamador (televisor para los clientes: pedidos para llevar "Preparando" y "¡Listo!"), en el menú
 *   Restaurante para los administradores, como la pantalla de cocina.
 * - atiende_de_noche: discotecas y bares que venden pasada la medianoche. El panel del dueño muestra "Esta noche"
 *   (de mediodía a mediodía) en vez de "Hoy", y lo de madrugada cuenta en la noche que se atendió.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'atiende_de_noche')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('atiende_de_noche')->default(false));
        }
        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/llamador')->exists()) {
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Pantalla Llamador', 'mod_url' => '/llamador', 'mod_gen' => 'Restaurante']);
            // Quien ya ve la pantalla de cocina, ve también el llamador
            $cocina = DB::table('modulos')->where('mod_url', '/cocina')->value('mod_id');
            $usuarios = $cocina ? DB::table('modulos_usuario')->where('mod_id', $cocina)->pluck('user_IdUsuario') : collect();
            foreach ($usuarios as $u) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/llamador')->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        if (Schema::hasColumn('empresa_negocios', 'atiende_de_noche')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('atiende_de_noche'));
        }
    }
};
