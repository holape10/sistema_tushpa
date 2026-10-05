<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Cookie};
use Illuminate\View\View;

use App\Models\Empresa;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (Empresa::count() === 0) {
            return redirect()->route('empresa.config');
        }

        // Celulares y tablets van al login de mozos, salvo que elijan expresamente el de escritorio
        // (la preferencia se guarda en una cookie para que sobreviva al cerrar sesión)
        if ($request->boolean('escritorio')) {
            Cookie::queue('login_escritorio', '1', 60 * 24 * 30);
        } elseif (LoginMovilController::esMovil($request) && !$request->cookie('login_escritorio')) {
            return redirect()->route('login.movil');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Este equipo queda asociado a la sucursal: el login móvil mostrará a sus mozos
        LoginMovilController::recordarSucursal($request->user());

        return redirect()->intended(route($request->user()->rutaInicio(), absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
