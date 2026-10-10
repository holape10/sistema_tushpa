<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) Control de stock por sucursal: libre (vende sin stock), productos (no vende productos sin stock)
 *    o todo (tampoco vende platos si faltan los insumos de su receta).
 * 2) Opciones de los platos: grupos como "Entrada (elige 1)" del menú o "Arma tu trío (elige 3)",
 *    con las opciones que se pueden elegir. Lo elegido en la comanda se guarda en la línea del pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'control_stock')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('control_stock', 10)->default('libre'));
        }
        if (! Schema::hasColumn('pedidos_detalle', 'opciones')) {
            Schema::table('pedidos_detalle', fn (Blueprint $t) => $t->text('opciones')->nullable()->after('id_presentacion'));
        }
        if (! Schema::hasTable('producto_opcion_grupos')) {
            Schema::create('producto_opcion_grupos', function (Blueprint $t) {
                $t->increments('grupo_id');
                $t->unsignedBigInteger('IdProducto')->index();
                $t->string('nombre', 60);
                $t->unsignedTinyInteger('cantidad')->default(1);
                $t->boolean('obligatorio')->default(true);
                $t->boolean('repetir')->default(false);
                $t->unsignedTinyInteger('orden')->default(0);
            });
        }
        if (! Schema::hasTable('producto_opcion_items')) {
            Schema::create('producto_opcion_items', function (Blueprint $t) {
                $t->increments('item_id');
                $t->unsignedInteger('grupo_id')->index();
                $t->unsignedBigInteger('IdOpcion')->index();
                $t->decimal('precio_extra', 10, 2)->default(0);
                $t->unsignedSmallInteger('orden')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_opcion_items');
        Schema::dropIfExists('producto_opcion_grupos');
        Schema::table('pedidos_detalle', fn (Blueprint $t) => $t->dropColumn('opciones'));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('control_stock'));
    }
};
