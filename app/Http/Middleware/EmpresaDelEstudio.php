<?php

namespace App\Http\Middleware;

use App\Support\Estudio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Estudio contable: antes de cada pantalla revisa que el usuario esté en una empresa que puede ver y en una sucursal de
 * esa misma empresa. Si le quitaron el acceso o la empresa se desactivó, vuelve al estudio: nunca ve datos ajenos.
 */
class EmpresaDelEstudio
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Estudio::activo()) {
            $usuario = Auth::user();
            if (! Estudio::enOrden($usuario)) {
                Estudio::cambiar($usuario, Estudio::ruc());
            }
        }

        return $next($request);
    }
}
