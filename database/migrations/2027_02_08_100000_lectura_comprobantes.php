<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leer comprobantes de compra (foto, PDF o XML): lo que el sistema aprende de cada proveedor. Cuando el usuario elige
 * qué producto es una línea que no se reconoció ("ARROZ COSTEÑO X 50KG" del proveedor X = su producto ARROZ), la próxima
 * vez esa línea de ese proveedor se llena sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('compras_lectura_productos')) {
            Schema::create('compras_lectura_productos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('id_empresa_negocio');
                $t->string('ruc_emisor', 15)->default('');
                $t->string('clave', 120);
                $t->unsignedInteger('IdProducto');
                $t->timestamps();
                $t->unique(['id_empresa_negocio', 'ruc_emisor', 'clave'], 'lectura_prod_unico');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_lectura_productos');
    }
};
