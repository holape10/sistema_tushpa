<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Línea de tiempo: el administrador ve, por día y por usuario, qué hizo cada uno (caja, cobros, comandas, anulaciones,
 * descuentos, mermas, compras y gastos). Se guarda quién envió cada comanda (antes solo el nombre del mozo de la mesa).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cocina_tickets') && ! Schema::hasColumn('cocina_tickets', 'IdUsuario')) {
            Schema::table('cocina_tickets', fn (Blueprint $t) => $t->unsignedInteger('IdUsuario')->nullable());
        }
        if (! Schema::hasTable('modulos')) {
            return;
        }
        $mod = DB::table('modulos')->where('mod_url', '/actividad')->value('mod_id')
            ?? DB::table('modulos')->insertGetId(['mod_nom' => 'Línea de tiempo', 'mod_url' => '/actividad', 'mod_gen' => 'Principal']);
        // Todos los administradores la reciben
        foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/actividad')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
        DB::table('modulos')->where('mod_id', $mod)->delete();
        Schema::table('cocina_tickets', fn (Blueprint $t) => $t->dropColumn('IdUsuario'));
    }
};
