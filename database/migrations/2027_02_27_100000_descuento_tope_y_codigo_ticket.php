<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal:
 *  - descuento_max_tipo: el máximo de descuento de la caja en % (como siempre) o en soles (ej. hasta S/ 5).
 *  - ticket_codigo: imprimir el código interno de cada producto en el ticket (apagado por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('empresa_negocios')) {
            return;
        }
        if (! Schema::hasColumn('empresa_negocios', 'descuento_max_tipo')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('descuento_max_tipo', 1)->default('P'));
        }
        if (! Schema::hasColumn('empresa_negocios', 'ticket_codigo')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('ticket_codigo')->default(false));
        }
    }

    public function down(): void
    {
        foreach (['descuento_max_tipo', 'ticket_codigo'] as $c) {
            if (Schema::hasColumn('empresa_negocios', $c)) {
                Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
