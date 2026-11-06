<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Órdenes de compra con aprobación y mercadería en tránsito:
 *  - ordenes_compra / ordenes_compra_detalle: pedido al proveedor → aprobación del administrador → se convierte en compra.
 *  - compras_cabecera.en_transito: la factura ya está registrada pero la mercadería aún no llega (no sube el stock);
 *    al recibirla entra al almacén con su fecha de recepción. Contablemente va a la 28 (inventarios por recibir).
 * La opción del menú va en el grupo de Compras y se da a quien ya maneja compras.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordenes_compra')) {
            Schema::create('ordenes_compra', function (Blueprint $t) {
                $t->id();
                $t->string('IdEmpresa', 11);
                $t->unsignedInteger('id_empresa_negocio');
                $t->unsignedInteger('numero');
                $t->date('fecha');
                $t->date('fecha_entrega')->nullable();
                $t->unsignedInteger('prov_id')->nullable();
                $t->string('prov_tdicod', 1)->default('6');
                $t->string('prov_num', 15);
                $t->string('prov_nom', 255);
                $t->string('moneda', 3)->default('PEN');
                $t->decimal('tip_cam', 8, 3)->nullable();
                $t->unsignedInteger('id_almacen')->nullable();
                $t->string('condiciones', 255)->nullable();
                $t->string('observaciones', 500)->nullable();
                $t->decimal('total', 14, 2)->default(0);
                // PENDIENTE (por aprobar) · APROBADA · RECHAZADA · COMPRADA (ya es compra) · ANULADA
                $t->string('estado', 12)->default('PENDIENTE');
                $t->unsignedInteger('IdUsuario');
                $t->unsignedInteger('IdUsuario_aprueba')->nullable();
                $t->dateTime('aprobada_at')->nullable();
                $t->string('motivo', 255)->nullable();
                $t->unsignedBigInteger('com_cab_id')->nullable();
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'numero']);
                $t->index(['id_empresa_negocio', 'estado']);
            });
        }
        if (! Schema::hasTable('ordenes_compra_detalle')) {
            Schema::create('ordenes_compra_detalle', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('orden_id')->index();
                $t->unsignedInteger('IdProducto');
                $t->string('tip_igv', 2)->default('10');
                $t->decimal('cantidad', 14, 3);
                // Costo unitario con IGV, en la moneda de la orden
                $t->decimal('costo', 14, 4)->default(0);
                $t->decimal('total', 14, 2)->default(0);
            });
        }

        Schema::table('compras_cabecera', function (Blueprint $t) {
            if (! Schema::hasColumn('compras_cabecera', 'en_transito')) {
                $t->boolean('en_transito')->default(false);
            }
            if (! Schema::hasColumn('compras_cabecera', 'fecha_recepcion')) {
                $t->date('fecha_recepcion')->nullable();
            }
            if (! Schema::hasColumn('compras_cabecera', 'IdUsuario_recibe')) {
                $t->unsignedInteger('IdUsuario_recibe')->nullable();
            }
            // Para actualizar el costo de los productos recién cuando llega la mercadería
            if (! Schema::hasColumn('compras_cabecera', 'actualizar_costo')) {
                $t->boolean('actualizar_costo')->default(true);
            }
            if (! Schema::hasColumn('compras_cabecera', 'orden_id')) {
                $t->unsignedBigInteger('orden_id')->nullable();
            }
        });

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/ordenes-compra')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/compras')->value('mod_gen') ?? 'Compras';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Órdenes de compra', 'mod_url' => '/ordenes-compra', 'mod_gen' => $grupo]);
            if (Schema::hasTable('modulos_usuario')) {
                $compras = DB::table('modulos')->where('mod_url', '/compras')->value('mod_id');
                $usuarios = DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')
                    ->merge($compras ? DB::table('modulos_usuario')->where('mod_id', $compras)->pluck('user_IdUsuario') : [])->unique();
                foreach ($usuarios as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/ordenes-compra')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        Schema::table('compras_cabecera', function (Blueprint $t) {
            foreach (['en_transito', 'fecha_recepcion', 'IdUsuario_recibe', 'actualizar_costo', 'orden_id'] as $columna) {
                if (Schema::hasColumn('compras_cabecera', $columna)) {
                    $t->dropColumn($columna);
                }
            }
        });
        Schema::dropIfExists('ordenes_compra_detalle');
        Schema::dropIfExists('ordenes_compra');
    }
};
