<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El administrador que lo activa en Encuestas recibe en su Telegram (el mismo bot de los pedidos) las calificaciones malas */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'telegram_encuestas')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('telegram_encuestas')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'telegram_encuestas')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_encuestas'));
        }
    }
};
