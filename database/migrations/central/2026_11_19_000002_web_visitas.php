<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitas a la web pública (tushpa.app): una fila por página vista. El visitante se identifica con un código anónimo
 * (huella del IP + navegador del día, sin guardar el IP) para contar personas sin datos personales.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('web_visitas')) {
            return;
        }
        Schema::connection('central')->create('web_visitas', function (Blueprint $t) {
            $t->id();
            $t->dateTime('fecha');
            $t->string('ruta', 120);
            $t->char('visitante', 16);
            $t->string('origen', 80)->nullable();
            $t->string('dispositivo', 10);
            $t->index('fecha');
            $t->index(['visitante', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_visitas');
    }
};
