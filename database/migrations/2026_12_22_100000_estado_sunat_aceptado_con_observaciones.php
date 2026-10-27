<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobantes importados del sistema antiguo antes de que el importador traduzca su estado: quedaron con
 * "ACEPTADO CO" (aceptado con observaciones). En el sistema nuevo ese estado es OBSERVADO; con el texto antiguo no
 * contaban como aceptados (tablero "por revisar", notas de crédito deshabilitadas). Es lo mismo que hoy hace el importador.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cpe_cabecera')) {
            return;
        }
        DB::table('cpe_cabecera')->whereIn('tdocod', ['01', '03', '07', '08'])
            ->whereRaw("UPPER(TRIM(est_sunat)) LIKE 'ACEPTADO CO%'")
            ->update(['est_sunat' => 'OBSERVADO']);
    }

    public function down(): void
    {
        // No se vuelve al texto antiguo: OBSERVADO es el estado correcto
    }
};
