<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Crea tu carta": el negocio escribe sus productos (categoría, nombre, precio, descripción y una foto cada uno).
 * La foto de la carta impresa pasa a ser opcional.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $esquema = Schema::connection('central');
        if (! $esquema->hasTable('cartas_web_productos')) {
            $esquema->create('cartas_web_productos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('carta_id')->index();
                $t->string('categoria', 60)->nullable();
                $t->string('nombre', 120);
                $t->string('descripcion', 255)->nullable();
                $t->decimal('precio', 10, 2)->nullable();
                $t->string('foto', 255)->nullable();
                $t->unsignedSmallInteger('orden')->default(0);
                $t->timestamps();
            });
        }
        $esquema->table('cartas_web', fn (Blueprint $t) => $t->string('foto', 255)->nullable()->change());
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('cartas_web_productos');
    }
};
