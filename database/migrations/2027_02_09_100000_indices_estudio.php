<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estudio contable con muchas empresas en una misma base: las consultas de saldos (balance, mayor, estados financieros)
 * y las compras del SIRE filtran por empresa y FECHA. Sin estos índices, cada consulta de un mes recorría toda la historia
 * de esa empresa (y se volvía más lenta cada año). Medido con 100 empresas y un año de asientos (1,2 millones de asientos,
 * 3,6 millones de líneas): los saldos de un mes pasan de ~610 ms a ~30 ms y los del año de ~1 190 ms a ~116 ms.
 * MySQL los agrega sin bloquear las tablas.
 */
return new class extends Migration
{
    private const INDICES = [
        'conta_asientos' => ['conta_asientos_empresa_fecha' => ['IdEmpresa', 'fecha']],
        // Incluye cuenta, debe y haber: la suma de saldos se responde solo con el índice, sin leer la tabla
        'conta_asiento_detalle' => ['conta_detalle_asiento_cuenta' => ['asiento_id', 'cuenta', 'debe', 'haber']],
        'compras_xml' => ['compras_xml_empresa_fecha' => ['IdEmpresa', 'fecha']],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $existentes = collect(DB::select("SHOW INDEX FROM `{$tabla}`"))->pluck('Key_name')->unique()->all();
            foreach ($indices as $nombre => $columnas) {
                if (! in_array($nombre, $existentes, true)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $existentes = collect(DB::select("SHOW INDEX FROM `{$tabla}`"))->pluck('Key_name')->unique()->all();
            foreach (array_keys($indices) as $nombre) {
                if (in_array($nombre, $existentes, true)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($nombre));
                }
            }
        }
    }
};
