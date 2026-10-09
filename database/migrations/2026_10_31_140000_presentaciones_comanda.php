<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comandas con presentación (TAJADA / ENTERA, VASO / JARRA): cada línea del pedido recuerda la presentación elegida.
 * Además, los administradores de negocios con Mesas reciben los módulos nuevos de restaurante (Motorizados y Carta Digital QR),
 * porque en Usuarios solo se pueden repartir los módulos que el administrador ya tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pedidos_detalle', 'id_presentacion')) {
            Schema::table('pedidos_detalle', fn (Blueprint $t) => $t->unsignedInteger('id_presentacion')->nullable()->after('IdProducto'));
        }

        $mesas = DB::table('modulos')->where('mod_url', '/mesas')->value('mod_id');
        $nuevos = DB::table('modulos')->whereIn('mod_url', ['/motorizados', '/carta/configuracion'])->pluck('mod_id');
        if (! $mesas || $nuevos->isEmpty()) {
            return;
        }
        $admins = DB::table('modulos_usuario as mu')
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $mesas)->where('r.role_id', 2)
            ->distinct()->pluck('mu.user_IdUsuario');
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
        Schema::table('pedidos_detalle', fn (Blueprint $t) => $t->dropColumn('id_presentacion'));
    }
};
