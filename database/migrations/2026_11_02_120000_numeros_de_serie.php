<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Números de serie (electrodomésticos, celulares: serie del fabricante o IMEI):
 *  - productos.control_serie: el producto se compra y se vende indicando la serie de cada unidad
 *  - productos.garantia_meses: garantía que se anota a la serie al venderla
 *  - producto_series: cada unidad con su serie, de qué compra o ingreso vino y en qué comprobante se vendió
 *  - compras_detalle.series y cpe_detalle.series: las series de la línea (para editar la compra y para el ticket)
 * Además la opción del menú "Números de serie" (consulta para garantías), para quien ve el Kardex.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'control_serie')) {
                $t->boolean('control_serie')->default(false);
            }
            if (! Schema::hasColumn('productos', 'garantia_meses')) {
                $t->unsignedSmallInteger('garantia_meses')->nullable();
            }
        });
        foreach (['compras_detalle', 'cpe_detalle'] as $tabla) {
            if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, 'series')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->text('series')->nullable());
            }
        }
        if (! Schema::hasTable('producto_series')) {
            Schema::create('producto_series', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('IdProducto');
                $t->unsignedBigInteger('id_almacen')->nullable();
                $t->unsignedBigInteger('id_empresa_negocio');
                $t->string('serie', 80);
                $t->string('estado', 12)->default('DISPONIBLE');   // DISPONIBLE | VENDIDO | BAJA
                $t->unsignedBigInteger('com_cab_id')->nullable()->index();
                $t->unsignedBigInteger('mov_cab_id')->nullable();
                $t->date('fecha_ingreso')->nullable();
                $t->unsignedBigInteger('IdCpe_cabecera')->nullable()->index();
                $t->date('fecha_venta')->nullable();
                $t->string('cliente', 150)->nullable();
                $t->date('garantia_hasta')->nullable();
                $t->timestamps();
                $t->unique(['IdProducto', 'serie']);
                $t->index(['id_empresa_negocio', 'serie']);
            });
        }

        // Menú: consulta de series (al lado del Kardex)
        $id = DB::table('modulos')->where('mod_url', '/series')->value('mod_id');
        if (! $id) {
            $grupo = DB::table('modulos')->where('mod_url', '/kardex')->value('mod_gen') ?? 'Mantenimiento';
            $id = DB::table('modulos')->insertGetId(['mod_nom' => 'Números de serie', 'mod_url' => '/series', 'mod_gen' => $grupo]);
        }
        $usuarios = DB::table('modulos_usuario as mu')->join('modulos as m', 'm.mod_id', '=', 'mu.mod_id')->where('m.mod_url', '/kardex')->pluck('mu.user_IdUsuario')->unique();
        foreach ($usuarios as $usuario) {
            if (! DB::table('modulos_usuario')->where('user_IdUsuario', $usuario)->where('mod_id', $id)->exists()) {
                DB::table('modulos_usuario')->insert(['user_IdUsuario' => $usuario, 'mod_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('modulos')->where('mod_url', '/series')->value('mod_id');
        DB::table('modulos_usuario')->where('mod_id', $id)->delete();
        DB::table('modulos')->where('mod_id', $id)->delete();
        Schema::dropIfExists('producto_series');
        foreach (['compras_detalle', 'cpe_detalle'] as $tabla) {
            if (Schema::hasColumn($tabla, 'series')) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn('series'));
            }
        }
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['control_serie', 'garantia_meses']));
    }
};
