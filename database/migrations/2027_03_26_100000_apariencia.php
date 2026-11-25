<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Mantenimiento › Apariencia: el color, el fondo y el tamaño de letra que el administrador elige para su empresa */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa') && ! Schema::hasColumn('empresa', 'apariencia')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->json('apariencia')->nullable());
        }
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', '/apariencia')->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Apariencia', 'mod_url' => '/apariencia', 'mod_gen' => 'Mantenimiento']);
        // Para los administradores
        foreach (DB::table('role_user')->where('role_id', 2)->distinct()->pluck('user_IdUsuario') as $usuario) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/apariencia')->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        if (Schema::hasColumn('empresa', 'apariencia')) {
            Schema::table('empresa', fn (Blueprint $t) => $t->dropColumn('apariencia'));
        }
    }
};
