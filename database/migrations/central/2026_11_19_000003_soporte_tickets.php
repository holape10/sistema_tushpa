<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tickets de soporte: el administrador de cada empresa reporta fallas o consultas (con fotos) y le llegan al usuario
 * del panel que creó esa empresa (y al dueño del sistema). Se conversa en el ticket hasta cerrarlo.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $central = Schema::connection('central');
        if (! $central->hasTable('soporte_tickets')) {
            $central->create('soporte_tickets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id');
                $t->string('asunto', 150);
                $t->string('tipo', 20)->default('FALLA');          // FALLA | CONSULTA | SUNAT | MEJORA
                $t->string('prioridad', 10)->default('NORMAL');    // NORMAL | URGENTE
                $t->string('estado', 15)->default('ABIERTO');      // ABIERTO | EN_PROCESO | RESPONDIDO | CERRADO
                $t->string('usuario', 120);                        // quién lo envió en la empresa
                $t->unsignedBigInteger('usuario_id')->nullable();
                $t->string('sucursal', 150)->nullable();
                $t->string('telefono', 20)->nullable();
                $t->boolean('leido_soporte')->default(false);
                $t->boolean('leido_cliente')->default(true);
                $t->timestamp('cerrado_at')->nullable();
                $t->timestamps();
                $t->index(['cliente_id', 'estado']);
                $t->index(['estado', 'updated_at']);
            });
        }
        if (! $central->hasTable('soporte_mensajes')) {
            $central->create('soporte_mensajes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('ticket_id');
                $t->string('autor', 10);                           // CLIENTE | SOPORTE
                $t->string('autor_nombre', 120);
                $t->text('texto')->nullable();
                $t->json('adjuntos')->nullable();                  // rutas en storage/app/soporte/{ticket}/
                $t->timestamp('created_at')->nullable();
                $t->index('ticket_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('soporte_mensajes');
        Schema::connection('central')->dropIfExists('soporte_tickets');
    }
};
