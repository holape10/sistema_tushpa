<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Premios de fidelización: pueden ser un producto del catálogo (al canjear sale del almacén, operación 08 "Premio")
 * y tener fecha de vencimiento (después ya no se pueden canjear).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fid_premios', 'IdProducto')) {
            Schema::table('fid_premios', function (Blueprint $t) {
                $t->unsignedInteger('IdProducto')->nullable();
                $t->decimal('cantidad', 10, 3)->default(1);
                $t->date('vence')->nullable();
            });
        }
        if (! Schema::hasColumn('fid_movimientos', 'cantidad')) {
            Schema::table('fid_movimientos', fn (Blueprint $t) => $t->decimal('cantidad', 10, 3)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('fid_premios', fn (Blueprint $t) => $t->dropColumn(['IdProducto', 'cantidad', 'vence']));
        Schema::table('fid_movimientos', fn (Blueprint $t) => $t->dropColumn('cantidad'));
    }
};
