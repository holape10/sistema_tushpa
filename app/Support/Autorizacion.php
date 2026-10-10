<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Autorización de un Administrador para acciones delicadas (quitar un consumo, etc.).
 * Si el que lo hace ya es Administrador, basta; si no, debe escribir el usuario (correo) y la clave de un Administrador
 * de su sucursal. Con límite de intentos para que no se pueda adivinar la clave.
 */
class Autorizacion
{
    /**
     * @return array{ok: bool, mensaje?: string, autorizado_por?: int}
     */
    public static function administrador(Request $request, string $accion): array
    {
        $yo = Auth::user();
        if ($yo->esAdmin()) {
            return ['ok' => true, 'autorizado_por' => (int) $yo->IdUsuario];
        }
        $llave = 'autoriza-'.$accion.':'.$yo->IdUsuario;
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            return ['ok' => false, 'mensaje' => 'Demasiados intentos. Espera '.RateLimiter::availableIn($llave).' segundos.'];
        }
        $admin = User::where('email', (string) $request->input('auth_user'))->where('id_empresa_negocio', $yo->id_empresa_negocio)->first();
        if (! $admin || ! Hash::check((string) $request->input('auth_password'), $admin->password)) {
            RateLimiter::hit($llave, 60);

            return ['ok' => false, 'mensaje' => 'Usuario o contraseña del administrador incorrectos.', 'pedir_clave' => true];
        }
        RateLimiter::clear($llave);
        if (! $admin->esAdmin()) {
            return ['ok' => false, 'mensaje' => 'Ese usuario no es Administrador.', 'pedir_clave' => true];
        }

        return ['ok' => true, 'autorizado_por' => (int) $admin->IdUsuario];
    }
}
