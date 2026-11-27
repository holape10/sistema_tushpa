<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trabaja con pedidos: el vendedor arma el pedido (como una venta, sin imprimir) y le sale un número del día (1, 2, 3…,
 * vuelve a 1 cada día); en la caja se escribe ese número y aparecen sus productos para cobrar e imprimir.
 *  - empresa_negocios.trabaja_pedidos: la sucursal lo activa en Mantenimiento › Sucursales
 *  - proformas.pedido_dia: el número del día del pedido (el pedido es una proforma de origen PEDIDO)
 *  - menú «Tomar Pedido» (Ventas): para los administradores y los vendedores
 */
return new class extends Migration
{
    private const URL = '/pedidos';

    public function up(): void
    {
        if (Schema::hasTable('empresa_negocios') && ! Schema::hasColumn('empresa_negocios', 'trabaja_pedidos')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('trabaja_pedidos')->default(false));
        }
        if (Schema::hasTable('proformas') && ! Schema::hasColumn('proformas', 'pedido_dia')) {
            Schema::table('proformas', function (Blueprint $t) {
                $t->unsignedInteger('pedido_dia')->nullable();
                $t->index(['id_empresa_negocio', 'fecha', 'pedido_dia'], 'proformas_pedido_dia_index');
            });
        }
        if (! Schema::hasTable('modulos') || DB::table('modulos')->where('mod_url', self::URL)->exists()) {
            return;
        }
        $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Tomar Pedido', 'mod_url' => self::URL, 'mod_gen' => 'Ventas']);
        // Administradores (2) y vendedores (5)
        foreach (DB::table('role_user')->whereIn('role_id', [2, 5])->distinct()->pluck('user_IdUsuario') as $usuario) {
            DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id]);
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', self::URL)->value('mod_id');
        if ($id) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        if (Schema::hasColumn('proformas', 'pedido_dia')) {
            Schema::table('proformas', function (Blueprint $t) {
                $t->dropIndex('proformas_pedido_dia_index');
                $t->dropColumn('pedido_dia');
            });
        }
        if (Schema::hasColumn('empresa_negocios', 'trabaja_pedidos')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('trabaja_pedidos'));
        }
    }
};
