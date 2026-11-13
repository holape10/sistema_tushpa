<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descuento por producto en el Punto de venta: lo máximo que la caja puede bajar a cada producto sin la clave del
 * administrador, en soles (S) o en % (P). 0 = la caja siempre necesita la clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_negocios')) {
            return;
        }
        if (! Schema::hasColumn('empresa_negocios', 'descuento_item_max')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->decimal('descuento_item_max', 10, 2)->default(0));
        }
        if (! Schema::hasColumn('empresa_negocios', 'descuento_item_tipo')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('descuento_item_tipo', 1)->default('S'));
        }
    }

    public function down(): void
    {
        foreach (['descuento_item_max', 'descuento_item_tipo'] as $c) {
            if (Schema::hasColumn('empresa_negocios', $c)) {
                Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
