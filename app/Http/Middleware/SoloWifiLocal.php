<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RedLocal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Si un mozo o promotor sale del Wi-Fi del local (usa sus datos móviles u otra red), se le cierra la sesión */
class SoloWifiLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();
        if (! $usuario instanceof User || RedLocal::permite($usuario, (string) $request->ip())) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        abort_if($request->expectsJson(), 403, RedLocal::mensaje());

        return redirect()->route('login.movil')->withErrors(['codigo' => RedLocal::mensaje()]);
    }
}
