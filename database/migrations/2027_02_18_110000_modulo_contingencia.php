<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Opción del menú "Comprobantes de contingencia" (los hechos en papel y pasados al sistema), junto al envío a SUNAT */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/contingencia')->exists()) {
            $envios = DB::table('modulos')->where('mod_nom', 'Envío de Comprobantes')->first(['mod_id', 'mod_gen']);
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Comprobantes de contingencia', 'mod_url' => '/contingencia', 'mod_gen' => $envios->mod_gen ?? 'SUNAT']);
            // Lo reciben quienes ya envían comprobantes a SUNAT
            if ($envios && Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('modulos_usuario')->where('mod_id', $envios->mod_id)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/contingencia')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
    }
};
