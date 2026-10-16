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
        '/inicio', '/dashboard', '/turnos', '/turnos/listado', '/ventas', '/notas', '/sunat/envios', '/sunat/resumenes',
        '/reportes/ventas', '/reportes/sunat', '/reportes/ventas-cliente', '/reportes/ventas-producto', '/reportes/productos-ranking',
        '/clientes', '/proveedores', '/usuarios', '/empresas', '/sucursales', '/mediospagos', '/categorias', '/productos',
        '/impresoras', '/compras', '/gastos', '/almacenes', '/kardex', '/kardex/stock', '/kardex/inventario', '/cuentas/cobrar', '/cuentas/pagar', '/soporte',
        '/respaldo',
    ];

    public const RUBROS = [
        'RESTOBAR' => ['icono' => 'fa-utensils', 'nombre' => 'Restobar', 'ejemplos' => 'Restaurante, cafetería, discoteca, bar', 'modulos' => [
            '/comandas', '/comandas/punto-venta', '/promotores', '/pv', '/punto-venta', '/pisos', '/mesas', '/cocina', '/reservas', '/motorizados', '/delivery', '/delivery/liquidacion', '/delivery/reporte', '/carta/configuracion', '/recetas', '/entradas', '/preparados', '/mermas', '/reportes/consumo-insumos', '/productos?tipo=4', '/productos?tipo=6',
            '/reportes/ventas-vendedor', '/reportes/ventas-delivery',
        ]],
        'GENERAL' => ['icono' => 'fa-cart-shopping', 'nombre' => 'Comercio general', 'ejemplos' => 'Comercio, ferretería, construcción, lavandería', 'modulos' => [
            '/punto-venta', '/pv-movil', '/proformas', '/guias', '/inventarios', '/transferencias', '/kardex/movimiento?tipo=I',
            '/kardex/movimiento?tipo=E', '/productos?tipo=6', '/reportes/rentabilidad', '/reportes/compras', '/cuentas/cobrar/reporte',
            '/series',
        ]],
        'FARMACIA' => ['icono' => 'fa-briefcase-medical', 'nombre' => 'Farmacia / botica', 'ejemplos' => 'Farmacia, botica (PV Farmacia con lotes y vencimientos)', 'modulos' => [
            '/pv-farmacia', '/lotes', '/inventarios', '/transferencias', '/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E',
            '/proformas', '/reportes/rentabilidad', '/reportes/compras',
        ]],
        'GRIFO' => ['icono' => 'fa-gas-pump', 'nombre' => 'Grifo', 'ejemplos' => 'Grifo, estación de servicio (PV Grifo)', 'modulos' => [
            '/pv-grifo', '/punto-venta', '/inventarios', '/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E', '/cuentas/cobrar/reporte', '/guias',
        ]],
        'HOTELERIA' => ['icono' => 'fa-bed', 'nombre' => 'Hotelería', 'ejemplos' => 'Hotel, hospedaje con restaurante', 'modulos' => [
            '/hotel', '/hotel/reporte', '/reservas', '/comandas', '/comandas/punto-venta', '/promotores', '/pv', '/punto-venta', '/pisos', '/mesas', '/cocina', '/productos?tipo=4',
        ]],
        'GIMNASIO' => ['icono' => 'fa-dumbbell', 'nombre' => 'Gimnasio', 'ejemplos' => 'Gimnasio, academia (membresías y control de ingreso)', 'modulos' => [
            '/gimnasio', '/gimnasio/acceso', '/gimnasio/entrenador', '/gimnasio/reporte', '/pv',
        ]],
        'ESTACIONAMIENTO' => ['icono' => 'fa-square-parking', 'nombre' => 'Estacionamiento', 'ejemplos' => 'Playa de estacionamiento, valet parking, cochera', 'modulos' => [
            '/estacionamiento', '/estacionamiento/abonados', '/estacionamiento/reporte',
        ]],
        'CLUB' => ['icono' => 'fa-people-group', 'nombre' => 'Club / asociación', 'ejemplos' => 'Club, asociación (socios y cuotas)', 'modulos' => [
            '/socios', '/punto-venta', '/comandas', '/pv',
        ]],
        'CLINICA' => ['icono' => 'fa-stethoscope', 'nombre' => 'Clínica / consultorio', 'ejemplos' => 'Clínica, consultorio, odontología', 'modulos' => [
            '/clinica', '/clinica/agenda', '/clinica/especialidades', '/clinica/tratamientos', '/punto-venta',
        ]],
        'COMPLETO' => ['icono' => 'fa-table-cells', 'nombre' => 'Todo el menú', 'ejemplos' => 'Todas las opciones del sistema', 'modulos' => ['*']],
    ];

    /**
     * Qué opciones del sistema tienen sentido en cada rubro (para no mostrarle a una farmacia "Preparado" o "Es combustible").
     * Lo que no está aquí lo usan todos. COMPLETO o sin rubro = todo.
     */
    private const FUNCIONES = [
        'preparados' => ['RESTOBAR', 'HOTELERIA', 'CLUB', 'GENERAL'],
        'insumos' => ['RESTOBAR', 'HOTELERIA', 'CLUB', 'GENERAL'],
        'combustible' => ['GRIFO'],
        'lotes' => ['FARMACIA', 'GENERAL', 'CLINICA'],
        'series' => ['GENERAL'],
        'restaurante' => ['RESTOBAR', 'HOTELERIA', 'CLUB'],
    ];

    /** Clave de sesión con el rubro elegido en el login (empresa demo sin rubro) */
    public const SESION = 'rubro_elegido';

    /** ¿El login pide primero el tipo de negocio? Solo en la empresa del subdominio que no tiene rubro (ej. la demo) */
    public static function eligeEnLogin(): bool
    {
        $cliente = Tenancy::cliente();

        return $cliente !== null && blank($cliente->rubro);
    }

    /** Rubro de la empresa (o el elegido en el login de la demo); null = sin rubro, se ve todo */
    public static function actual(): ?string
    {
        $cliente = Tenancy::cliente();
        $rubro = $cliente === null ? null : ($cliente->rubro ?: session(self::SESION));

        return $rubro && isset(self::RUBROS[$rubro]) && $rubro !== 'COMPLETO' ? $rubro : null;
    }

    /** ¿Esta opción aplica al rubro de la empresa? (preparados, insumos, combustible, lotes, series, restaurante) */
    public static function permite(string $funcion): bool
    {
        $rubro = self::actual();

        return $rubro === null || ! isset(self::FUNCIONES[$funcion]) || in_array($rubro, self::FUNCIONES[$funcion], true);
    }

    /** @return array<int, int> Tipos de producto (promocion) que se pueden crear: 0 Producto, 4 Insumo, 2 Preparado, 6 Combo */
    public static function tiposProducto(): array
    {
        return array_values(array_filter([0, 4, 2, 6], fn ($t) => match ($t) {
            4 => self::permite('insumos'),
            2 => self::permite('preparados'),
            default => true,
        }));
    }

    /** Deja solo las opciones del rubro elegido en el login; sin elección se ve todo el menú del usuario */
    public static function filtrarMenu(Collection $modulos): Collection
    {
        $rubro = session(self::SESION);
        if (! $rubro || ! isset(self::RUBROS[$rubro]) || ! self::eligeEnLogin()) {
            return $modulos;
        }
        $urls = self::urls($rubro);

        return $urls === ['*'] ? $modulos : $modulos->filter(fn ($m) => in_array($m->mod_url, $urls, true))->values();
    }

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
