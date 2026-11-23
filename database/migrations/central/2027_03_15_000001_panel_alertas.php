<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alertas en el panel: cambios delicados que hace una empresa (editar la empresa o una sucursal, importar productos,
 * crear, editar o eliminar usuarios). Salen en la lista de clientes con ⚠️ hasta que alguien del panel las descarta.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('panel_alertas')) {
            Schema::connection('central')->create('panel_alertas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id');
                $t->unsignedBigInteger('bitacora_id')->nullable();
                $t->dateTime('fecha');
                $t->string('accion', 60);
                $t->string('descripcion', 255);
                $t->string('usuario', 120)->nullable();
                $t->string('ip', 45)->nullable();
                $t->dateTime('descartada_el')->nullable();
                $t->string('descartada_por', 120)->nullable();
                $t->index(['cliente_id', 'descartada_el']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('panel_alertas');
    }
};
