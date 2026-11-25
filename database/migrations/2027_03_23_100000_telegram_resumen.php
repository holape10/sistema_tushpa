<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El administrador que lo activa en el Panel del dueño recibe en su Telegram el resumen del día y lo que le deben sus clientes */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'telegram_resumen')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('telegram_resumen')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'telegram_resumen')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_resumen'));
        }
    }
};
