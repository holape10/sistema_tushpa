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

    /** Contabilidad completa: libros, registro de ventas y compras, SIRE, tributos, planilla, asistencia y CONCAR */
    private const CONTABLE = [
        '/contabilidad/plan', '/contabilidad/registro/ventas', '/contabilidad/registro/compras', '/contabilidad/centralizar/ventas',
        '/contabilidad/centralizar/compras', '/contabilidad/diario', '/contabilidad/mayor', '/contabilidad/balance', '/contabilidad/estados', '/concar',
    ];

    /**
     * Módulos que se le pueden agregar a cualquier empresa desde el panel (si quiere ampliar su sistema).
     * Se suman al menú de sus administradores sin quitarle nada.
     */
    public const ADICIONALES = [
        'CONTABILIDAD' => ['icono' => 'fa-calculator', 'nombre' => 'Contabilidad', 'detalle' => 'Plan contable, registro de ventas y compras, libros diario y mayor, balance, estados financieros y CONCAR',
            'modulos' => self::CONTABLE],
        'SIRE' => ['icono' => 'fa-landmark', 'nombre' => 'SIRE y tributos', 'detalle' => 'Propuestas RVIE / RCE de SUNAT y resumen tributario del mes',
            'modulos' => ['/sire/ventas', '/sire/compras', '/sire/credenciales', '/tributos']],
        'PLANILLA' => ['icono' => 'fa-file-signature', 'nombre' => 'Planilla', 'detalle' => 'Trabajadores, planillas y boletas de pago',
            'modulos' => ['/planilla/trabajadores', '/planilla', '/planilla/parametros']],
        'ASISTENCIA' => ['icono' => 'fa-user-clock', 'nombre' => 'Asistencia', 'detalle' => 'Marcación, turnos, tareo y reporte de jornadas',
            'modulos' => ['/asistencia', '/asistencia/motivos', '/asistencia/tareo', '/asistencia/jornadas', '/asistencia/matriz', '/asistencia/turnos', '/asistencia/configuracion']],
        'TIENDA' => ['icono' => 'fa-bag-shopping', 'nombre' => 'Tienda virtual', 'detalle' => 'Catálogo en línea con pedidos (según su plan)',
            'modulos' => ['/tienda/configuracion']],
        'FIDELIZACION' => ['icono' => 'fa-gift', 'nombre' => 'Puntos y premios', 'detalle' => 'Fidelización de clientes con puntos',
            'modulos' => ['/fidelizacion']],
        'GUIAS' => ['icono' => 'fa-truck', 'nombre' => 'Guías de remisión', 'detalle' => 'Guías electrónicas SUNAT',
            'modulos' => ['/guias']],
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
            '/clinica', '/clinica/agenda', '/clinica/especialidades', '/clinica/tratamientos', '/clinica/reportes', '/punto-venta',
        ]],
        // Estudio contable o empresa que solo lleva su contabilidad (sin vender con el sistema)
        'CONTABLE' => ['icono' => 'fa-calculator', 'nombre' => 'Contabilidad', 'ejemplos' => 'Estudio contable, contabilidad de la empresa, SIRE, planilla', 'sin_base' => true, 'modulos' => [
            '/inicio', '/usuarios', '/empresas', '/sucursales', '/clientes', '/proveedores', '/soporte', '/respaldo',
            ...self::CONTABLE,
            '/sire/ventas', '/sire/compras', '/sire/credenciales', '/tributos',
            '/planilla/trabajadores', '/planilla', '/planilla/parametros',
            '/asistencia', '/asistencia/motivos', '/asistencia/tareo', '/asistencia/jornadas', '/asistencia/matriz', '/asistencia/turnos', '/asistencia/configuracion',
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
        'doctores' => ['CLINICA'],
        'entrenadores' => ['GIMNASIO'],
    ];

    /** Rol => opción que lo habilita: mozo, promotor y motorizado son de restaurante; doctor de clínica; entrenador de gimnasio */
    private const ROLES = [8 => 'restaurante', 13 => 'restaurante', 12 => 'restaurante', 10 => 'doctores', 11 => 'entrenadores'];

    /** ¿Este rol tiene sentido en el rubro de la empresa? (administrador y cajero, siempre) */
    public static function permiteRol(int $rol): bool
    {
        return ! isset(self::ROLES[$rol]) || self::permite(self::ROLES[$rol]);
    }

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
        if ($r['modulos'] === ['*']) {
            return ['*'];
        }

        // El rubro contable no lleva ventas, caja ni almacén: solo su propio menú
        return array_values(array_unique(empty($r['sin_base']) ? array_merge(self::BASE, $r['modulos']) : $r['modulos']));
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
