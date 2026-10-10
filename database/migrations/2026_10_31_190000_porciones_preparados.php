<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gestión de preparados: platos que se preparan por cantidad cada día (juanes, tamales, sopa del día...) y se controlan
 * por porciones en vez de por insumos. Cada día empieza en cero: "hoy preparé 20"; las ventas restan y las anulaciones devuelven.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'controla_porciones')) {
            Schema::table('productos', fn (Blueprint $t) => $t->boolean('controla_porciones')->default(false));
        }
        if (! Schema::hasTable('porciones_movimientos')) {
            Schema::create('porciones_movimientos', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('IdProducto')->index();
                $t->string('tipo', 12); // PREPARADO, VENTA, ANULACION, AJUSTE
                $t->decimal('cantidad', 10, 2);
                $t->date('fecha')->index();
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->string('observacion', 150)->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('created_at')->nullable();
            });
        }

        $modulo = DB::table('modulos')->whereIn('mod_nom', ['Gestión Preparados', 'Gestion Preparados', 'Gestión de Preparados'])->first();
        $modulo
            ? DB::table('modulos')->where('mod_id', $modulo->mod_id)->update(['mod_nom' => 'Gestión de Preparados', 'mod_url' => '/preparados', 'mod_gen' => 'Restaurante'])
            : DB::table('modulos')->insert(['mod_nom' => 'Gestión de Preparados', 'mod_url' => '/preparados', 'mod_gen' => 'Restaurante']);

        $mesas = DB::table('modulos')->where('mod_url', '/mesas')->value('mod_id');
        $nuevo = DB::table('modulos')->where('mod_url', '/preparados')->value('mod_id');
        $admins = $mesas ? DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $mesas)->where('r.role_id', 2)->distinct()->pluck('mu.user_IdUsuario') : collect();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $nuevo)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $nuevo, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('porciones_movimientos');
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('controla_porciones'));
        DB::table('modulos')->where('mod_url', '/preparados')->update(['mod_nom' => 'Gestión Preparados', 'mod_url' => '#', 'mod_gen' => 'Mantenimiento']);
    }
};
