<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si el dueño desactiva a un usuario del panel, lo saca aunque tenga la sesión abierta */
class PanelActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('superadmin')->user();
        if ($usuario && ! $usuario->activo) {
            Auth::guard('superadmin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => 'Tu usuario del panel está desactivado.']);
        }

        return $next($request);
    }
}
