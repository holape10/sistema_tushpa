<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barra de cada cajero (locales con varias barras y "comanda al cobrar"): al cobrar, la comanda sale en la ticketera de
 * caja del cajero (users.terminal) y en la de su barra (users.impresora_barra).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'impresora_barra')) {
                $t->unsignedInteger('impresora_barra')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('impresora_barra'));
    }
};
