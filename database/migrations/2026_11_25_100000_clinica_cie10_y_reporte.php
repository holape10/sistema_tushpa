<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clínica: varios diagnósticos CIE-10 por atención con su tipo ("K02.1 (D), K04.0 (P)") y el menú "Reporte Clínica"
 * para quienes ya usan la clínica y son administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('atencion_clinica') && Schema::hasColumn('atencion_clinica', 'cie10')) {
            Schema::table('atencion_clinica', fn (Blueprint $t) => $t->string('cie10', 500)->nullable()->change());
        }
        if (! Schema::hasTable('modulos')) {
            return;
        }
        $id = DB::table('modulos')->where('mod_url', '/clinica/reportes')->value('mod_id')
            ?? DB::table('modulos')->insertGetId(['mod_nom' => 'Reporte Clínica', 'mod_url' => '/clinica/reportes', 'mod_gen' => 'Clínica']);
        $clinica = DB::table('modulos')->where('mod_url', '/clinica')->value('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = $clinica ? DB::table('modulos_usuario')->where('mod_id', $clinica)->whereIn('user_IdUsuario', $admins)->pluck('user_IdUsuario')->unique() : collect();
        foreach ($usuarios as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/clinica/reportes')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
    }
};
