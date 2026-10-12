<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery (fase 2 y 3):
 *  - pedidos: medio de pago que usará el cliente (y con cuánto paga, para el vuelto), costo de envío,
 *    de dónde vino el pedido (CARTA = lo hizo el cliente desde la carta QR) y su liquidación
 *  - delivery_tarifas: costo de envío según la distancia al local
 *  - delivery_liquidaciones: lo que rinde cada motorizado al terminar su turno
 *  - empresa_negocios: pedidos delivery desde la carta QR y pedido mínimo
 *  - motorizados: usuario del sistema (rol Motorizado)
 *  - rol "Motorizado" y las opciones del menú "Liquidación Delivery" y "Reporte Delivery"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $t) {
            $columnas = [
                'entrega_pago' => fn () => $t->string('entrega_pago', 20)->nullable(),
                'entrega_paga_con' => fn () => $t->decimal('entrega_paga_con', 10, 2)->nullable(),
                'entrega_envio' => fn () => $t->decimal('entrega_envio', 10, 2)->nullable(),
                'entrega_origen' => fn () => $t->string('entrega_origen', 10)->nullable(),
                'liq_id' => fn () => $t->unsignedInteger('liq_id')->nullable()->index(),
            ];
            foreach ($columnas as $nombre => $crear) {
                if (! Schema::hasColumn('pedidos', $nombre)) {
                    $crear();
                }
            }
        });

        Schema::table('motorizados', function (Blueprint $t) {
            if (! Schema::hasColumn('motorizados', 'IdUsuario')) {
                $t->unsignedInteger('IdUsuario')->nullable()->index();
            }
        });

        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'carta_delivery')) {
                $t->boolean('carta_delivery')->default(false);
                $t->decimal('delivery_minimo', 10, 2)->nullable();
            }
        });

        if (! Schema::hasTable('delivery_tarifas')) {
            Schema::create('delivery_tarifas', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->decimal('hasta_km', 6, 2);
                $t->decimal('costo', 10, 2);
            });
        }

        if (! Schema::hasTable('delivery_liquidaciones')) {
            Schema::create('delivery_liquidaciones', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->unsignedInteger('mot_id')->index();
                $t->unsignedInteger('IdUsuario')->nullable();
                $t->unsignedInteger('pedidos');
                $t->decimal('total', 12, 2);
                $t->decimal('efectivo', 12, 2);
                $t->decimal('otros', 12, 2);
                $t->decimal('envios', 12, 2);
                $t->decimal('recibido', 12, 2);
                $t->decimal('diferencia', 12, 2);
                $t->string('observacion', 255)->nullable();
                $t->dateTime('created_at');
            });
        }

        if (! DB::table('roles')->where('id', 12)->exists()) {
            DB::table('roles')->insert(['id' => 12, 'name' => 'motorizado', 'description' => 'Motorizado']);
        }

        // Menú: para quien ya tiene "Delivery en vivo"
        $delivery = DB::table('modulos')->where('mod_url', '/delivery')->first();
        if ($delivery) {
            $usuarios = DB::table('modulos_usuario')->where('mod_id', $delivery->mod_id)->pluck('user_IdUsuario');
            foreach (['/delivery/liquidacion' => 'Liquidación Delivery', '/delivery/reporte' => 'Reporte Delivery'] as $url => $nombre) {
                $id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')
                    ?? DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $delivery->mod_gen]);
                foreach ($usuarios as $usuario) {
                    if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                        DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('modulos')->whereIn('mod_url', ['/delivery/liquidacion', '/delivery/reporte'])->pluck('mod_id');
        DB::table('modulos_usuario')->whereIn('mod_id', $ids)->delete();
        DB::table('modulos')->whereIn('mod_id', $ids)->delete();
        Schema::dropIfExists('delivery_liquidaciones');
        Schema::dropIfExists('delivery_tarifas');
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn(['entrega_pago', 'entrega_paga_con', 'entrega_envio', 'entrega_origen', 'liq_id']));
        Schema::table('motorizados', fn (Blueprint $t) => $t->dropColumn('IdUsuario'));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['carta_delivery', 'delivery_minimo']));
    }
};
