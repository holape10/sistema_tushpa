<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::table('modulos')->where('mod_nom', 'Impresoras')->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Impresoras', 'mod_url' => '/impresoras', 'mod_gen' => 'Mantenimiento']);

        // Visible para los administradores
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        foreach ($admins as $u) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $u, 'mod_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_nom', 'Impresoras')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
    }
};
