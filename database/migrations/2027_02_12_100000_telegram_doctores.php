<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** El chat de Telegram de cada usuario (doctor): por ahí le llegan gratis sus citas */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'telegram_chat_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('telegram_chat_id', 30)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'telegram_chat_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_chat_id'));
        }
    }
};
