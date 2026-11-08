<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Asistente IA: la opción del menú, por ahora solo para los administradores */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', '/asistente')->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Asistente IA', 'mod_url' => '/asistente', 'mod_gen' => 'Principal']);
        if (Schema::hasTable('modulos_usuario')) {
            foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/asistente')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
    }
};
