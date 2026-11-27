<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Otros › Contabilidad DBF: el tienfact.dbf de ventas armado con los comprobantes del sistema. Se agrega al menú de los
 * administradores (los demás usuarios lo reciben si el administrador se lo da).
 */
return new class extends Migration
{
    private const URL = '/contabilidad-dbf';

    public function up(): void
    {
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', self::URL)->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Contabilidad DBF', 'mod_url' => self::URL, 'mod_gen' => 'Otros']);
        foreach (DB::table('role_user')->where('role_id', 2)->distinct()->pluck('user_IdUsuario') as $usuario) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', self::URL)->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
    }
};
