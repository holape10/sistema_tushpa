<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consulta de comprobantes en SUNAT (apiperu.dev):
 *  - "Consulta CPE Individual" deja de estar "Pronto": abre /sunat/consulta-cpe.
 *  - Compras y gastos guardan lo que respondió SUNAT al pulsar "Consultar" (estado y cuándo se consultó).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modulos')->where('mod_nom', 'Consulta CPE Individual')->whereIn('mod_url', ['#', ''])->update(['mod_url' => '/sunat/consulta-cpe']);
        if (! DB::table('modulos')->where('mod_url', '/sunat/consulta-cpe')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/sunat/envios')->value('mod_gen') ?? 'SUNAT';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Consulta CPE Individual', 'mod_url' => '/sunat/consulta-cpe', 'mod_gen' => $grupo]);
            foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('compras_cabecera') && ! Schema::hasColumn('compras_cabecera', 'sunat_consultado')) {
            Schema::table('compras_cabecera', fn (Blueprint $t) => $t->dateTime('sunat_consultado')->nullable());
        }
        if (Schema::hasTable('gastos') && ! Schema::hasColumn('gastos', 'sunat_estado')) {
            Schema::table('gastos', function (Blueprint $t) {
                $t->string('sunat_estado', 50)->nullable();
                $t->dateTime('sunat_consultado')->nullable();
            });
        }
    }

    public function down(): void
    {
        DB::table('modulos')->where('mod_url', '/sunat/consulta-cpe')->update(['mod_url' => '#']);
        if (Schema::hasColumn('compras_cabecera', 'sunat_consultado')) {
            Schema::table('compras_cabecera', fn (Blueprint $t) => $t->dropColumn('sunat_consultado'));
        }
        if (Schema::hasColumn('gastos', 'sunat_estado')) {
            Schema::table('gastos', fn (Blueprint $t) => $t->dropColumn(['sunat_estado', 'sunat_consultado']));
        }
    }
};
