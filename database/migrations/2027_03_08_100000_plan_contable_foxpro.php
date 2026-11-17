<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan contable importado del sistema en FoxPro: su nivel (Tipcta: 1 elemento, 2 cuenta, 3 de movimiento) y los
 * amarres de cada cuenta (Cargo y Abono: a dónde va el destino, ej. 60111 → 20111 / 61111).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conta_plan') || Schema::hasColumn('conta_plan', 'tipcta')) {
            return;
        }
        Schema::table('conta_plan', function (Blueprint $t) {
            $t->unsignedTinyInteger('tipcta')->nullable();
            $t->string('amarre_cargo', 12)->nullable();
            $t->string('amarre_abono', 12)->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('conta_plan', 'tipcta')) {
            Schema::table('conta_plan', fn (Blueprint $t) => $t->dropColumn(['tipcta', 'amarre_cargo', 'amarre_abono']));
        }
    }
};
