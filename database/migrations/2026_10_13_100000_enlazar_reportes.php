<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Reportes de ventas y compras (con descarga en Excel y PDF)
return new class extends Migration {
    private array $urls = [
        ['Ventas', 'Reporte: Ventas', '/reportes/ventas'],
        ['Ventas', 'Reporte: Ventas por Vendedor', '/reportes/ventas-vendedor'],
        ['Ventas', 'Reporte: Ventas Delivery', '/reportes/ventas-delivery'],
        ['Ventas', 'Reporte: Ventas por Cliente', '/reportes/ventas-cliente'],
        ['Ventas', 'Reporte: Ventas por Producto', '/reportes/ventas-producto'],
        ['Ventas', 'Reporte: Productos (+/-) Vendidos', '/reportes/productos-ranking'],
        ['Ventas', 'Reporte: Rentabilidad', '/reportes/rentabilidad'],
        ['Compras', 'Reporte: Compras', '/reportes/compras'],
        ['Compras', 'Reporte: Compras por Proveedor', '/reportes/compras-proveedor'],
        ['Compras', 'Reporte: Compras por Productos', '/reportes/compras-producto'],
    ];

    public function up(): void
    {
        foreach ($this->urls as [$gen, $nom, $url]) {
            DB::table('modulos')->where('mod_gen', $gen)->where('mod_nom', $nom)->update(['mod_url' => $url]);
        }
    }

    public function down(): void
    {
        foreach ($this->urls as [$gen, $nom]) {
            DB::table('modulos')->where('mod_gen', $gen)->where('mod_nom', $nom)->update(['mod_url' => '#']);
        }
    }
};
