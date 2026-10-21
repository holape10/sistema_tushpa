<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mozos y promotores solo usan el sistema conectados al Wi-Fi del local: IP(s) públicas permitidas de la sucursal.
 * Vacío = sin restricción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('empresa_negocios', 'ip_mozos')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('ip_mozos', 255)->nullable());
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn('ip_mozos'));
    }
};
