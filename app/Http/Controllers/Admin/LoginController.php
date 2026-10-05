<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        if (Auth::guard('superadmin')->check()) {
            return redirect()->route('admin.clientes.index');
        }
        return view('admin.login');
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        // 5 intentos por correo + IP; luego hay que esperar
        $llave = 'admin-login:' . Str::lower($datos['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Espera ' . RateLimiter::availableIn($llave) . ' segundos.',
            ]);
        }

        if (!Auth::guard('superadmin')->attempt($datos)) {
            RateLimiter::hit($llave, 300);
            throw ValidationException::withMessages(['email' => 'Correo o contraseña incorrectos.']);
        }

        RateLimiter::clear($llave);
        $request->session()->regenerate();
        Auth::guard('superadmin')->user()->forceFill(['ultimo_acceso' => now(), 'ultima_ip' => $request->ip()])->save();

        return redirect()->intended(route('admin.clientes.index'));
    }

    public function destroy(Request $request)
    {
        Auth::guard('superadmin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
