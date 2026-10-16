<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - productos.imagenes_extra: hasta 3 fotos más del producto (la principal sigue en imagenproducto): 4 en total.
 * - Tienda virtual: portada (banner), color de la marca y frase corta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('productos') && ! Schema::hasColumn('productos', 'imagenes_extra')) {
            Schema::table('productos', fn (Blueprint $t) => $t->text('imagenes_extra')->nullable());
        }
        if (Schema::hasTable('empresa_negocios')) {
            Schema::table('empresa_negocios', function (Blueprint $t) {
                if (! Schema::hasColumn('empresa_negocios', 'tienda_banner')) {
                    $t->string('tienda_banner', 255)->nullable();
                }
                if (! Schema::hasColumn('empresa_negocios', 'tienda_color')) {
                    $t->string('tienda_color', 7)->nullable();
                }
                if (! Schema::hasColumn('empresa_negocios', 'tienda_eslogan')) {
                    $t->string('tienda_eslogan', 150)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn('imagenes_extra'));
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['tienda_banner', 'tienda_color', 'tienda_eslogan']));
    }
};
