<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para lo que se consulta todo el tiempo:
 *  - La campana de SUNAT (cada minuto en cada pantalla abierta) cuenta los comprobantes pendientes de la sucursal.
 *  - Comandas y punto de venta listan los productos activos de la sucursal.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: array<int, string>}> */
    private array $indices = [
        'cpe_suc_estado_index' => ['cpe_cabecera', ['id_empresa_negocio', 'est_sunat']],
        'productos_suc_estado_index' => ['productos', ['id_empresa_negocio', 'proest']],
    ];

    public function up(): void
    {
        foreach ($this->indices as $nombre => [$tabla, $columnas]) {
            if (Schema::hasTable($tabla) && ! Schema::hasIndex($tabla, $nombre)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->index($columnas, $nombre));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indices as $nombre => [$tabla]) {
            if (Schema::hasTable($tabla) && Schema::hasIndex($tabla, $nombre)) {
                Schema::table($tabla, fn (Blueprint $t) => $t->dropIndex($nombre));
            }
        }
    }
};
