<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Negocios con varios socios: el resumen del día va a varios WhatsApp (separados por coma, hasta 5) */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('empresa_negocios', 'whatsapp_dueno')) {
            Schema::table('empresa_negocios', fn (Blueprint $t) => $t->string('whatsapp_dueno', 120)->nullable()->change());
        }
    }

    public function down(): void
    {
        // Se deja el tamaño: achicarlo cortaría los números guardados
    }
};
