<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rendimiento con los años:
 *  - Índices para las búsquedas de todos los días (panel de ventas por sucursal y fecha, caja por turno,
 *    reportes por producto y cliente, mesas abiertas). Es lo que de verdad mantiene rápido el sistema.
 *  - Se quitan las únicas columnas que no usa ninguna parte del sistema (vacías en todas las bases).
 *    Las demás columnas de cpe_cabecera, cpe_detalle, pedidos y pedidos_detalle sí se usan.
 */
return new class extends Migration
{
    /** @var array<string, array<string, array<int, string>>> tabla => [nombre del índice => columnas] */
    private const INDICES = [
        'cpe_cabecera' => [
            'cpe_suc_fecha_index' => ['id_empresa_negocio', 'ccafem'],
            'cpe_turno_index' => ['id_turno'],
            'cpe_clicod_index' => ['clicod'],
            'cpe_ccandi_index' => ['ccandi'],
        ],
        'cpe_detalle' => ['cpe_det_producto_index' => ['IdProducto']],
        'pedidos' => ['pedidos_suc_estado_index' => ['id_empresa_negocio', 'ped_est'], 'pedidos_mesa_index' => ['mes_id']],
        'pedidos_detalle' => ['pedidos_det_producto_index' => ['IdProducto']],
        'venta_medio_pago' => ['vmp_turno_index' => ['id_turno']],
    ];

    private const SIN_USO = [
        'pedidos' => ['icbper_val', 'icbper_tot'],
        'pedidos_detalle' => ['icbper_ind', 'fecha_hora_despacho'],
    ];

    private function tieneIndice(string $tabla, string $nombre): bool
    {
        return DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tabla)->where('index_name', $nombre)->exists();
    }

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            foreach ($indices as $nombre => $columnas) {
                if (Schema::hasTable($tabla) && Schema::hasColumns($tabla, $columnas) && ! $this->tieneIndice($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
                }
            }
        }
        foreach (self::SIN_USO as $tabla => $columnas) {
            $existen = array_values(array_filter($columnas, fn ($c) => Schema::hasColumn($tabla, $c)));
            if ($existen) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn($existen));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            foreach (array_keys($indices) as $nombre) {
                if ($this->tieneIndice($tabla, $nombre)) {
                    Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($nombre));
                }
            }
        }
        Schema::table('pedidos', function (Blueprint $t) {
            $t->decimal('icbper_val', 10, 2)->nullable();
            $t->decimal('icbper_tot', 10, 2)->nullable();
        });
        Schema::table('pedidos_detalle', function (Blueprint $t) {
            $t->tinyInteger('icbper_ind')->nullable();
            $t->dateTime('fecha_hora_despacho')->nullable();
        });
    }
};
