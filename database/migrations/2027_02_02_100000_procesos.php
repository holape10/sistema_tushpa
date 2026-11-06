<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procesos en segundo plano de la empresa (copia de seguridad, ZIP de comprobantes…): su estado, su avance y el archivo
 * que dejan listo para descargar. El usuario los ve en una pantalla que se actualiza sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('procesos')) {
            Schema::create('procesos', function (Blueprint $t) {
                $t->id();
                $t->string('tipo', 30);
                $t->string('descripcion', 200);
                // EN COLA · EN PROCESO · LISTO · ERROR
                $t->string('estado', 12)->default('EN COLA');
                $t->string('mensaje', 500)->nullable();
                $t->string('archivo', 255)->nullable();
                $t->string('nombre_archivo', 150)->nullable();
                $t->unsignedInteger('IdUsuario');
                $t->dateTime('iniciado_at')->nullable();
                $t->dateTime('terminado_at')->nullable();
                $t->timestamps();
                $t->index(['IdUsuario', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procesos');
    }
};
