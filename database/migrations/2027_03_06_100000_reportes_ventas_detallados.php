<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú: dos reportes nuevos de ventas, «Ventas Detallado» (cada comprobante con sus productos) y «Ventas por Producto
 * Detallado» (cada venta de cada producto). Se dan a los administradores que ya tienen el reporte de ventas.
 */
return new class extends Migration
{
    private const NUEVOS = [
        ['Reporte: Ventas Detallado', '/reportes/ventas-detallado'],
        ['Reporte: Ventas por Producto Detallado', '/reportes/ventas-producto-detalle'],
    ];

    public function up(): void
    {
        foreach (self::NUEVOS as [$nombre, $url]) {
            if (! DB::table('modulos')->where('mod_url', $url)->exists()) {
                DB::table('modulos')->insert(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => 'Ventas']);
            }
        }
        $referencia = DB::table('modulos')->where('mod_url', '/reportes/ventas')->value('mod_id');
        $nuevos = DB::table('modulos')->whereIn('mod_url', array_column(self::NUEVOS, 1))->pluck('mod_id');
        if (! $referencia || $nuevos->isEmpty()) {
            return;
        }
        $admins = DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $referencia)->where('r.role_id', 2)->distinct()->pluck('mu.user_IdUsuario');
        foreach ($admins as $usuario) {
            foreach ($nuevos as $modulo) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $modulo)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $modulo, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', array_column(self::NUEVOS, 1))->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
    }
};
