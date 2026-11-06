<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * Inicios de sesión en la bitácora, desde los eventos de Laravel: cubre cualquier forma de entrar (escritorio, móvil,
 * sesión recordada, registro) sin tocar cada pantalla. Solo el sistema de las empresas (guard web); el panel del dueño
 * trabaja en la base central, que no tiene bitácora. Nunca se guarda la contraseña escrita.
 */
class BitacoraSesion
{
    public static function escuchar(): void
    {
        Event::listen(Login::class, function (Login $e) {
            if ($e->guard === 'web' && $e->user instanceof User) {
                Bitacora::registrar('sesion.ingreso', $e->remember && ! request()->isMethod('post') ? 'Volvió a entrar (sesión recordada)' : 'Inició sesión',
                    null, ['usuario' => $e->user, 'referencia' => ['usuario', $e->user->IdUsuario]]);
            }
        });

        Event::listen(Failed::class, function (Failed $e) {
            if ($e->guard !== 'web') {
                return;
            }
            $correo = mb_substr(trim((string) ($e->credentials['email'] ?? $e->credentials['codigo_movil'] ?? '')), 0, 120);
            $usuario = $e->user instanceof User ? $e->user : null;
            // A quién intentaron entrar (si el correo existe), pero sin atribuirle el intento
            Bitacora::registrar('sesion.fallido', $usuario ? "Contraseña incorrecta para {$correo}" : "Intento de ingreso con un usuario que no existe o está inactivo: {$correo}",
                null, ['usuario' => null, 'nombre' => null, 'sucursal' => $usuario?->id_empresa_negocio, 'empresa' => $usuario?->IdEmpresa,
                    'referencia' => $usuario ? ['usuario', $usuario->IdUsuario] : null, 'datos' => ['correo' => $correo]]);
        });

        Event::listen(Lockout::class, function (Lockout $e) {
            $correo = mb_substr(trim((string) $e->request->input('email', '')), 0, 120);
            // Mientras dura el bloqueo cada intento vuelve a avisar: basta un registro cada 5 minutos
            try {
                if (! Cache::add('bitacora_bloqueo:'.sha1($e->request->ip().'|'.$correo), 1, 300)) {
                    return;
                }
            } catch (\Throwable) {
            }
            Bitacora::registrar('sesion.bloqueo', "Ingreso bloqueado por muchos intentos fallidos: {$correo}", null,
                ['usuario' => null, 'nombre' => null, 'alerta' => true, 'datos' => ['correo' => $correo]]);
        });

        Event::listen(Logout::class, function (Logout $e) {
            if ($e->guard === 'web' && $e->user instanceof User) {
                Bitacora::registrar('sesion.salida', 'Cerró sesión', null, ['usuario' => $e->user]);
            }
        });

        Event::listen(PasswordReset::class, function (PasswordReset $e) {
            if ($e->user instanceof User) {
                Bitacora::registrar('sesion.clave_restablecida', 'Restableció su contraseña con el enlace del correo', null,
                    ['usuario' => $e->user, 'alerta' => true, 'referencia' => ['usuario', $e->user->IdUsuario]]);
            }
        });
    }
}
