<?php

namespace App\Support;

use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de negocio: al crear una empresa desde el panel admin.tushpa.app se elige el rubro y su usuario administrador
 * arranca solo con el menú de ese rubro (luego el panel puede quitar o agregar opciones).
 * Los módulos se nombran por su URL, que es igual en todas las bases (los mod_id pueden variar).
 */
class Rubros
{
    /** Lo que usa cualquier negocio: ventas, caja, SUNAT, productos, compras y almacén */
    private const BASE = [
        '/dashboard', '/turnos', '/turnos/listado', '/ventas', '/notas', '/sunat/envios', '/sunat/resumenes',
        '/reportes/ventas', '/reportes/ventas-cliente', '/reportes/ventas-producto', '/reportes/productos-ranking',
        '/clientes', '/proveedores', '/usuarios', '/empresas', '/sucursales', '/mediospagos', '/categorias', '/productos',
        '/impresoras', '/compras', '/gastos', '/almacenes', '/kardex', '/kardex/stock', '/cuentas/cobrar', '/cuentas/pagar', '/soporte',
    ];

    public const RUBROS = [
        'RESTOBAR' => ['nombre' => 'Restobar', 'ejemplos' => 'Restaurante, cafetería, discoteca, bar', 'modulos' => [
            '/comandas', '/pv', '/punto-venta', '/pisos', '/mesas', '/cocina', '/reservas', '/motorizados', '/productos?tipo=4', '/productos?tipo=6',
            '/reportes/ventas-vendedor', '/reportes/ventas-delivery',
        ]],
        'GENERAL' => ['nombre' => 'Comercio general', 'ejemplos' => 'Comercio, ferretería, construcción, lavandería', 'modulos' => [
            '/punto-venta', '/pv-movil', '/proformas', '/guias', '/inventarios', '/transferencias', '/kardex/movimiento?tipo=I',
            '/kardex/movimiento?tipo=E', '/productos?tipo=6', '/reportes/rentabilidad', '/reportes/compras', '/cuentas/cobrar/reporte',
        ]],
        'FARMACIA' => ['nombre' => 'Farmacia / botica', 'ejemplos' => 'Farmacia, botica (PV Farmacia con lotes y vencimientos)', 'modulos' => [
            '/pv-farmacia', '/lotes', '/inventarios', '/transferencias', '/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E',
            '/proformas', '/reportes/rentabilidad', '/reportes/compras',
        ]],
        'GRIFO' => ['nombre' => 'Grifo', 'ejemplos' => 'Grifo, estación de servicio (PV Grifo)', 'modulos' => [
            '/pv-grifo', '/punto-venta', '/inventarios', '/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E', '/cuentas/cobrar/reporte', '/guias',
        ]],
        'HOTELERIA' => ['nombre' => 'Hotelería', 'ejemplos' => 'Hotel, hospedaje con restaurante', 'modulos' => [
            '/hotel', '/reservas', '/comandas', '/pv', '/punto-venta', '/pisos', '/mesas', '/cocina', '/productos?tipo=4',
        ]],
        'GIMNASIO' => ['nombre' => 'Gimnasio', 'ejemplos' => 'Gimnasio, academia (membresías y control de ingreso)', 'modulos' => [
            '/gimnasio', '/gimnasio/acceso', '/gimnasio/entrenador', '/pv',
        ]],
        'CLUB' => ['nombre' => 'Club / asociación', 'ejemplos' => 'Club, asociación (socios y cuotas)', 'modulos' => [
            '/socios', '/punto-venta', '/comandas', '/pv',
        ]],
        'CLINICA' => ['nombre' => 'Clínica / consultorio', 'ejemplos' => 'Clínica, consultorio, odontología', 'modulos' => [
            '/clinica', '/clinica/agenda', '/punto-venta',
        ]],
        'COMPLETO' => ['nombre' => 'Todo el menú', 'ejemplos' => 'Todas las opciones del sistema', 'modulos' => ['*']],
    ];

    /** @return array<int, string> URLs del menú de un rubro */
    public static function urls(string $rubro): array
    {
        $r = self::RUBROS[$rubro] ?? self::RUBROS['GENERAL'];

        return $r['modulos'] === ['*'] ? ['*'] : array_values(array_unique(array_merge(self::BASE, $r['modulos'])));
    }

    /**
     * Menú disponible agrupado como se ve en el sistema; sin las opciones "Pronto".
     * Si la base actual no tiene menú (el panel trabaja en la base central), se lee de la base del sistema.
     */
    public static function catalogo(): Collection
    {
        $base = config('tenancy.base_sistema');
        if (! Schema::hasTable('modulos') && $base && $base !== Tenancy::baseActual()) {
            return Tenancy::en($base, fn () => self::catalogo());
        }

        return DB::table('modulos')->whereNotNull('mod_url')->where('mod_url', '!=', '#')->where('mod_url', '!=', '')
            ->orderBy('mod_gen')->orderBy('mod_id')->get(['mod_id', 'mod_nom', 'mod_url', 'mod_gen'])->groupBy('mod_gen');
    }

    /** mod_id de la base actual para unas URLs ('*' = todo) */
    public static function ids(array $urls): array
    {
        return in_array('*', $urls, true)
            ? DB::table('modulos')->pluck('mod_id')->all()
            : DB::table('modulos')->whereIn('mod_url', $urls)->pluck('mod_id')->all();
    }
}
