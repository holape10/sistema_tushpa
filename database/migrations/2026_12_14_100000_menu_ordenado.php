<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menú más ordenado: "Principal" quedó largo con todo lo del restaurante. Ahora:
 *  - Principal: inicio, paneles y las pantallas de venta (puntos de venta, comandas, caja, hotel, socios).
 *  - Restaurante: lo del restaurante (promotores, cupones, metas, encuestas, kiosko...) y sus reportes en "Reportes"
 *    (el menú pone en ese submenú lo que empieza con "Reporte:").
 *  - Cuentas por cobrar y por pagar en un solo grupo "Cuentas"; "Listar Cajas" pasa a Ventas.
 * Se ubica por la URL del módulo (es igual en todas las bases).
 */
return new class extends Migration
{
    /** URL => [grupo, nombre (null = el mismo)] */
    private const CAMBIOS = [
        '/promotores' => ['Restaurante', null],
        '/reportes/descuentos-cortesias' => ['Restaurante', 'Reporte: Descuentos y Cortesías'],
        '/reportes/consumo-cajas' => ['Restaurante', 'Reporte: Consumo por Caja y Barra'],
        '/reportes/propinas' => ['Restaurante', 'Reporte: Propinas'],
        '/cupones' => ['Restaurante', null],
        '/metas-mozos' => ['Restaurante', null],
        '/encuestas' => ['Restaurante', null],
        '/autoservicio' => ['Restaurante', null],
        '/delivery/reporte' => ['Restaurante', 'Reporte: Delivery'],
        '/cuentas/cobrar' => ['Cuentas', 'Cuentas por Cobrar'],
        '/cuentas/cobrar/reporte' => ['Cuentas', 'Reporte: Cuentas por Cobrar'],
        '/cuentas/pagar' => ['Cuentas', 'Cuentas por Pagar'],
        '/cuentas/pagar/reporte' => ['Cuentas', 'Reporte: Cuentas por Pagar'],
        '/turnos/listado' => ['Ventas', 'Cajas cerradas (turnos)'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modulos')) {
            return;
        }
        foreach (self::CAMBIOS as $url => [$grupo, $nombre]) {
            DB::table('modulos')->where('mod_url', $url)->update(['mod_gen' => $grupo] + ($nombre ? ['mod_nom' => $nombre] : []));
        }
    }

    public function down(): void
    {
        $antes = [
            '/promotores' => ['Principal', 'Ventas de Promotores'], '/reportes/descuentos-cortesias' => ['Principal', 'Descuentos y Cortesías'],
            '/reportes/consumo-cajas' => ['Principal', 'Consumo por Caja y Barra'], '/reportes/propinas' => ['Principal', 'Propinas'],
            '/cupones' => ['Principal', 'Cupones de descuento'], '/metas-mozos' => ['Principal', 'Metas de mozos'],
            '/encuestas' => ['Principal', 'Encuestas a clientes'], '/autoservicio' => ['Principal', 'Kiosko de autoservicio'],
            '/delivery/reporte' => ['Restaurante', 'Reporte Delivery'],
            '/cuentas/cobrar' => ['Cuentas Cobrar', 'Cuentas Cobrar'], '/cuentas/cobrar/reporte' => ['Cuentas Cobrar', 'Reporte: Cuentas por Cobrar'],
            '/cuentas/pagar' => ['Cuentas Pagar', 'Cuentas Pagar'], '/cuentas/pagar/reporte' => ['Cuentas Pagar', 'Reporte: Cuentas por Pagar'],
            '/turnos/listado' => ['Control Caja', 'Listar Cajas'],
        ];
        foreach ($antes as $url => [$grupo, $nombre]) {
            DB::table('modulos')->where('mod_url', $url)->update(['mod_gen' => $grupo, 'mod_nom' => $nombre]);
        }
    }
};
