<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones por sucursal:
 *  - comanda_al_cobrar: las comandas no se imprimen al enviarlas, sino al cobrar (se guardan en pedidos.comanda_pendiente)
 *  - exigir_operacion: al cobrar con un medio que no es EFECTIVO se pide el número de operación (venta_medio_pago.num_operacion)
 * Ambas quedan activas para el RUC 20616065034. Y la unidad de medida ONZA (código SUNAT ONZ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_negocios', function (Blueprint $t) {
            if (! Schema::hasColumn('empresa_negocios', 'comanda_al_cobrar')) {
                $t->boolean('comanda_al_cobrar')->default(false);
                $t->boolean('exigir_operacion')->default(false);
            }
        });
        Schema::table('pedidos', function (Blueprint $t) {
            if (! Schema::hasColumn('pedidos', 'comanda_pendiente')) {
                $t->text('comanda_pendiente')->nullable();
            }
        });
        Schema::table('venta_medio_pago', function (Blueprint $t) {
            if (! Schema::hasColumn('venta_medio_pago', 'num_operacion')) {
                $t->string('num_operacion', 30)->nullable();
            }
        });

        DB::table('empresa_negocios')->where('IdEmpresa', '20616065034')->update(['comanda_al_cobrar' => 1, 'exigir_operacion' => 1]);

        if (Schema::hasTable('unidad_medida') && ! DB::table('unidad_medida')->where('umecod', 'ONZ')->exists()) {
            DB::table('unidad_medida')->insert(['umecod' => 'ONZ', 'umenom' => 'Onza', 'umecin' => 'ONZ', 'umeest' => 'Activo']);
        }
    }

    public function down(): void
    {
        Schema::table('empresa_negocios', fn (Blueprint $t) => $t->dropColumn(['comanda_al_cobrar', 'exigir_operacion']));
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropColumn('comanda_pendiente'));
        Schema::table('venta_medio_pago', fn (Blueprint $t) => $t->dropColumn('num_operacion'));
        DB::table('unidad_medida')->where('umecod', 'ONZ')->delete();
    }
};
