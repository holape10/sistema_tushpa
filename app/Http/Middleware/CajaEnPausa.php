<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PausasCaja;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caja en pausa: mientras el cajero no ponga su clave, solo se ve la pantalla de la caja (con el mensaje grande); las
 * demás pantallas y acciones llevan de vuelta ahí. Así nadie usa la caja ni ve sus datos mientras el cajero no está.
 */
class CajaEnPausa
{
    /** Lo único que se puede abrir con la caja en pausa */
    private const PERMITIDAS = ['pv.index', 'pv.farmacia', 'pv.pausa.reanudar', 'logout', 'soporte.salir'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user instanceof User || ! $request->hasSession() || ! $request->session()->has(PausasCaja::SESION)
            || in_array($request->route()?->getName(), self::PERMITIDAS, true)) {
            return $next($request);
        }
        // Si la pausa ya se quitó (por ejemplo, desde otra pestaña), sigue normal
        if (! PausasCaja::abierta($user)) {
            $request->session()->forget(PausasCaja::SESION);

            return $next($request);
        }
        $mensaje = 'La caja está en pausa: pon tu clave para volver.';

        return $request->expectsJson() ? response()->json(['message' => $mensaje, 'mensaje' => $mensaje], 423) : redirect()->route('pv.index');
    }
}
