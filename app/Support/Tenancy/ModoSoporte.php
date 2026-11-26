<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Superadmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Ver como el cliente": alguien del panel entra al sistema de una empresa como uno de sus usuarios, sin pedirle la
 * clave, para ver exactamente lo que le reclama. El enlace es de un solo uso y vence en 2 minutos; la visita dura a lo
 * más una hora. En modo "mirar" (el de siempre) no se puede guardar nada; en modo "cambiar" sí. Todo queda en la
 * bitácora de la empresa y arriba se ve la franja "Estás en modo soporte".
 */
class ModoSoporte
{
    public const SESION = 'modo_soporte';

    public const MODOS = ['mirar' => 'Solo para mirar', 'cambiar' => 'Puede hacer cambios'];

    private const MINUTOS_ENLACE = 2;

    public const MINUTOS_VISITA = 60;

    public static function disponible(): bool
    {
        try {
            return Schema::connection('central')->hasTable('soporte_ingresos');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Enlace de un solo uso para entrar al sistema de la empresa como ese usuario */
    public static function enlace(Cliente $cliente, Superadmin $quien, object $usuario, string $modo): string
    {
        $token = Str::random(48);
        DB::connection('central')->table('soporte_ingresos')->insert([
            'cliente_id' => $cliente->id, 'superadmin_id' => $quien->id, 'superadmin_nombre' => mb_substr((string) $quien->nombre, 0, 120),
            'IdUsuario' => $usuario->IdUsuario, 'usuario_nombre' => mb_substr(trim((string) ($usuario->apeusu ?: $usuario->name)), 0, 150),
            'modo' => isset(self::MODOS[$modo]) ? $modo : 'mirar', 'token_hash' => hash('sha256', $token),
            'expira_at' => now()->addMinutes(self::MINUTOS_ENLACE), 'created_at' => now(),
        ]);

        return rtrim($cliente->url(), '/').'/soporte/entrar/'.$token;
    }

    /**
     * Valida el enlace en la base de la empresa que lo abre y lo gasta. null si no sirve (vencido, usado, de otra empresa).
     *
     * @return array{ingreso: object, usuario: User}|null
     */
    public static function canjear(string $token): ?array
    {
        if (! self::disponible() || ! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }
        $central = DB::connection('central');
        $ingreso = $central->table('soporte_ingresos as i')->join('clientes as c', 'c.id', '=', 'i.cliente_id')
            ->where('i.token_hash', hash('sha256', $token))->whereNull('i.usado_at')->where('i.expira_at', '>', now())
            ->first(['i.*', 'c.base_datos']);
        // El enlace solo vale en la empresa para la que se hizo
        if (! $ingreso || $ingreso->base_datos !== DB::connection()->getDatabaseName()) {
            return null;
        }
        if (! $central->table('soporte_ingresos')->where('id', $ingreso->id)->whereNull('usado_at')->update(['usado_at' => now()])) {
            return null;
        }
        $usuario = User::where('IdUsuario', $ingreso->IdUsuario)->where('estusu', 1)->first();

        return $usuario ? ['ingreso' => $ingreso, 'usuario' => $usuario] : null;
    }

    /** @return array{id: int, nombre: string, usuario: string, modo: string, hasta: int}|null la visita de soporte en curso */
    public static function actual(): ?array
    {
        $datos = request()->hasSession() ? request()->session()->get(self::SESION) : null;

        return is_array($datos) ? $datos : null;
    }

    public static function soloMirar(): bool
    {
        return (self::actual()['modo'] ?? null) === 'mirar';
    }

    public static function terminar(int $id): void
    {
        try {
            DB::connection('central')->table('soporte_ingresos')->where('id', $id)->whereNull('salio_at')->update(['salio_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
