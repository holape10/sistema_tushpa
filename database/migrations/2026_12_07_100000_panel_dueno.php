<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Panel del dueño: meta de ventas del mes, gastos fijos (para el punto de equilibrio) y el WhatsApp del dueño
 * (para el resumen del día). El menú se da a todos los administradores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios')) {
            Schema::table('empresa_negocios', function (Blueprint $t) {
                if (! Schema::hasColumn('empresa_negocios', 'meta_mensual')) {
                    $t->decimal('meta_mensual', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('empresa_negocios', 'gastos_fijos_mes')) {
                    $t->decimal('gastos_fijos_mes', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('empresa_negocios', 'whatsapp_dueno')) {
                    $t->string('whatsapp_dueno', 20)->nullable();
                }
            });
        }

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $mod = DB::table('modulos')->where('mod_url', '/panel-dueno')->value('mod_id')
            ?? DB::table('modulos')->insertGetId(['mod_nom' => 'Panel del dueño', 'mod_url' => '/panel-dueno', 'mod_gen' => 'Principal']);
        foreach (DB::table('role_user')->where('role_id', 2)->distinct()->pluck('user_IdUsuario') as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $mod)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $mod, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $mod = DB::table('modulos')->where('mod_url', '/panel-dueno')->value('mod_id');
        if ($mod) {
            DB::table('modulos_usuario')->where('mod_id', $mod)->delete();
            DB::table('modulos')->where('mod_id', $mod)->delete();
        }
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['meta_mensual', 'gastos_fijos_mes', 'whatsapp_dueno']));
    }
};
