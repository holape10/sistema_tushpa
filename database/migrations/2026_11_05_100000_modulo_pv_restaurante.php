<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción del menú "PV Restaurante": el punto de venta que está dentro de Comandas (venta directa sin mesa),
 * para administradores y caja que ya usan Comandas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/comandas/punto-venta')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'PV Restaurante', 'mod_url' => '/comandas/punto-venta', 'mod_gen' => $grupo]);
        }
        $usuarios = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('m.mod_url', '/comandas')
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
        $id = DB::table('modulos')->where('mod_url', '/comandas/punto-venta')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
    }
};
