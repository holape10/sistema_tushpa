<?php

namespace App\Http\Middleware;

use App\Support\TiempoReal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuando alguien guarda algo en mesas, comandas, cobros, cocina, hotel o la caja sin internet, las demás pantallas
 * de su sucursal se enteran al instante (tiempo real). Solo después de guardar bien (respuesta sin error).
 */
class AvisarCambios
{
    private const RUTAS = ['comandas.', 'cobros.', 'cocina.', 'hotel.', 'offline.', 'delivery.', 'pv.', 'pos.', 'ventas.'];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        if (! $request->isMethodSafe() && $respuesta->getStatusCode() < 400 && Auth::check()) {
            $ruta = (string) $request->route()?->getName();
            $esPantalla = str_starts_with($request->path(), 'cocina/') || str_starts_with($request->path(), 'comandas/');
            if ($esPantalla || ($ruta !== '' && collect(self::RUTAS)->contains(fn ($p) => str_starts_with($ruta, $p)))) {
                TiempoReal::avisar((int) Auth::user()->id_empresa_negocio);
            }
        }

        return $respuesta;
    }
}
