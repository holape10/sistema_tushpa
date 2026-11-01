<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en todas las pantallas:
 *  - Ninguna otra web puede mostrar el sistema dentro de un marco (evita que engañen a un usuario para que haga clic
 *    en un botón escondido). La carta pública sí se puede mostrar en la web de un cliente.
 *  - Con https, el navegador recuerda entrar siempre por https (sin includeSubDomains: hay empresas aún sin certificado).
 *  - El navegador no adivina el tipo de los archivos y no manda la dirección completa a otras webs.
 */
class CabecerasSeguridad
{
    /** Rutas que otras webs pueden mostrar dentro de un marco */
    private const SE_PUEDEN_INCRUSTAR = ['carta.ver', 'sitio.carta.ver'];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $cabeceras = $respuesta->headers;

        $cabeceras->set('X-Content-Type-Options', 'nosniff', false);
        $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        if (! in_array($request->route()?->getName(), self::SE_PUEDEN_INCRUSTAR, true)) {
            $cabeceras->set('X-Frame-Options', 'SAMEORIGIN', false);
        }
        if ($request->isSecure()) {
            $cabeceras->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        return $respuesta;
    }
}
