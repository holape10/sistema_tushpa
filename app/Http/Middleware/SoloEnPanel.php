<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las pantallas del panel solo abren en un panel de verdad: admin.tushpa.app o admin.{dominio de una marca activa}
 * (IdentificarEmpresa lo marca). Cualquier otro admin.algo que apunte al servidor no existe. Además quita el dominio
 * de los parámetros de la ruta, para que no llegue a los controladores como si fuera un dato.
 */
class SoloEnPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('panel_admin'), 404);
        $request->route()?->forgetParameter('panelDominio');

        return $next($request);
    }
}
