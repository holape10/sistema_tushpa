<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla de mesas más rápida: se consulta cada 10 s por zona (mesas.pis_id) y busca el pedido abierto de cada mesa
 * (pedidos.mes_id + ped_est), en una tabla de pedidos que crece todos los días.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tiene = fn (string $tabla, array $columnas) => collect(Schema::getIndexes($tabla))->contains(fn ($i) => $i['columns'] === $columnas);
        if (! $tiene('mesas', ['pis_id', 'id_empresa_negocio'])) {
            Schema::table('mesas', fn (Blueprint $t) => $t->index(['pis_id', 'id_empresa_negocio'], 'mesas_piso_index'));
        }
        if (! $tiene('pedidos', ['mes_id', 'ped_est'])) {
            Schema::table('pedidos', fn (Blueprint $t) => $t->index(['mes_id', 'ped_est'], 'pedidos_mesa_estado_index'));
        }
    }

    public function down(): void
    {
        Schema::table('mesas', fn (Blueprint $t) => $t->dropIndex('mesas_piso_index'));
        Schema::table('pedidos', fn (Blueprint $t) => $t->dropIndex('pedidos_mesa_estado_index'));
    }
};
