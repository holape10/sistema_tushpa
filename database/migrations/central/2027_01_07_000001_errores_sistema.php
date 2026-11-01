<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitor de errores: cada falla del sistema (en cualquier empresa) queda anotada una vez con cuántas veces pasó, en qué
 * empresa y pantalla, y si ya se le avisó al dueño por Telegram. Así el dueño se entera antes de que llame el cliente.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('errores_sistema')) {
            return;
        }
        Schema::connection('central')->create('errores_sistema', function (Blueprint $t) {
            $t->id();
            $t->char('huella', 40)->unique();          // el mismo error (clase, archivo, línea y mensaje sin números)
            $t->string('tipo', 150);
            $t->text('mensaje');
            $t->string('archivo', 255)->nullable();
            $t->unsignedInteger('linea')->nullable();
            $t->string('url', 300)->nullable();
            $t->string('base_datos', 64)->nullable();
            $t->string('empresa', 150)->nullable();
            $t->string('usuario', 120)->nullable();
            $t->unsignedInteger('veces')->default(1);
            $t->dateTime('primera_vez');
            $t->dateTime('ultima_vez');
            $t->dateTime('avisado_el')->nullable();
            $t->boolean('resuelto')->default(false);
            $t->index(['resuelto', 'ultima_vez']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('errores_sistema');
    }
};
