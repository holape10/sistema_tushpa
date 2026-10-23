<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja sin internet: cada PC de caja tiene su propia serie para cuando se cae el internet (BX01, FX01, NX01…),
 * así el comprobante sale con su número real sin preguntar a la nube y nunca choca con otra caja.
 * ventas_offline guarda cada venta que llega al volver el internet (por su código único: nunca se duplica).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('series_offline')) {
            Schema::create('series_offline', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->string('pc', 40);
                $t->char('tdocod', 2);
                $t->char('serie', 4);
                $t->unsignedInteger('ultimo')->default(0);   // último número que ya llegó al sistema
                $t->timestamp('created_at')->nullable();
                $t->unique(['id_empresa_negocio', 'pc', 'tdocod']);
                $t->index('serie');
            });
        }
        if (! Schema::hasTable('ventas_offline')) {
            Schema::create('ventas_offline', function (Blueprint $t) {
                $t->increments('id');
                $t->char('uuid', 36)->unique();
                $t->unsignedBigInteger('id_empresa_negocio')->index();
                $t->string('pc', 40);
                $t->unsignedInteger('IdUsuario');
                $t->unsignedInteger('id_turno')->nullable();
                $t->unsignedInteger('IdCpe_cabecera')->nullable();
                $t->char('tdocod', 2);
                $t->char('serie', 4);
                $t->unsignedInteger('numero');
                $t->decimal('total', 12, 2);
                $t->dateTime('fecha_venta');
                $t->string('estado', 10);                     // OK | ERROR
                $t->string('error', 500)->nullable();
                $t->longText('datos');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_offline');
        Schema::dropIfExists('series_offline');
    }
};
