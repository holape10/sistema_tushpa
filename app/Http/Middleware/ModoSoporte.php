<?php

namespace App\Http\Middleware;

use App\Support\Bitacora;
use App\Support\Tenancy\ModoSoporte as Soporte;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Durante una visita de soporte ("Ver como el cliente"): muestra la franja de arriba, la termina pasada la hora y, en
 * modo "solo para mirar", no deja guardar nada (solo se puede mirar y salir).
 */
class ModoSoporte
{
    /** Lo único que se puede enviar en modo "mirar": salir del modo soporte */
    private const PERMITIDAS = ['soporte.salir', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $visita = Soporte::actual();
        if (! $visita) {
            return $next($request);
        }
        if (! Auth::guard('web')->check() || $visita['hasta'] < now()->timestamp) {
            if (Auth::guard('web')->check()) {
                Bitacora::registrar('sesion.soporte_salio', "Terminó la visita de soporte ({$visita['nombre']}): pasó la hora", null,
                    ['usuario' => null, 'nombre' => 'Soporte: '.$visita['nombre']]);
                Auth::guard('web')->logout();
            }
            Soporte::terminar((int) $visita['id']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() ? response()->json(['message' => 'La visita de soporte terminó.'], 401)
                : redirect('/login')->withErrors(['email' => 'La visita de soporte terminó (dura una hora). Genera otra desde el panel.']);
        }
        if ($visita['modo'] === 'mirar' && ! $request->isMethodSafe() && ! in_array($request->route()?->getName(), self::PERMITIDAS, true)) {
            $mensaje = 'Modo soporte: estás solo para mirar, no se guardó nada. Para hacer cambios, entra desde el panel con «Puede hacer cambios».';

            return $request->expectsJson() ? response()->json(['message' => $mensaje, 'mensaje' => $mensaje, 'ok' => false], 403)
                : back()->withErrors(['soporte' => $mensaje]);
        }
        View::share('modoSoporte', $visita);

        return $next($request);
    }
}
