<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CONCAR: asientos de cancelación (el cobro) además de los de provisión, como el sistema antiguo. La venta al contado
 * se cancela el mismo día y la venta al crédito cuando se cobra: Caja (Debe) contra la cuenta por cobrar (Haber),
 * en su propio subdiario y con su propio correlativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('concar_config') && ! Schema::hasColumn('concar_config', 'subdiario_cancelacion')) {
            Schema::table('concar_config', function (Blueprint $t) {
                $t->string('cancelacion', 1)->default('S');
                $t->string('subdiario_cancelacion', 4)->default('01');
                $t->string('cta_caja', 12)->default('101101');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('concar_config', 'subdiario_cancelacion')) {
            Schema::table('concar_config', fn (Blueprint $t) => $t->dropColumn(['cancelacion', 'subdiario_cancelacion', 'cta_caja']));
        }
    }
};
