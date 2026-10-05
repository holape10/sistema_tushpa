<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Menú Principal: "PV" (punto de venta táctil, el PV Mall del sistema antiguo) para Administrador y Caja
return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modulos')->where('mod_url', '/pv')->exists()) {
            return;
        }
        $modId = DB::table('modulos')->insertGetId(['mod_nom' => 'PV', 'mod_url' => '/pv', 'mod_gen' => 'Principal']);
        foreach (DB::table('role_user')->whereIn('role_id', [2, 4])->pluck('user_IdUsuario')->unique() as $userId) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $userId, 'mod_id' => $modId]);
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/pv')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
    }
};
