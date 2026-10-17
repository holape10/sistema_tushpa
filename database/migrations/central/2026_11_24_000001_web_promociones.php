<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones de la web pública (tushpa.app) creadas desde el panel: ventana emergente (modal) o barra de aviso,
 * en las páginas elegidas, entre fechas y cada cuánto se repite; opcionalmente con su propia página de oferta
 * (tushpa.app/ofertas/{slug}). Cuenta cuántas veces se vio y cuántos le dieron clic.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_promociones')) {
            return;
        }
        Schema::connection('central')->create('web_promociones', function (Blueprint $t) {
            $t->id();
            $t->string('titulo', 120);
            $t->text('texto')->nullable();
            $t->string('imagen', 255)->nullable();
            $t->string('tipo', 10)->default('MODAL');          // MODAL | BARRA
            $t->string('boton_texto', 60)->nullable();
            $t->string('boton_url', 255)->nullable();
            $t->boolean('con_pagina')->default(false);
            $t->string('slug', 80)->nullable()->unique();
            $t->text('contenido')->nullable();                 // texto largo de su página de oferta
            $t->json('paginas')->nullable();                   // todas, inicio, registro, carta, tutorial, ciudades, ingresar
            $t->date('desde')->nullable();
            $t->date('hasta')->nullable();
            $t->string('frecuencia', 10)->default('DIA');      // SIEMPRE | SESION | DIA | UNA_VEZ
            $t->unsignedSmallInteger('retraso')->default(3);   // segundos antes de aparecer
            $t->string('color', 7)->default('#2563eb');
            $t->boolean('activo')->default(true);
            $t->unsignedInteger('orden')->default(0);
            $t->unsignedInteger('vistas')->default(0);
            $t->unsignedInteger('clics')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_promociones');
    }
};
