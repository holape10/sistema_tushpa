<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preguntas al Asistente IA por Telegram (base central: el bot es uno para todas las empresas). El bot las anota al
 * leerlas y la tarea telegram:responder las contesta con los datos de la empresa de quien pregunta.
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        if (! Schema::connection('central')->hasTable('telegram_preguntas')) {
            Schema::connection('central')->create('telegram_preguntas', function (Blueprint $t) {
                $t->id();
                $t->string('chat_id', 30)->index();
                $t->string('texto', 1000);
                $t->timestamp('respondida_at')->nullable()->index();
                $t->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('telegram_preguntas');
    }
};
