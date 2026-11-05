<?php

namespace App\Http\Middleware;

use App\Models\User;
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
        $usuario = Auth::user();
        // Solo los usuarios del sistema (en el panel TUSHPA el que entra es un superadmin)
        if ($usuario instanceof User && Estudio::activo()) {
            if (! Estudio::enOrden($usuario)) {
                Estudio::cambiar($usuario, Estudio::ruc());
            }
            // Guardar desde una pantalla abierta para otra empresa (se cambió en otra pestaña): se rechaza
            $deLaPantalla = (string) ($request->header('X-Empresa') ?? $request->input('_empresa', ''));
            if (! $request->isMethodSafe() && $deLaPantalla !== '' && $deLaPantalla !== (string) $usuario->IdEmpresa
                && ! in_array($request->route()?->getName(), ['estudio.cambiar', 'logout'], true)) {
                $mensaje = 'Esta pantalla era de la empresa '.$deLaPantalla.', pero ahora trabajas en '.$usuario->IdEmpresa
                    .' (cambiaste en otra pestaña). No se guardó nada: recarga la página.';

                return $request->expectsJson()
                    ? response()->json(['ok' => false, 'message' => $mensaje, 'mensaje' => $mensaje], 409)
                    : redirect()->back()->with('error', $mensaje);
            }
        }

        return $next($request);
    }
}
