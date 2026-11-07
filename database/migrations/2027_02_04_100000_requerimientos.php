<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requerimientos del día: el cocinero o el almacenero ve su stock, lo que falta y lo que se acabaría, y pide lo que hay
 * que comprar (al mercado o a un proveedor). Le llega al administrador, que compra, marca lo comprado y lo registra
 * como compra con un clic. Trae los roles Cocinero y Almacenero (solo entran a su sucursal, como el mozo y la caja).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([16 => ['cocinero', 'Cocinero'], 17 => ['almacenero', 'Almacenero']] as $id => [$nombre, $descripcion]) {
            if (! DB::table('roles')->where('id', $id)->exists()) {
                DB::table('roles')->insert(['id' => $id, 'name' => $nombre, 'description' => $descripcion]);
            }
        }

        if (! Schema::hasTable('requerimientos')) {
            Schema::create('requerimientos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('numero');
                $t->unsignedInteger('id_empresa_negocio');
                $t->unsignedInteger('id_almacen');
                $t->date('fecha_para');
                $t->unsignedInteger('IdUsuario');
                $t->string('estado', 12)->default('PENDIENTE');
                $t->string('observaciones', 500)->nullable();
                $t->unsignedInteger('atendido_por')->nullable();
                $t->dateTime('atendido_at')->nullable();
                $t->unsignedBigInteger('com_cab_id')->nullable();
                $t->string('motivo_anulacion', 255)->nullable();
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'numero']);
                $t->index(['id_empresa_negocio', 'estado']);
            });
        }
        if (! Schema::hasTable('requerimientos_detalle')) {
            Schema::create('requerimientos_detalle', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('requerimiento_id')->index();
                $t->unsignedInteger('IdProducto');
                $t->decimal('cantidad', 14, 3);
                // Cómo estaba al pedirlo (para que el administrador vea por qué se pidió)
                $t->decimal('stock', 14, 3)->default(0);
                $t->decimal('minimo', 14, 3)->default(0);
                $t->decimal('consumo_dia', 14, 3)->default(0);
                $t->string('nota', 150)->nullable();
                // Lo que se compró en el mercado (null = aún no se marca)
                $t->decimal('comprado', 14, 3)->nullable();
                $t->decimal('precio', 14, 4)->nullable();
            });
        }
        if (Schema::hasTable('compras_cabecera') && ! Schema::hasColumn('compras_cabecera', 'requerimiento_id')) {
            Schema::table('compras_cabecera', fn (Blueprint $t) => $t->unsignedBigInteger('requerimiento_id')->nullable());
        }

        if (Schema::hasTable('modulos') && ! DB::table('modulos')->where('mod_url', '/requerimientos')->exists()) {
            $grupo = DB::table('modulos')->where('mod_url', '/kardex/stock')->value('mod_gen') ?? 'Almacén';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Requerimientos del día', 'mod_url' => '/requerimientos', 'mod_gen' => $grupo]);
            if (Schema::hasTable('modulos_usuario')) {
                foreach (DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario')->unique() as $usuario) {
                    DB::table('modulos_usuario')->insertOrIgnore(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modulos') && ($id = DB::table('modulos')->where('mod_url', '/requerimientos')->value('mod_id'))) {
            DB::table('modulos_usuario')->where('mod_id', $id)->delete();
            DB::table('modulos')->where('mod_id', $id)->delete();
        }
        if (Schema::hasColumn('compras_cabecera', 'requerimiento_id')) {
            Schema::table('compras_cabecera', fn (Blueprint $t) => $t->dropColumn('requerimiento_id'));
        }
        Schema::dropIfExists('requerimientos_detalle');
        Schema::dropIfExists('requerimientos');
    }
};
