<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú "Ventas de Promotores": administradores y caja que usan Comandas, y todos los usuarios con rol Promotor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/promotores')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Ventas de Promotores', 'mod_url' => '/promotores', 'mod_gen' => $grupo]);
        }
        $cajaYAdmin = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('m.mod_url', '/comandas')
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->whereIn('r.role_id', [2, 4])->pluck('mu.user_IdUsuario');
        $promotores = DB::table('role_user')->where('role_id', 13)->pluck('user_IdUsuario');
        foreach ($cajaYAdmin->merge($promotores)->unique() as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/promotores')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
    }
};
