<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ver como el cliente" (modo soporte): cada vez que alguien del panel entra al sistema de una empresa como uno de sus
 * usuarios. El enlace es de un solo uso y vence en 2 minutos; queda quién entró, como quién, en qué modo y cuándo salió.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('soporte_ingresos')) {
            Schema::connection('central')->create('soporte_ingresos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->index();
                $t->unsignedBigInteger('superadmin_id');
                $t->string('superadmin_nombre', 120);
                $t->unsignedBigInteger('IdUsuario');
                $t->string('usuario_nombre', 150)->nullable();
                $t->string('modo', 10)->default('mirar');
                $t->char('token_hash', 64)->unique();
                $t->timestamp('expira_at');
                $t->timestamp('usado_at')->nullable();
                $t->timestamp('salio_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('soporte_ingresos');
    }
};
