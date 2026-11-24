<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuales del asistente: los tutoriales en PDF que se suben en el panel. La IA los lee una sola vez y los deja en
 * secciones de texto; cuando un usuario pregunta cómo se hace algo, el asistente busca en esas secciones.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('asistente_manuales')) {
            Schema::connection('central')->create('asistente_manuales', function (Blueprint $t) {
                $t->id();
                $t->string('titulo', 150);
                $t->string('rubro', 30)->default('TODOS');
                // Solo para una empresa (un manual hecho a su medida); vacío = para todas
                $t->unsignedBigInteger('cliente_id')->nullable();
                $t->string('archivo', 255)->nullable();
                $t->string('estado', 20)->default('procesando');
                $t->string('error', 255)->nullable();
                $t->unsignedInteger('secciones')->default(0);
                $t->decimal('costo_usd', 10, 4)->default(0);
                $t->string('subido_por', 120)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::connection('central')->hasTable('asistente_manual_secciones')) {
            Schema::connection('central')->create('asistente_manual_secciones', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('manual_id')->index();
                $t->unsignedInteger('orden')->default(0);
                $t->string('titulo', 200);
                $t->string('palabras', 500)->nullable();
                $t->text('texto');
                // Título, palabras y texto sin tildes y en minúsculas: para buscar rápido
                $t->text('buscar');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('asistente_manual_secciones');
        Schema::connection('central')->dropIfExists('asistente_manuales');
    }
};
