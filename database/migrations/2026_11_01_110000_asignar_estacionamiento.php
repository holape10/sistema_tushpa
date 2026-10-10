<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migración de estacionamiento creó sus opciones del menú pero no se las dio a nadie, y el menú de cada usuario
 * solo muestra lo que tiene asignado. Aquí se asignan a los administradores del sistema principal y de los clientes
 * cuyo rubro es ESTACIONAMIENTO. A los demás clientes se les da desde el panel admin (Clientes › editar › menú).
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = DB::connection()->getDatabaseName();
        if ($base !== config('tenancy.base_sistema') && $this->rubro($base) !== 'ESTACIONAMIENTO') {
            return;
        }

        $modulos = DB::table('modulos')->whereIn('mod_url', ['/estacionamiento', '/estacionamiento/abonados', '/estacionamiento/reporte'])->pluck('mod_id');
        $admins = DB::table('role_user')->where('role_id', 2)->distinct()->pluck('user_IdUsuario');
        foreach ($admins as $usuario) {
            foreach ($modulos as $modulo) {
                if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $modulo)->exists()) {
                    DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $modulo, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    /** Rubro del cliente según la base central (null si no hay multi-empresa o no tiene rubro) */
    private function rubro(string $base): ?string
    {
        try {
            return DB::connection('central')->table('clientes')->where('base_datos', $base)->value('rubro');
        } catch (Throwable) {
            return null;
        }
    }

    public function down(): void
    {
        // Las asignaciones se quitan desde el menú de cada usuario
    }
};
