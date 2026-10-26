<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal: mostrar la foto de cada producto (miniatura liviana) en los botones de la comanda y de los puntos de venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'imagenes_venta')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->boolean('imagenes_venta')->default(false));
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('imagenes_venta'));
    }
};
