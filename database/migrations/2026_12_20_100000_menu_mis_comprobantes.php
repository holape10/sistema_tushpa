<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú "Mis comprobantes" (en Otros, junto a Soporte): los comprobantes que TUSHPA (el sistema principal) le emitió a
 * esta empresa, con su PDF, XML y CDR. Se da a los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/mis-comprobantes')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/soporte')->value('mod_gen') ?? 'Otros';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Mis comprobantes', 'mod_url' => '/mis-comprobantes', 'mod_gen' => $grupo]);
        }
        foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/mis-comprobantes')->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
    }
};
