<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Descuentos y cortesías en el cobro del restaurante (cobrar mesa y PV Restaurante).
 * - empresa_negocios: se activan por sucursal; el cajero descuenta hasta descuento_max_cajero % sin clave del administrador.
 * - cpe_cabecera: descuento a la cuenta (en soles o %), quién lo autorizó y el valor de las operaciones gratuitas (cortesías).
 * - cpe_detalle: precio de lista y parte del descuento de cada línea; las cortesías van como operación gratuita ante SUNAT.
 * - Menú "Descuentos y Cortesías" (reporte) para los administradores que usan Comandas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'permite_descuentos')) {
                $t->boolean('permite_descuentos')->default(false);
                $t->boolean('permite_cortesias')->default(false);
                $t->decimal('descuento_max_cajero', 5, 2)->default(10);
            }
        });
        Schema::table('cpe_cabecera', function (Blueprint $t) {
            if (! Schema::hasColumn('cpe_cabecera', 'descuento')) {
                $t->decimal('descuento', 12, 2)->default(0);
                $t->char('descuento_tipo', 1)->nullable();
                $t->decimal('descuento_valor', 12, 2)->nullable();
                $t->string('descuento_motivo', 80)->nullable();
                $t->unsignedInteger('IdUsuario_descuento')->nullable();
                $t->decimal('ccagra', 12, 2)->default(0);
                $t->decimal('ccaigvgra', 12, 2)->default(0);
            }
        });
        Schema::table('cpe_detalle', function (Blueprint $t) {
            if (! Schema::hasColumn('cpe_detalle', 'precio_lista')) {
                $t->decimal('precio_lista', 10, 2)->nullable();
                $t->decimal('descuento', 12, 2)->default(0);
                $t->boolean('cortesia')->default(false);
                $t->string('cortesia_motivo', 80)->nullable();
            }
        });

        if (! Schema::hasTable('modulos')) {
            return;
        }
        $id = DB::table('modulos')->where('mod_url', '/reportes/descuentos-cortesias')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/promotores')->value('mod_gen')
                ?? DB::table('modulos')->where('mod_url', '/comandas')->value('mod_gen') ?? 'Principal';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Descuentos y Cortesías', 'mod_url' => '/reportes/descuentos-cortesias', 'mod_gen' => $grupo]);
        }
        $admins = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('m.mod_url', '/comandas')
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')->where('r.role_id', 2)->pluck('mu.user_IdUsuario')->unique();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/reportes/descuentos-cortesias')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
        Schema::table('cpe_detalle', fn (Blueprint $t) => $t->dropColumn(['precio_lista', 'descuento', 'cortesia', 'cortesia_motivo']));
        Schema::table('cpe_cabecera', fn (Blueprint $t) => $t->dropColumn(['descuento', 'descuento_tipo', 'descuento_valor', 'descuento_motivo', 'IdUsuario_descuento', 'ccagra', 'ccaigvgra']));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['permite_descuentos', 'permite_cortesias', 'descuento_max_cajero']));
    }
};
