<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda del panel: cada usuario tiene la suya (personal) y hay una general por marca (los de TUSHPA ven la de TUSHPA;
 * los de una marca, la de su marca). Cada evento avisa por Telegram las veces que se elijan antes (1 h, 1 día…).
 */
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        $esquema = Schema::connection('central');
        if (! $esquema->hasTable('agenda_eventos')) {
            $esquema->create('agenda_eventos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('superadmin_id');
                // De qué marca es la agenda general (null = TUSHPA)
                $t->unsignedBigInteger('marca_id')->nullable();
                $t->boolean('general')->default(false);
                $t->string('titulo', 150);
                $t->string('detalle', 1000)->nullable();
                $t->dateTime('inicio');
                $t->boolean('todo_el_dia')->default(false);
                $t->string('color', 10)->default('indigo');
                // Minutos antes en que avisa por Telegram: [60, 1440]
                $t->json('avisos')->nullable();
                $t->timestamps();
                $t->index(['inicio']);
                $t->index(['superadmin_id', 'inicio']);
                $t->index(['general', 'marca_id', 'inicio']);
            });
        }
        if (! $esquema->hasTable('agenda_avisos_enviados')) {
            $esquema->create('agenda_avisos_enviados', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('evento_id');
                $t->unsignedBigInteger('superadmin_id');
                $t->unsignedInteger('minutos');
                $t->timestamp('enviado_at')->useCurrent();
                $t->unique(['evento_id', 'superadmin_id', 'minutos'], 'agenda_aviso_unico');
            });
        }
        if ($esquema->hasTable('superadmins') && ! $esquema->hasColumn('superadmins', 'telegram_chat_id')) {
            $esquema->table('superadmins', fn (Blueprint $t) => $t->string('telegram_chat_id', 30)->nullable());
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('agenda_avisos_enviados');
        Schema::connection('central')->dropIfExists('agenda_eventos');
        if (Schema::connection('central')->hasColumn('superadmins', 'telegram_chat_id')) {
            Schema::connection('central')->table('superadmins', fn (Blueprint $t) => $t->dropColumn('telegram_chat_id'));
        }
    }
};
