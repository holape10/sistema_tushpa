<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El administrador decide si le llegan a su Telegram los pedidos de almacén (requerimientos) */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'telegram_pedidos')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('telegram_pedidos')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'telegram_pedidos')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_pedidos'));
        }
    }
};
