<?php

namespace App\Http\Controllers;

use App\Support\Notas;
use App\View\Composers\MenuComposer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pantalla de Inicio: un mosaico con los accesos del menú del usuario que ingresa (solo lo que tiene asignado).
 */
class InicioController extends Controller
{
    /** Ícono (Font Awesome) y color de cada módulo, por su URL */
    private const ACCESOS = [
        '/dashboard' => ['fa-chart-line', '#f59e0b'],
        '/punto-venta' => ['fa-cash-register', '#0891b2'],
        '/pv-movil' => ['fa-mobile-screen-button', '#6366f1'],
        '/comandas' => ['fa-utensils', '#16a34a'],
        '/turnos' => ['fa-money-bill-wave', '#2563eb'],
        '/turnos/listado' => ['fa-clock-rotate-left', '#14b8a6'],
        '/pv' => ['fa-rocket', '#dc2626'],
        '/pv-farmacia' => ['fa-prescription-bottle-medical', '#059669'],
        '/pv-grifo' => ['fa-gas-pump', '#ea580c'],
        '/hotel' => ['fa-hotel', '#7c3aed'],
        '/socios' => ['fa-id-card', '#db2777'],
        '/ventas' => ['fa-file-invoice-dollar', '#be185d'],
        '/notas' => ['fa-file-circle-minus', '#9f1239'],
        '/guias' => ['fa-truck-fast', '#0f766e'],
        '/proformas' => ['fa-file-signature', '#4f46e5'],
        '/ventas/masiva' => ['fa-layer-group', '#0891b2'],
        '/tienda/configuracion' => ['fa-store', '#64748b'],
        '/cuentas/cobrar' => ['fa-hand-holding-dollar', '#15803d'],
        '/cuentas/pagar' => ['fa-money-check-dollar', '#b91c1c'],
        '/compras' => ['fa-cart-shopping', '#c2410c'],
        '/gastos' => ['fa-receipt', '#a16207'],
        '/almacenes' => ['fa-warehouse', '#475569'],
        '/inventarios' => ['fa-clipboard-check', '#0369a1'],
        '/transferencias' => ['fa-right-left', '#7c3aed'],
        '/kardex' => ['fa-book', '#334155'],
        '/kardex/stock' => ['fa-boxes-stacked', '#ea580c'],
        '/kardex/movimiento' => ['fa-dolly', '#0d9488'],
        '/lotes' => ['fa-calendar-xmark', '#b45309'],
        '/productos' => ['fa-box-open', '#f97316'],
        '/categorias' => ['fa-table-list', '#7c3aed'],
        '/clientes' => ['fa-users', '#0284c7'],
        '/proveedores' => ['fa-truck-field', '#57534e'],
        '/usuarios' => ['fa-users-gear', '#3b82f6'],
        '/empresas' => ['fa-building', '#1e3a8a'],
        '/sucursales' => ['fa-shop', '#0e7490'],
        '/mediospagos' => ['fa-credit-card', '#9333ea'],
        '/tipo-cambio' => ['fa-dollar-sign', '#16a34a'],
        '/impresoras' => ['fa-print', '#374151'],
        '/importar-antiguo' => ['fa-file-import', '#64748b'],
        '/pisos' => ['fa-building-columns', '#a855f7'],
        '/mesas' => ['fa-chair', '#ca8a04'],
        '/cocina' => ['fa-fire-burner', '#dc2626'],
        '/reservas' => ['fa-calendar-check', '#0d9488'],
        '/motorizados' => ['fa-motorcycle', '#e11d48'],
        '/sunat/envios' => ['fa-cloud-arrow-up', '#1e3a8a'],
        '/sunat/resumenes' => ['fa-file-zipper', '#831843'],
        '/sire' => ['fa-landmark', '#1e40af'],
        '/tributos' => ['fa-scale-balanced', '#92400e'],
        '/fidelizacion' => ['fa-award', '#db2777'],
        '/gimnasio' => ['fa-dumbbell', '#ea580c'],
        '/gimnasio/acceso' => ['fa-door-open', '#16a34a'],
        '/gimnasio/entrenador' => ['fa-person-running', '#7c3aed'],
        '/clinica' => ['fa-stethoscope', '#059669'],
        '/clinica/agenda' => ['fa-calendar-days', '#0891b2'],
        '/asistencia' => ['fa-user-clock', '#52525b'],
        '/planilla' => ['fa-money-check', '#0f766e'],
        '/planilla/trabajadores' => ['fa-id-badge', '#2563eb'],
        '/contabilidad' => ['fa-scale-balanced', '#ea580c'],
        '/concar' => ['fa-file-export', '#475569'],
        '/soporte' => ['fa-headset', '#1f2937'],
    ];

    /** Ícono de reserva por grupo del menú, cuando el módulo no tiene uno propio */
    private const POR_GRUPO = [
        'Ventas' => 'fa-file-invoice-dollar', 'Compras' => 'fa-cart-shopping', 'Almacén' => 'fa-warehouse',
        'Mantenimiento' => 'fa-gear', 'Contactos' => 'fa-address-book', 'Asistencia' => 'fa-user-clock',
        'Planilla' => 'fa-money-check', 'Contabilidad' => 'fa-scale-balanced', 'SUNAT' => 'fa-cloud-arrow-up',
        'SIRE' => 'fa-landmark', 'Restaurante' => 'fa-utensils', 'Gimnasio' => 'fa-dumbbell', 'Clínica' => 'fa-stethoscope',
    ];

    private const COLORES = ['#2563eb', '#16a34a', '#dc2626', '#7c3aed', '#ea580c', '#0891b2', '#db2777', '#ca8a04', '#0f766e', '#4f46e5'];

    public function index(): View
    {
        $user = Auth::user();
        $modulos = $user->modulos()->orderBy('mod_id')->get()
            ->filter(fn ($m) => $m->mod_url && $m->mod_url !== '#' && $m->mod_url !== '/inicio');

        $esReporte = fn ($m) => str_starts_with($m->mod_nom, 'Reporte') || str_contains($m->mod_url, 'reporte');
        $grupos = $modulos->reject($esReporte)->groupBy('mod_gen')
            ->map(fn (Collection $mods) => $mods->values()->map(fn ($m, $i) => $this->acceso($m, $i)));
        // Lo principal primero, luego el resto en el orden del menú
        $grupos = $grupos->sortBy(fn ($v, $grupo) => $grupo === 'Principal' ? 0 : 1);
        $reportes = $modulos->filter($esReporte)->values()->map(fn ($m) => [
            'nombre' => trim(preg_replace('/^Reporte:?\s*/i', '', $m->mod_nom)), 'url' => url($m->mod_url),
        ]);

        return view('empresas.inicio', [
            'grupos' => $grupos,
            'reportes' => $reportes,
            'resumen' => $user->esAdminOCaja() ? $this->resumenDeHoy((int) $user->id_empresa_negocio) : null,
        ]);
    }

    /**
     * @return array{nombre: string, url: string, icono: string, color: string, grupo: string}
     */
    private function acceso(object $modulo, int $posicion): array
    {
        $ruta = '/'.trim(parse_url($modulo->mod_url, PHP_URL_PATH) ?? '', '/');
        $propio = self::ACCESOS[$ruta] ?? collect(self::ACCESOS)->first(fn ($v, $url) => str_starts_with($ruta, $url.'/'));

        return [
            'nombre' => $modulo->mod_nom,
            'url' => url($modulo->mod_url),
            'icono' => $propio[0] ?? self::POR_GRUPO[$modulo->mod_gen] ?? 'fa-circle-right',
            'color' => $propio[1] ?? self::COLORES[($modulo->mod_id + $posicion) % count(self::COLORES)],
            'grupo' => (string) $modulo->mod_gen,
        ];
    }

    /**
     * @return array{total: float, comprobantes: int, pendientes: int}
     */
    private function resumenDeHoy(int $sucursal): array
    {
        $hoy = DB::table('cpe_cabecera as c')->where('c.id_empresa_negocio', $sucursal)->whereNull('c.ccabaj')
            ->where('c.ccafem', now()->toDateString());

        return [
            'total' => (float) (clone $hoy)->sum(DB::raw(Notas::SIGNO_SQL.' * c.ccaitv')),
            'comprobantes' => (clone $hoy)->count(),
            'pendientes' => (int) MenuComposer::pendientesSunat(Auth::user())['total'],
        ];
    }
}
