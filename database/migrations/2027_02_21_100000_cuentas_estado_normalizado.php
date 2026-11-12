<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas por cobrar y por pagar importadas del sistema antiguo: su estado venía en minúsculas ("pendiente") o con otro
 * nombre ("cancelado" = pagada), y así no salía el botón Cobrar/Pagar ni contaban como pagadas. El estado se vuelve a
 * sacar de los montos: sin saldo, PAGADO; con algo abonado, PARCIAL; si no, PENDIENTE (las anuladas siguen anuladas).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cuentas_cobrar', 'cuentas_pagar'] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            DB::statement("UPDATE {$tabla} SET estado_cob = CASE
                    WHEN UPPER(TRIM(estado_cob)) LIKE 'ANUL%' THEN 'ANULADO'
                    WHEN saldo <= 0.009 THEN 'PAGADO'
                    WHEN abono > 0 THEN 'PARCIAL'
                    ELSE 'PENDIENTE' END
                WHERE estado_cob IS NULL OR BINARY estado_cob NOT IN ('PENDIENTE', 'PARCIAL', 'PAGADO', 'ANULADO')");
        }
        foreach (['cuentas_cobrar_detalle' => 'est_cue_cob_det', 'cuentas_pagar_detalle' => 'est_cue_pag_det'] as $tabla => $campo) {
            if (Schema::hasTable($tabla)) {
                DB::statement("UPDATE {$tabla} SET {$campo} = UPPER(TRIM({$campo})) WHERE BINARY {$campo} <> UPPER(TRIM({$campo}))");
            }
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: el estado anterior era el texto del sistema antiguo
    }
};
