<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de caja proyectado: lo que se va a cobrar y pagar en las próximas semanas. Solo guarda con cuánto dinero
 * cuenta hoy la sucursal (caja + bancos, lo escribe el dueño); lo demás sale de las cuentas por cobrar y por pagar.
 * La opción del menú va junto a las cuentas por cobrar y se les da a los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flujo_caja_saldos')) {
            Schema::create('flujo_caja_saldos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio')->unique();
                $t->decimal('saldo', 14, 2)->default(0);
                $t->date('fecha');
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/flujo-caja')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/cuentas/cobrar')->value('mod_gen') ?? 'Cuentas';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Flujo de caja proyectado', 'mod_url' => '/flujo-caja', 'mod_gen' => $grupo]);
            if (Schema::hasTable('role_user') && Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/flujo-caja')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        Schema::dropIfExists('flujo_caja_saldos');
    }
};
