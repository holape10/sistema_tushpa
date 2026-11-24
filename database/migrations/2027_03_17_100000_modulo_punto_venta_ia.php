<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opción del menú "Punto Venta IA" (comercios): el mismo Punto Venta, pero el cajero arma y cobra la venta hablando.
 * Va en el mismo grupo que el Punto Venta y la reciben quienes ya usan el Punto Venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', '/punto-venta-ia')->exists()) {
            return;
        }
        $pv = DB::table('modulos')->where('mod_url', '/punto-venta')->first(['mod_id', 'mod_gen']);
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Punto Venta IA', 'mod_url' => '/punto-venta-ia', 'mod_gen' => $pv->mod_gen ?? 'Principal']);
        if ($pv && Schema::hasTable('modulos_usuario')) {
            foreach (DB::table('modulos_usuario')->where('mod_id', $pv->mod_id)->pluck('user_IdUsuario')->unique() as $usuario) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/punto-venta-ia')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
    }
};
