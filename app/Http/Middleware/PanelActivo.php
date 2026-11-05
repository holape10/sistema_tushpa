<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el dueño desactiva a un usuario del panel, lo saca aunque tenga la sesión abierta. Lo mismo si el usuario no es
 * de la marca de este panel (cada marca entra solo por su admin.{dominio}).
 */
class PanelActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('superadmin')->user();
        $deOtraMarca = $usuario && (int) ($usuario->marca_id ?? 0) !== (int) ($request->attributes->get('panel_marca') ?? 0);
        if ($usuario && (! $usuario->activo || $deOtraMarca)) {
            Auth::guard('superadmin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => $deOtraMarca ? 'Ese usuario es de otro panel: entra por el tuyo.' : 'Tu usuario del panel está desactivado.']);
        }

        return $next($request);
    }
}
