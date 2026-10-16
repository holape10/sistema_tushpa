<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Crea tu carta" (tushpa.app): cualquier negocio, solo con su correo, sube la foto de su carta, elige un diseño y recibe
 * su enlace (tushpa.app/c/su-negocio) y su QR. Se edita con un enlace secreto (token) que también se recupera por correo.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('cartas_web')) {
            return;
        }
        Schema::connection('central')->create('cartas_web', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('token', 64);
            $t->string('nombre', 120);
            $t->string('email', 120)->index();
            $t->string('whatsapp', 20)->nullable();
            $t->string('direccion', 200)->nullable();
            $t->string('horario', 120)->nullable();
            $t->string('foto', 255);
            $t->string('diseno', 20)->default('clasico');
            $t->unsignedInteger('visitas')->default(0);
            $t->string('estado', 12)->default('ACTIVA');   // ACTIVA | BLOQUEADA
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('cartas_web');
    }
};
