<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menú nuevo:
 *  - "Inventario valorizado" (/kardex/inventario), en el mismo grupo que el Kardex: se da a quien ya ve el Kardex.
 *  - "Copia de seguridad" (/respaldo), en Mantenimiento: se da a los administradores.
 * Además, un índice para los reportes de inventario a una fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->modulo('Inventario valorizado', '/kardex/inventario', '/kardex',
            DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('m.mod_url', '/kardex')->pluck('mu.user_IdUsuario'));
        $this->modulo('Copia de seguridad', '/respaldo', '/empresas',
            DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario'));

        if (Schema::hasTable('movimientos_productos') && ! Schema::hasIndex('movimientos_productos', 'mov_prod_suc_fecha_index')) {
            Schema::table('movimientos_productos', fn (Blueprint $t) => $t->index(['id_empresa_negocio', 'fecha_mov'], 'mov_prod_suc_fecha_index'));
        }
    }

    /** Crea la opción del menú (en el grupo de $comoUrl) y se la asigna a esos usuarios */
    private function modulo(string $nombre, string $url, string $comoUrl, $usuarios): void
    {
        $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', $comoUrl)->value('mod_gen') ?? 'Mantenimiento';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $grupo]);
        }
        foreach ($usuarios->unique() as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', ['/kardex/inventario', '/respaldo'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        if (Schema::hasIndex('movimientos_productos', 'mov_prod_suc_fecha_index')) {
            Schema::table('movimientos_productos', fn (Blueprint $t) => $t->dropIndex('mov_prod_suc_fecha_index'));
        }
    }
};
