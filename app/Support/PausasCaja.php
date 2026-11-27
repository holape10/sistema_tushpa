<?php

namespace App\Support;

use App\Models\Turno;
use App\Models\User;
use App\Support\Tenancy\ModoSoporte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * Pausar caja: la caja queda bloqueada con un mensaje grande para los clientes y el turno sigue abierto. Para volver a
 * vender, la clave del cajero o la de un administrador. Cada pausa queda guardada (quién, desde y hasta cuándo, motivo)
 * y sale en la Línea de tiempo y en la bitácora.
 */
class PausasCaja
{
    /** Motivo => [mensaje grande, texto chico] */
    public const MOTIVOS = [
        'CERRADA' => ['CAJA CERRADA', 'Por favor, use otra caja'],
        'DESCANSO' => ['EN DESCANSO', 'Vuelvo en unos minutos'],
        'OTRA' => ['USE OTRA CAJA', 'Gracias por su comprensión'],
        'SERVICIO' => ['FUERA DE SERVICIO', 'Por favor, use otra caja'],
        'INACTIVIDAD' => ['CAJA CERRADA', 'Por favor, use otra caja'],
    ];

    /** Clave de la sesión con la pausa en curso (bloquea las demás pantallas: ver middleware CajaEnPausa) */
    public const SESION = 'caja_pausa';

    public static function disponible(): bool
    {
        return Schema::hasTable('caja_pausas');
    }

    /** La pausa abierta del usuario (null si no tiene) */
    public static function abierta(User $user): ?object
    {
        if (! self::disponible()) {
            return null;
        }

        return DB::table('caja_pausas')->where('IdUsuario', $user->IdUsuario)->whereNull('fin')->orderByDesc('id')->first();
    }

    /** Pausa la caja del usuario (si ya estaba en pausa, devuelve esa) */
    public static function iniciar(User $user, string $motivo, ?int $minutos = null, ?string $pc = null): object
    {
        if ($abierta = self::abierta($user)) {
            return $abierta;
        }
        $motivo = isset(self::MOTIVOS[$motivo]) ? $motivo : 'CERRADA';
        $id = DB::table('caja_pausas')->insertGetId([
            'id_empresa_negocio' => $user->id_empresa_negocio, 'IdUsuario' => $user->IdUsuario, 'id_turno' => Turno::abiertoDe($user)?->id_turno,
            'motivo' => $motivo, 'minutos' => $motivo === 'DESCANSO' && $minutos ? max(1, min(240, $minutos)) : null,
            'pc' => $pc ? mb_substr(trim($pc), 0, 40) : null, 'inicio' => now(),
        ]);
        session()->put(self::SESION, $id);

        return DB::table('caja_pausas')->where('id', $id)->first();
    }

    /**
     * Quita la pausa con la clave del cajero o la de un administrador de la empresa (5 intentos por minuto).
     *
     * @return int cuántos minutos duró
     *
     * @throws \RuntimeException con el motivo (clave incorrecta, demasiados intentos)
     */
    public static function terminar(User $user, string $clave): int
    {
        $pausa = self::abierta($user);
        if (! $pausa) {
            session()->forget(self::SESION);

            return 0;
        }
        $quien = self::quienAutoriza($user, $clave);
        $minutos = (int) now()->parse($pausa->inicio)->diffInMinutes(now());
        DB::table('caja_pausas')->where('id', $pausa->id)->update(['fin' => now(), 'IdUsuario_fin' => $quien]);
        session()->forget(self::SESION);
        Bitacora::registrar('caja.pausa', 'Caja en pausa '.$minutos.' min ('.mb_strtolower(self::MOTIVOS[$pausa->motivo][0]).')'
            .($pausa->pc ? ' · '.$pausa->pc : ''), null, ['referencia' => ['turno', $pausa->id_turno]]);

        return $minutos;
    }

    /** Quién quita la pausa: el mismo cajero o un administrador (soporte con permiso de cambios no necesita clave) */
    private static function quienAutoriza(User $user, string $clave): int
    {
        if (ModoSoporte::conTodo()) {
            return (int) $user->IdUsuario;
        }
        if ($clave === '') {
            throw new \RuntimeException('Escribe tu clave para volver a la caja.');
        }
        $llave = 'pausa-caja:'.$user->IdUsuario;
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw new \RuntimeException('Demasiados intentos. Espera un minuto.');
        }
        $propia = DB::table('users')->where('IdUsuario', $user->IdUsuario)->value('password');
        if ($propia && Hash::check($clave, $propia)) {
            RateLimiter::clear($llave);

            return (int) $user->IdUsuario;
        }
        $admins = DB::table('users')->where('IdEmpresa', $user->IdEmpresa)->where('estusu', 1)
            ->whereIn('IdUsuario', DB::table('role_user')->where('role_id', 2)->pluck('user_IdUsuario'))->get(['IdUsuario', 'password']);
        foreach ($admins as $admin) {
            if (Hash::check($clave, (string) $admin->password)) {
                RateLimiter::clear($llave);

                return (int) $admin->IdUsuario;
            }
        }
        RateLimiter::hit($llave, 60);

        throw new \RuntimeException('Clave incorrecta.');
    }
}
