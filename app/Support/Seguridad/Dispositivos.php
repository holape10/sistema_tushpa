<?php

namespace App\Support\Seguridad;

use App\Models\User;
use App\Support\AvisosCelular;
use App\Support\Bitacora;
use App\Support\GeoIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Equipos desde los que entra cada usuario (PC, celular, tablet). Cada navegador lleva una marca propia (cookie) que lo
 * identifica aunque cambie de IP. El primer equipo de un usuario queda de confianza; uno nuevo queda "por revisar" y se
 * avisa al dueño ("¿fuiste tú?"). Desde un equipo de confianza se confirma, se bloquea ("no fui yo": se cierran sus
 * sesiones y ya no puede entrar) o se le cierra la sesión. Las acciones delicadas (cambiar precios en masa, correlativos,
 * credenciales de SUNAT) solo se dejan hacer desde un equipo de confianza.
 */
class Dispositivos
{
    public const COOKIE = 'tushpa_equipo';

    /** Cada cuánto se anota la última actividad del equipo (segundos) */
    private const REFRESCO = 300;

    /** En las pruebas automáticas el control va apagado (el cliente de pruebas no guarda cookies), salvo en las suyas */
    public static bool $enPruebas = false;

    public static function disponible(): bool
    {
        if (app()->runningUnitTests() && ! self::$enPruebas) {
            return false;
        }
        static $hay = [];
        $base = DB::connection()->getDatabaseName();

        return $hay[$base] ??= Schema::hasTable('dispositivos') && Schema::hasTable('dispositivo_sesiones');
    }

    /**
     * En cada petición de un usuario con sesión: reconoce su equipo (o lo registra y avisa si es nuevo), une la sesión
     * al equipo y devuelve el equipo. null si el equipo está bloqueado (hay que sacarlo).
     *
     * @return object|false|null false = sin control (tablas aún no creadas); null = bloqueado
     */
    public static function controlar(Request $request, User $user): object|false|null
    {
        if (! self::disponible()) {
            return false;
        }
        $token = (string) $request->cookie(self::COOKIE);
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            $token = Str::random(40);
            // 5 años: la marca del equipo no debe perderse al cerrar el navegador
            Cookie::queue(self::COOKIE, $token, 60 * 24 * 365 * 5, null, null, null, true, false, 'lax');
        }
        $sesion = $request->session();
        $equipo = DB::table('dispositivos')->where('IdUsuario', $user->IdUsuario)->where('token', $token)->first();
        if (! $equipo) {
            $equipo = self::registrar($request, $user, $token);
        }
        if ($equipo->bloqueado_el) {
            return null;
        }

        // La sesión queda unida a este equipo (para poder cerrarla desde Seguridad)
        if ($sesion->get('equipo_sesion') !== $sesion->getId()) {
            DB::table('dispositivo_sesiones')->updateOrInsert(['session_id' => $sesion->getId()],
                ['dispositivo_id' => $equipo->id, 'IdUsuario' => $user->IdUsuario, 'created_at' => now()]);
            $sesion->put('equipo_sesion', $sesion->getId());
        }
        if (now()->timestamp - (int) $sesion->get('equipo_visto', 0) > self::REFRESCO) {
            $ip = $request->ip();
            DB::table('dispositivos')->where('id', $equipo->id)->update(['ultima_vez' => now(), 'ip' => $ip] + ($ip !== $equipo->ip ? ['ciudad' => self::ciudad($ip)] : []));
            $sesion->put('equipo_visto', now()->timestamp);
        }

        return $equipo;
    }

    /** Marca puesta al entrar con usuario y clave (o código): solo ese ingreso puede traer un equipo "por revisar" */
    public const INGRESO = 'equipo_ingreso_nuevo';

    /**
     * Equipo nuevo: el primero del usuario queda de confianza; también el que ya tenía su sesión abierta (o "recordarme")
     * de antes de existir este control, para que nadie note el cambio. Solo un ingreso nuevo con clave desde un equipo
     * desconocido queda por revisar y con aviso al dueño.
     */
    private static function registrar(Request $request, User $user, string $token): object
    {
        [$navegador, $sistema, $tipo] = self::describir((string) $request->userAgent());
        $primero = ! DB::table('dispositivos')->where('IdUsuario', $user->IdUsuario)->exists()
            || ! $request->session()->get(self::INGRESO);
        // Nombre de la PC: el que guardó el agente de impresión en este navegador (lo manda el login)
        $pc = mb_strtoupper(mb_substr(trim((string) ($request->input('equipo_pc') ?: $request->session()->get('equipo_pc', ''))), 0, 60)) ?: null;
        $id = DB::table('dispositivos')->insertGetId([
            'IdUsuario' => $user->IdUsuario, 'token' => $token, 'equipo_pc' => $pc,
            'nombre' => $pc ?: "{$navegador} en {$sistema}", 'navegador' => $navegador, 'sistema' => $sistema, 'tipo' => $tipo,
            'ip' => $request->ip(), 'ciudad' => self::ciudad($request->ip()), 'primera_vez' => now(), 'ultima_vez' => now(),
            'confiable' => $primero, 'por_revisar' => ! $primero,
        ]);
        $equipo = DB::table('dispositivos')->where('id', $id)->first();
        if (! $primero) {
            self::avisar($user, $equipo);
        }

        return $equipo;
    }

    /** Aviso al dueño: en la bitácora (y al panel, si es un administrador) y al celular del dueño si activó los avisos */
    private static function avisar(User $user, object $equipo): void
    {
        $donde = $equipo->nombre.' · IP '.$equipo->ip.($equipo->ciudad ? ' ('.$equipo->ciudad.')' : '');
        Bitacora::registrar('sesion.equipo_nuevo', "Entró desde un equipo nuevo: {$donde}", null, ['alerta' => true, 'usuario' => $user,
            'referencia' => ['dispositivo', $equipo->id]]);
        AvisosCelular::alDueno((int) $user->id_empresa_negocio, '🔐 Ingreso desde un equipo nuevo', ($user->apeusu ?: $user->name).' entró desde '.$donde.'. ¿Fue él?',
            route('seguridad.equipos'));
    }

    /** "Chrome", "Windows", "PC" a partir del navegador */
    public static function describir(string $agente): array
    {
        $navegador = match (true) {
            str_contains($agente, 'Edg/') => 'Edge', str_contains($agente, 'OPR/') => 'Opera', str_contains($agente, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agente, 'Firefox/') => 'Firefox', str_contains($agente, 'Chrome/') => 'Chrome', str_contains($agente, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        $sistema = match (true) {
            str_contains($agente, 'Android') => 'Android', (bool) preg_match('/iPhone|iPad|iPod/', $agente) => 'iPhone/iPad',
            str_contains($agente, 'Windows') => 'Windows', str_contains($agente, 'Mac OS') => 'Mac', str_contains($agente, 'Linux') => 'Linux',
            default => 'otro sistema',
        };
        $tipo = preg_match('/iPad|Tablet/i', $agente) || (str_contains($agente, 'Android') && ! str_contains($agente, 'Mobile')) ? 'Tablet'
            : (preg_match('/Mobile|iPhone/i', $agente) ? 'Celular' : 'PC');

        return [$navegador, $sistema, $tipo];
    }

    private static function ciudad(?string $ip): ?string
    {
        $u = GeoIp::ubicacion($ip);

        return $u ? mb_substr(trim(implode(', ', array_filter([$u['ciudad'] ?? null, $u['region'] ?? null, $u['pais'] !== 'PE' ? GeoIp::nombrePais($u['pais']) : null]))), 0, 120) ?: null : null;
    }

    /** El equipo de la petición actual (null si no se sabe) */
    public static function actual(): ?object
    {
        $user = auth()->user();
        $token = (string) request()->cookie(self::COOKIE);
        if (! $user || ! self::disponible() || $token === '') {
            return null;
        }

        return DB::table('dispositivos')->where('IdUsuario', $user->IdUsuario)->where('token', $token)->first();
    }

    /** ¿La petición actual viene de un equipo de confianza? (sin control instalado aún, sí) */
    public static function deConfianza(): bool
    {
        if (! self::disponible()) {
            return true;
        }

        return (bool) (self::actual()?->confiable);
    }

    /**
     * ¿Algún administrador de la empresa tiene un equipo de confianza? Si no tiene ninguno (cambió de PC, borró el
     * navegador, recién empieza), el administrador puede autorizar sus equipos ahí mismo; si tiene uno, debe usar ese
     * (así quien robó la clave no se aprueba solo).
     */
    public static function hayAdminDeConfianza(string $empresa): bool
    {
        if (! self::disponible()) {
            return true;
        }

        return DB::table('dispositivos as d')->join('users as u', 'u.IdUsuario', '=', 'd.IdUsuario')
            ->join('role_user as r', 'r.user_IdUsuario', '=', 'u.IdUsuario')
            ->where('u.IdEmpresa', $empresa)->where('r.role_id', 2)->where('u.estusu', 1)
            ->where('d.confiable', true)->whereNull('d.bloqueado_el')->exists();
    }

    /** Puede confirmar o bloquear equipos: desde un equipo de confianza, o si ningún administrador tiene uno */
    public static function puedeRevisar(User $user): bool
    {
        return self::deConfianza() || ($user->esAdmin() && ! self::hayAdminDeConfianza((string) $user->IdEmpresa));
    }

    /**
     * Frena una acción delicada que no viene de un equipo de confianza.
     *
     * @throws \RuntimeException
     */
    public static function exigirConfianza(string $accion): void
    {
        if (! self::deConfianza()) {
            // Dentro de una transacción el registro se desharía junto con la acción: se anota al terminar la petición
            $anotar = fn () => Bitacora::registrar('sesion.bloqueo_accion', "Se frenó «{$accion}»: no se hizo desde un equipo de confianza", null, ['alerta' => true]);
            DB::transactionLevel() > 0 ? app()->terminating($anotar) : $anotar();

            throw new \RuntimeException("Por seguridad, «{$accion}» solo se puede hacer desde un equipo de confianza. "
                .'Confirma este equipo desde una PC o celular ya autorizado (Seguridad › Equipos y sesiones) o pide a soporte que lo autorice.');
        }
    }

    /** Cierra las sesiones abiertas de un equipo (el usuario tendrá que volver a entrar en ese equipo) */
    public static function cerrarSesiones(object $equipo): int
    {
        $ids = DB::table('dispositivo_sesiones')->where('dispositivo_id', $equipo->id)->pluck('session_id');
        $n = DB::table('sessions')->whereIn('id', $ids)->delete();
        DB::table('dispositivo_sesiones')->where('dispositivo_id', $equipo->id)->delete();
        // Sin "recordarme": la cookie de recordar ya no sirve para volver a entrar sola
        DB::table('users')->where('IdUsuario', $equipo->IdUsuario)->update(['remember_token' => Str::random(60)]);

        return $n;
    }

    /** "No fui yo": se bloquea el equipo y se le cierran las sesiones */
    public static function bloquear(object $equipo, string $quien): void
    {
        DB::table('dispositivos')->where('id', $equipo->id)->update(['bloqueado_el' => now(), 'confiable' => false, 'por_revisar' => false, 'revisado_por' => $quien]);
        self::cerrarSesiones($equipo);
    }

    /** "Sí, fue él": el equipo queda de confianza */
    public static function confiar(object $equipo, string $quien): void
    {
        DB::table('dispositivos')->where('id', $equipo->id)->update(['confiable' => true, 'por_revisar' => false, 'bloqueado_el' => null, 'revisado_por' => $quien]);
    }

    /** Equipos por revisar de los usuarios de la empresa (para el aviso del administrador) */
    public static function porRevisar(string $empresa): int
    {
        if (! self::disponible()) {
            return 0;
        }

        return DB::table('dispositivos as d')->join('users as u', 'u.IdUsuario', '=', 'd.IdUsuario')
            ->where('u.IdEmpresa', $empresa)->where('d.por_revisar', true)->whereNull('d.bloqueado_el')->count();
    }
}
