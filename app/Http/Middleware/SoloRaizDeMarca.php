<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas que solo existen en el dominio de una marca (wilcatsystems.pe/contrato/{enlace}); IdentificarEmpresa lo
 * marca. En cualquier otra dirección (una empresa, tushpa.app) no existen.
 */
class SoloRaizDeMarca
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->attributes->get('marca_raiz'), 404);

        return $next($request);
    }
}
