<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas juntas (grupos grandes, cumpleaños): una mesa puede quedar "unida" al pedido abierto de otra mesa.
 * Se ve ocupada y su consumo va en una sola cuenta; al cobrar o anular ese pedido se libera sola.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mesas') && ! Schema::hasColumn('mesas', 'unida_ped_id')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->unsignedBigInteger('unida_ped_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mesas', 'unida_ped_id')) {
            Schema::table('mesas', function (Blueprint $table) {
                $table->dropIndex(['unida_ped_id']);
                $table->dropColumn('unida_ped_id');
            });
        }
    }
};
