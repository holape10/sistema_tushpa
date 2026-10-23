<?php

use App\Support\Unidades;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades de medida al día con el catálogo 03 de SUNAT: el par pasa de NPR (código obsoleto) a PR,
 * el servicio (ZZ) sale como "SER" en el ticket (tenía "YRD", que es yarda) y se agregan las unidades que falten.
 * Las de envases o inventadas (Caja, Taper…) se quedan: en el XML van como NIU (ver Unidades::sunat()).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unidad_medida')) {
            return;
        }
        DB::table('unidad_medida')->where('umecod', 'ZZ')->where('umecin', 'YRD')->update(['umecin' => 'SER']);

        $npr = DB::table('unidad_medida')->where('umecod', 'NPR')->first();
        if ($npr) {
            // Primero existe PR, luego se mueven los productos y al final se quita NPR (por si hay llaves foráneas)
            if (! DB::table('unidad_medida')->where('umecod', 'PR')->exists()) {
                DB::table('unidad_medida')->insert(collect((array) $npr)->except('ume_id')->merge(['umecod' => 'PR'])->all());
            }
            // Los comprobantes ya emitidos se quedan como están; los productos y sus datos pasan al código nuevo
            foreach (['productos' => ['umecod', 'ume_equivalente'], 'producto_presentacion' => ['umecod'], 'producto_receta' => ['umecod'],
                'mermas' => ['umecod'], 'proforma_detalle' => ['umecod']] as $tabla => $columnas) {
                foreach ($columnas as $columna) {
                    if (Schema::hasColumn($tabla, $columna)) {
                        DB::table($tabla)->where($columna, 'NPR')->update([$columna => 'PR']);
                    }
                }
            }
            DB::table('unidad_medida')->where('umecod', 'NPR')->delete();
        }

        Unidades::completar();
    }

    public function down(): void
    {
        //
    }
};
