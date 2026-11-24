<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Seguridad\Dispositivos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reconoce el equipo de cada usuario con sesión (Dispositivos): registra los nuevos (y avisa al dueño) y saca a los que
 * el administrador bloqueó ("no fui yo"). No hace nada en el panel ni con usuarios sin sesión.
 */
class ControlEquipo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User && $request->hasSession()) {
            try {
                $equipo = Dispositivos::controlar($request, $user);
            } catch (\Throwable $e) {
                report($e);
                $equipo = false;
            }
            if ($equipo === null) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $mensaje = 'Este equipo fue bloqueado por el administrador. Si es un error, pídele que lo desbloquee en Seguridad › Equipos y sesiones.';

                return $request->expectsJson() ? response()->json(['message' => $mensaje], 401)
                    : redirect('/login')->withErrors(['email' => $mensaje]);
            }
        }

        return $next($request);
    }
}
