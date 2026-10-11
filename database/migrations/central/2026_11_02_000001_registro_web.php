<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro desde la web (tushpa.app): la empresa se crea sola con días de prueba gratis.
 *  - clientes.prueba_hasta: último día de la prueba (null = ya pagó o la creó el panel)
 *  - clientes.origen: 'panel' o 'web'
 *  - web_contactos: mensajes del formulario de contacto de la web
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        $central->table('clientes', function (Blueprint $t) use ($central) {
            if (! $central->hasColumn('clientes', 'prueba_hasta')) {
                $t->date('prueba_hasta')->nullable();
            }
            if (! $central->hasColumn('clientes', 'origen')) {
                $t->string('origen', 10)->default('panel');
            }
            if (! $central->hasColumn('clientes', 'registro_ip')) {
                $t->string('registro_ip', 45)->nullable();
            }
            if (! $central->hasColumn('clientes', 'suspendido_el')) {
                $t->date('suspendido_el')->nullable();
            }
        });

        if (! $central->hasTable('web_contactos')) {
            $central->create('web_contactos', function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 120);
                $t->string('correo', 120)->nullable();
                $t->string('telefono', 30)->nullable();
                $t->string('empresa', 150)->nullable();
                $t->string('servicio', 40)->nullable();
                $t->text('mensaje');
                $t->string('ip', 45)->nullable();
                $t->boolean('atendido')->default(false);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('web_contactos');
        Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['prueba_hasta', 'origen', 'registro_ip', 'suspendido_el']));
    }
};
