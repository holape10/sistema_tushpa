<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistente IA (base central): cada pregunta que se le hace, de qué empresa, cuántos tokens usó y cuánto costó, para
 * que el panel muestre el gasto del mes y nunca se pase del tope. Por empresa: si lo tiene activado y su límite mensual.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('asistente_uso')) {
            Schema::connection('central')->create('asistente_uso', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->nullable();
                $t->string('base', 64);
                $t->unsignedInteger('IdUsuario');
                $t->string('usuario', 120)->nullable();
                $t->string('pregunta', 500);
                $t->string('modelo', 40);
                $t->unsignedInteger('tokens_entrada')->default(0);
                $t->unsignedInteger('tokens_salida')->default(0);
                $t->unsignedInteger('tokens_cache_lectura')->default(0);
                $t->unsignedInteger('tokens_cache_escritura')->default(0);
                $t->unsignedSmallInteger('consultas')->default(0);
                $t->decimal('costo_usd', 10, 5)->default(0);
                $t->string('error', 255)->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['cliente_id', 'created_at']);
                $t->index('created_at');
            });
        }
        if (Schema::connection('central')->hasTable('clientes') && ! Schema::connection('central')->hasColumn('clientes', 'asistente_activo')) {
            Schema::connection('central')->table('clientes', function (Blueprint $t) {
                $t->boolean('asistente_activo')->default(false);
                $t->unsignedInteger('asistente_limite')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('asistente_uso');
        if (Schema::connection('central')->hasColumn('clientes', 'asistente_activo')) {
            Schema::connection('central')->table('clientes', fn (Blueprint $t) => $t->dropColumn(['asistente_activo', 'asistente_limite']));
        }
    }
};
