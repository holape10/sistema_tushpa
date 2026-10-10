<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel: reservas por fecha, bitácora (consumos quitados, cambios de habitación, salidas sin cobrar exceso, anulaciones)
 * y minutos de tolerancia configurables por sucursal. Agrega el reporte del hotel al menú.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'hotel_tolerancia')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->unsignedSmallInteger('hotel_tolerancia')->default(10));
        }
        if (! Schema::hasTable('hotel_reservas')) {
            Schema::create('hotel_reservas', function (Blueprint $t) {
                $t->increments('res_id');
                $t->unsignedInteger('hab_id')->index();
                $t->dateTime('llegada')->index();
                $t->unsignedBigInteger('IdProducto');
                $t->decimal('cantidad', 8, 2)->default(1);
                $t->string('cliente', 150);
                $t->string('documento', 15)->nullable();
                $t->string('telefono', 20)->nullable();
                $t->unsignedTinyInteger('personas')->default(1);
                $t->string('nota', 200)->nullable();
                $t->string('estado', 12)->default('PENDIENTE'); // PENDIENTE, INGRESADA, CANCELADA
                $t->unsignedInteger('hos_id')->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('hotel_eventos')) {
            Schema::create('hotel_eventos', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('hos_id')->nullable()->index();
                $t->unsignedInteger('hab_id')->nullable();
                $t->string('tipo', 20); // ANULACION, SALIDA_SIN_EXCESO, CONSUMO_QUITADO, CAMBIO_HABITACION, EXCESO_COBRADO
                $t->integer('minutos')->nullable();
                $t->decimal('monto', 10, 2)->nullable();
                $t->string('detalle', 200)->nullable();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedInteger('autorizado_por')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->timestamp('created_at')->nullable()->index();
            });
        }

        if (! DB::table('modulos')->where('mod_url', '/hotel/reporte')->exists()) {
            DB::table('modulos')->insert(['mod_nom' => 'Reporte: Hotel', 'mod_url' => '/hotel/reporte', 'mod_gen' => 'Ventas']);
        }
        $hotel = DB::table('modulos')->where('mod_url', '/hotel')->value('mod_id');
        $reporte = DB::table('modulos')->where('mod_url', '/hotel/reporte')->value('mod_id');
        $admins = $hotel ? DB::table('modulos_usuario as mu')->join('role_user as r', 'r.user_IdUsuario', '=', 'mu.user_IdUsuario')
            ->where('mu.mod_id', $hotel)->whereIn('r.role_id', [2, 4])->distinct()->pluck('mu.user_IdUsuario') : collect();
        foreach ($admins as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $reporte)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $reporte, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_eventos');
        Schema::dropIfExists('hotel_reservas');
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('hotel_tolerancia'));
        DB::table('modulos')->where('mod_url', '/hotel/reporte')->delete();
    }
};
