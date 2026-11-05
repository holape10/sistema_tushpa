<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las operaciones delicadas (precios, stock, categorías, mesas) solo para quien tiene esa opción en su menú: un mozo o
 * cajero que escribe la dirección a mano no entra. El administrador siempre puede.
 */
class PermisoDelMenu
{
    /** Ruta (por nombre) => opciones del menú que la habilitan (basta una) */
    private const PERMISOS = [
        'productos.' => ['/productos', '/productos?tipo=4', '/productos?tipo=6'],
        'kardex.guardar_movimiento' => ['/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E'],
        'kardex.movimiento' => ['/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E'],
        'kardex.' => ['/kardex', '/kardex/stock', '/kardex/inventario', '/kardex/sunat', '/kardex/movimiento?tipo=I', '/kardex/movimiento?tipo=E'],
        'categorias.' => ['/categorias'],
        'mesas.' => ['/mesas', '/pisos'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();
        $ruta = (string) $request->route()?->getName();
        if ($usuario && $ruta !== '' && ! $usuario->esAdmin()) {
            foreach (self::PERMISOS as $prefijo => $opciones) {
                if ($ruta === $prefijo || (str_ends_with($prefijo, '.') && str_starts_with($ruta, $prefijo))) {
                    $permitido = collect($opciones)->contains(fn ($url) => $usuario->tieneModulo($url));
                    abort_unless($permitido, 403, 'No tienes permiso para esta sección.');
                    break;
                }
            }
        }

        return $next($request);
    }
}
