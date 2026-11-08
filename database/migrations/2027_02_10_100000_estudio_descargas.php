<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descarga automática del estudio contable: el administrador elige el día del mes (p. ej. el 5) y qué traer (propuestas
 * del SIRE de compras y ventas, y los XML de las compras) del mes anterior. Esa noche el sistema lo trae solo, empresa por
 * empresa, y al día siguiente el contador ya tiene todo. Cada empresa y tarea queda anotada (lista, en proceso o con error).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estudio_programacion')) {
            Schema::create('estudio_programacion', function (Blueprint $t) {
                $t->id();
                $t->boolean('activo')->default(false);
                $t->unsignedTinyInteger('dia')->default(5);
                $t->unsignedTinyInteger('hora')->default(1);
                $t->boolean('sire_compras')->default(true);
                $t->boolean('sire_ventas')->default(true);
                $t->boolean('xml')->default(true);
                $t->char('avisado_periodo', 6)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('estudio_descargas')) {
            Schema::create('estudio_descargas', function (Blueprint $t) {
                $t->id();
                $t->char('periodo', 6);
                $t->string('IdEmpresa', 11);
                $t->string('tarea', 15);
                $t->string('estado', 10)->default('PENDIENTE');
                $t->string('mensaje', 255)->nullable();
                $t->unsignedTinyInteger('intentos')->default(0);
                $t->unsignedBigInteger('solicitud_id')->nullable();
                $t->unsignedInteger('cantidad')->default(0);
                // XML que SUNAT no tenía (no se vuelven a pedir)
                $t->json('fallidos')->nullable();
                $t->dateTime('fin_at')->nullable();
                $t->timestamps();
                $t->unique(['periodo', 'IdEmpresa', 'tarea']);
                $t->index(['estado', 'periodo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_descargas');
        Schema::dropIfExists('estudio_programacion');
    }
};
