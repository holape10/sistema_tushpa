<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anuncios generales que el dueño envía desde el panel a todas las empresas (o a las de algunos rubros):
 * salen como una franja arriba de todas las pantallas del sistema mientras estén vigentes.
 * Ej.: "Hoy SUNAT presenta fallas en la recepción de comprobantes".
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('anuncios_sistema')) {
            return;
        }
        Schema::connection('central')->create('anuncios_sistema', function (Blueprint $t) {
            $t->id();
            $t->string('mensaje', 600);
            $t->string('tipo', 10)->default('INFO');            // INFO | AVISO | URGENTE | OFERTA (color de la franja)
            $t->json('rubros')->nullable();                     // null = todas las empresas
            $t->dateTime('desde')->nullable();
            $t->dateTime('hasta')->nullable();
            $t->boolean('se_puede_cerrar')->default(true);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('anuncios_sistema');
    }
};
