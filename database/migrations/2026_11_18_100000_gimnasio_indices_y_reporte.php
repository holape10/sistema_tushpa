<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gimnasio:
 * - Índices: buscar por huella (lector), los ingresos de cada cliente por fecha y las ventas de planes por fecha (reporte).
 * - Menú "Reporte Gimnasio" para quienes ya usan el gimnasio y son administradores.
 */
return new class extends Migration
{
    private function tieneIndice(string $tabla, string $nombre): bool
    {
        return collect(DB::select("SHOW INDEX FROM `{$tabla}`"))->contains('Key_name', $nombre);
    }

    public function up(): void
    {
        if (Schema::hasTable('socios') && Schema::hasColumn('socios', 'huella') && ! $this->tieneIndice('socios', 'socios_suc_huella_index')) {
            Schema::table('socios', fn (Blueprint $t) => $t->index(['id_empresa_negocio', 'huella'], 'socios_suc_huella_index'));
        }
        if (Schema::hasTable('gym_asistencias') && ! $this->tieneIndice('gym_asistencias', 'gym_asistencias_soc_fecha_index')) {
            Schema::table('gym_asistencias', fn (Blueprint $t) => $t->index(['soc_id', 'fecha_hora'], 'gym_asistencias_soc_fecha_index'));
        }
        if (Schema::hasTable('gym_membresias') && ! $this->tieneIndice('gym_membresias', 'gym_membresias_suc_creado_index')) {
            Schema::table('gym_membresias', fn (Blueprint $t) => $t->index(['id_empresa_negocio', 'creado'], 'gym_membresias_suc_creado_index'));
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $id = DB::table('modulos')->where('mod_url', '/gimnasio/reporte')->value('mod_id')
            ?? DB::table('modulos')->insertGetId(['mod_nom' => 'Reporte Gimnasio', 'mod_url' => '/gimnasio/reporte', 'mod_gen' => 'Gimnasio']);
        $gimnasio = DB::table('modulos')->where('mod_url', '/gimnasio')->value('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario');
        $usuarios = $gimnasio ? DB::table('modulos_usuario')->where('mod_id', $gimnasio)->whereIn('user_IdUsuario', $admins)->pluck('user_IdUsuario')->unique() : collect();
        foreach ($usuarios as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/gimnasio/reporte')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
        foreach ([['socios', 'socios_suc_huella_index'], ['gym_asistencias', 'gym_asistencias_soc_fecha_index'], ['gym_membresias', 'gym_membresias_suc_creado_index']] as [$tabla, $indice]) {
            if ($this->tieneIndice($tabla, $indice)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($indice));
            }
        }
    }
};
