<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos en línea del servicio (Culqi): tarjeta, Yape o billeteras con QR. Al confirmarse el pago la empresa se
 * reactiva sola y su vencimiento se corre los meses pagados. Cada intento queda anotado (también los fallidos).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (Schema::connection('central')->hasTable('pagos_online')) {
            return;
        }
        Schema::connection('central')->create('pagos_online', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cliente_id')->index();
            $t->unsignedTinyInteger('meses');
            $t->decimal('monto', 10, 2);
            $t->string('estado', 12)->default('PENDIENTE');     // PENDIENTE | PAGADO | FALLIDO
            $t->string('medio', 20)->nullable();               // tarjeta | yape | billetera | agente | bancaMovil
            $t->string('culqi_orden', 60)->nullable()->index(); // ord_… (billeteras con QR, agentes, banca móvil)
            $t->string('culqi_cargo', 60)->nullable()->index(); // chr_… (tarjeta o Yape)
            $t->string('correo', 120)->nullable();
            $t->string('detalle', 255)->nullable();             // mensaje de Culqi (aceptado o el motivo del rechazo)
            $t->date('vence_antes')->nullable();
            $t->date('vence_despues')->nullable();
            $t->timestamp('pagado_el')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('pagos_online');
    }
};
