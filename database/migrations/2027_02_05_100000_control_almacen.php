<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Más control del almacén:
 *  - Requerimientos urgentes (avisan al celular del administrador) y pedidos a otra sede (la central despacha, llega en
 *    tránsito y la sede lo recibe): cada sede tiene su propio catálogo, por eso cada línea guarda el producto de la sede que envía.
 *  - Inventario ciego: el almacenero cuenta sin ver el stock; el administrador ve las diferencias y decide si ajusta.
 *  - Control de insumos (reporte) y el inventario ciego como opciones del menú.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requerimientos', function (Blueprint $t) {
            if (! Schema::hasColumn('requerimientos', 'urgente')) {
                $t->boolean('urgente')->default(false)->after('estado');
            }
            if (! Schema::hasColumn('requerimientos', 'sucursal_origen')) {
                // null = se compra; con sede = se pide a esa sede (la central)
                $t->unsignedInteger('sucursal_origen')->nullable()->after('id_almacen');
                $t->unsignedInteger('id_almacen_origen')->nullable()->after('sucursal_origen');
                $t->unsignedInteger('despachado_por')->nullable();
                $t->dateTime('despachado_at')->nullable();
                $t->unsignedInteger('recibido_por')->nullable();
                $t->dateTime('recibido_at')->nullable();
                $t->index(['sucursal_origen', 'estado']);
            }
        });
        Schema::table('requerimientos_detalle', function (Blueprint $t) {
            if (! Schema::hasColumn('requerimientos_detalle', 'IdProducto_origen')) {
                $t->unsignedInteger('IdProducto_origen')->nullable();
                $t->decimal('enviado', 14, 3)->nullable();
                $t->decimal('recibido', 14, 3)->nullable();
            }
        });

        if (! Schema::hasTable('conteos_ciegos')) {
            Schema::create('conteos_ciegos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio');
                $t->unsignedInteger('id_almacen');
                $t->unsignedInteger('IdUsuario');
                $t->string('estado', 12)->default('PENDIENTE');
                $t->string('observaciones', 255)->nullable();
                $t->unsignedInteger('revisado_por')->nullable();
                $t->dateTime('revisado_at')->nullable();
                $t->unsignedBigInteger('inv_cab_id')->nullable();
                $t->timestamps();
                $t->index(['id_empresa_negocio', 'estado']);
            });
        }
        if (! Schema::hasTable('conteos_ciegos_detalle')) {
            Schema::create('conteos_ciegos_detalle', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('conteo_id')->index();
                $t->unsignedInteger('IdProducto');
                $t->decimal('contado', 14, 3);
                // El stock del sistema en el momento de contar (no lo ve quien cuenta)
                $t->decimal('sistema', 14, 3);
                $t->decimal('costo', 14, 4)->default(0);
            });
        }

        if (Schema::hasTable('modulos')) {
            $grupo = DB::table('modulos')->where('mod_url', '/kardex/stock')->value('mod_gen') ?? 'Almacén';
            foreach (['/conteo-ciego' => 'Inventario ciego', '/control-insumos' => 'Control de insumos'] as $url => $nombre) {
                if (DB::table('modulos')->where('mod_url', $url)->exists()) {
                    continue;
                }
                $id = DB::table('modulos')->insertGetId(['mod_nom' => $nombre, 'mod_url' => $url, 'mod_gen' => $grupo]);
                if (Schema::hasTable('modulos_usuario')) {
                    foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                        DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos')) {
            foreach (['/conteo-ciego', '/control-insumos'] as $url) {
                if ($id = DB::table('modulos')->where('mod_url', $url)->value('mod_id')) {
                    DB::table('modulos_usuario')->where('mod_id', $id)->delete();
                    DB::table('modulos')->where('mod_id', $id)->delete();
                }
            }
        }
        Schema::dropIfExists('conteos_ciegos_detalle');
        Schema::dropIfExists('conteos_ciegos');
        Schema::table('requerimientos_detalle', fn (Blueprint $t) => $t->dropColumn(['IdProducto_origen', 'enviado', 'recibido']));
        Schema::table('requerimientos', function (Blueprint $t) {
            $t->dropIndex(['sucursal_origen', 'estado']);
            $t->dropColumn(['urgente', 'sucursal_origen', 'id_almacen_origen', 'despachado_por', 'despachado_at', 'recibido_por', 'recibido_at']);
        });
    }
};
