<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fidelización: el cliente con DNI o RUC gana puntos con cada compra y los canjea por premios.
 *  - empresa_negocios: fid_activo (Sí/No por sucursal), fid_soles_por_punto (S/ que valen 1 punto), fid_compra_minima.
 *  - cliente.puntos: saldo actual (el detalle está en fid_movimientos).
 *  - fid_premios: lo que se puede canjear y cuántos puntos cuesta.
 *  - fid_movimientos: cada suma (venta), resta (canje, anulación) o ajuste, con el saldo que quedó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'fid_activo')) {
            Schema::table('empresa_negocios', function (Blueprint $t) {
                $t->tinyInteger('fid_activo')->default(0);
                $t->decimal('fid_soles_por_punto', 8, 2)->default(1);
                $t->decimal('fid_compra_minima', 10, 2)->default(0);
            });
        }
        if (! Schema::hasColumn('cliente', 'puntos')) {
            Schema::table('cliente', fn (Blueprint $t) => $t->integer('puntos')->default(0));
        }

        if (! Schema::hasTable('fid_premios')) {
            Schema::create('fid_premios', function (Blueprint $t) {
                $t->increments('premio_id');
                $t->string('nombre', 120);
                $t->string('descripcion', 255)->nullable();
                $t->unsignedInteger('puntos');
                $t->tinyInteger('activo')->default(1);
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        if (! Schema::hasTable('fid_movimientos')) {
            Schema::create('fid_movimientos', function (Blueprint $t) {
                $t->bigIncrements('mov_id');
                $t->unsignedInteger('clicod')->index();
                $t->unsignedInteger('IdCpe_cabecera')->nullable()->index();
                $t->unsignedInteger('premio_id')->nullable();
                $t->string('tipo', 10);                     // VENTA | CANJE | ANULACION | AJUSTE
                $t->integer('puntos');                      // + suma, - resta
                $t->integer('saldo');                       // saldo del cliente después del movimiento
                $t->string('detalle', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->dateTime('fecha');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
            });
        }

        // El menú "Configurar Reglas" (grupo Fidelización) estaba como "Pronto"
        DB::table('modulos')->where('mod_gen', 'like', 'Fidelizaci%')->where(fn ($q) => $q->whereNull('mod_url')->orWhere('mod_url', '#'))
            ->update(['mod_nom' => 'Puntos y Premios', 'mod_url' => '/fidelizacion']);
        if (! DB::table('modulos')->where('mod_url', '/fidelizacion')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Puntos y Premios', 'mod_url' => '/fidelizacion', 'mod_gen' => 'Fidelización']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fid_movimientos');
        Schema::dropIfExists('fid_premios');
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['fid_activo', 'fid_soles_por_punto', 'fid_compra_minima']));
        DB::table('modulos')->where('mod_url', '/fidelizacion')->update(['mod_url' => '#', 'mod_nom' => 'Configurar Reglas']);
    }
};
