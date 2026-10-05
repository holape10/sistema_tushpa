<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{EmpresaNegocio, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Cookie, DB, RateLimiter};

/**
 * Login rápido para tablets y celulares: se elige el mozo y se escribe su código móvil (users.codigo_movil).
 * El equipo recuerda su sucursal en una cookie (se guarda cuando alguien entra con usuario y contraseña).
 */
class LoginMovilController extends Controller
{
    private const COOKIE = 'tushpa_sucursal';

    public static function esMovil(Request $request): bool
    {
        return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|Tablet|Silk|Kindle|Opera Mini|IEMobile|BlackBerry/i',
            (string) $request->userAgent());
    }

    public static function recordarSucursal(User $user): void
    {
        Cookie::queue(self::COOKIE, (string) $user->id_empresa_negocio, 60 * 24 * 365);
    }

    private function sucursal(Request $request): ?EmpresaNegocio
    {
        $id = (int) $request->cookie(self::COOKIE);
        if ($id && ($s = EmpresaNegocio::find($id))) {
            return $s;
        }
        // Si en todo el sistema hay una sola sucursal, no hace falta configurar el equipo
        return EmpresaNegocio::count() === 1 ? EmpresaNegocio::first() : null;
    }

    /** Mozos activos con código móvil de la sucursal del equipo */
    private function mozos(EmpresaNegocio $sucursal)
    {
        return User::join('role_user as ru', 'ru.user_IdUsuario', '=', 'users.IdUsuario')
            ->where('ru.role_id', 8)
            ->where('users.id_empresa_negocio', $sucursal->id_empresa_negocio)
            ->where('users.estusu', 1)
            ->whereNotNull('users.codigo_movil')
            ->orderBy('users.apeusu')
            ->get(['users.IdUsuario', 'users.apeusu']);
    }

    public function create(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->rutaInicio());
        }
        // Quien vuelve al login móvil deja de preferir el de escritorio
        Cookie::queue(Cookie::forget('login_escritorio'));

        $sucursal = $this->sucursal($request);
        $mozos = $sucursal ? $this->mozos($sucursal) : collect();

        return view('auth.login_movil', compact('sucursal', 'mozos'));
    }

    public function store(Request $request)
    {
        $request->validate(['usuario' => 'required|integer', 'codigo' => 'required|digits_between:3,6']);

        $sucursal = $this->sucursal($request);
        abort_unless($sucursal, 403);

        // Máximo 5 intentos por minuto por mozo y equipo
        $llave = 'login-movil:' . $request->usuario . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            return back()->withErrors(['codigo' => 'Demasiados intentos. Espera ' . RateLimiter::availableIn($llave) . ' segundos.'])
                ->withInput(['usuario' => $request->usuario]);
        }

        $mozo = $this->mozos($sucursal)->firstWhere('IdUsuario', (int) $request->usuario);
        $user = $mozo ? User::find($mozo->IdUsuario) : null;

        if (!$user || !hash_equals((string) $user->codigo_movil, (string) $request->codigo)) {
            RateLimiter::hit($llave, 60);
            return back()->withErrors(['codigo' => 'Código incorrecto.'])->withInput(['usuario' => $request->usuario]);
        }

        RateLimiter::clear($llave);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->rutaInicio());
    }
}
