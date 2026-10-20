<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impresoras por equipo (PC de caja): cada PC con agente tiene su ticketera de comprobantes y su barra. El navegador
 * recuerda en qué PC está (cookie) y lo que se cobra ahí sale en sus impresoras, sin importar qué usuario cobra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresion_agentes', function (Blueprint $t) {
            if (! Schema::hasColumn('impresion_agentes', 'impresora_caja')) {
                $t->unsignedInteger('impresora_caja')->nullable();
            }
            if (! Schema::hasColumn('impresion_agentes', 'impresora_barra')) {
                $t->unsignedInteger('impresora_barra')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('impresion_agentes', fn (Blueprint $t) => $t->dropColumn(['impresora_caja', 'impresora_barra']));
    }
};
