<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram de los doctores (base central, porque el bot es uno para todas las empresas): cada enlace "Vincular
 * Telegram" lleva un código; cuando el doctor toca Iniciar en el bot, se anota su chat para mandarle sus citas gratis.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('telegram_vinculos')) {
            Schema::connection('central')->create('telegram_vinculos', function (Blueprint $t) {
                $t->id();
                $t->string('codigo', 24)->unique();
                $t->string('base', 64);
                $t->unsignedInteger('IdUsuario');
                $t->string('chat_id', 30)->nullable();
                $t->string('nombre', 120)->nullable();
                $t->timestamp('vinculado_at')->nullable();
                $t->timestamp('created_at')->useCurrent();
                $t->index(['base', 'IdUsuario']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('telegram_vinculos');
    }
};
