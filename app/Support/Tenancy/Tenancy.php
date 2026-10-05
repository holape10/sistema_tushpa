<?php
namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use Illuminate\Support\Facades\DB;

/**
 * Multi-empresa por base de datos: la conexión por defecto apunta a la base del cliente del subdominio.
 * Modelos, sesiones y caché usan la conexión por defecto, así que quedan separados por empresa sin tocar el resto del código.
 */
class Tenancy
{
    public static function activa(): bool
    {
        return (bool) config('tenancy.dominio');
    }

    /** Cambia la base de la conexión por defecto (la siguiente consulta ya va a la nueva base) */
    public static function conectar(string $baseDatos): void
    {
        $con = config('database.default');
        if (config("database.connections.$con.database") === $baseDatos) {
            return;
        }
        config(["database.connections.$con.database" => $baseDatos]);
        DB::purge($con);
    }

    public static function baseActual(): string
    {
        return config('database.connections.' . config('database.default') . '.database');
    }

    public static function baseCentral(): string
    {
        return config('database.connections.central.database');
    }

    public static function nombreBase(string $ruc): string
    {
        $nombre = config('tenancy.prefijo_bd') . $ruc;
        // Solo letras, números y guion bajo: el nombre va dentro de CREATE/DROP DATABASE
        if (!preg_match('/^\d{11}$/', $ruc) || !preg_match('/^[a-z0-9_]{1,64}$/i', $nombre)) {
            throw new \InvalidArgumentException('Nombre de base de datos no válido.');
        }
        return $nombre;
    }

    /** @param string $subdominio el RUC o el subdominio propio del cliente (demo) */
    public static function urlCliente(string $subdominio): string
    {
        return config('tenancy.esquema') . '://' . $subdominio . '.' . config('tenancy.dominio');
    }

    /** Subdominios que nunca puede usar un cliente */
    public static function subdominiosReservados(): array
    {
        return array_merge(['www', 'mail', 'ftp', 'api', 'panel', 'webmail', 'cpanel', config('tenancy.subdominio_admin')],
            config('tenancy.principales', []));
    }

    /** Plan del cliente del subdominio actual (null = sin límites: empresa principal o modo de una sola empresa) */
    public static function plan(): ?\App\Models\Central\Plan
    {
        $cliente = self::cliente();
        return $cliente && $cliente->plan_id ? $cliente->planContratado : null;
    }

    /** Cliente del subdominio actual (null en el panel o en modo de una sola empresa) */
    public static function cliente(): ?Cliente
    {
        return app()->bound('tenancy.cliente') ? app('tenancy.cliente') : null;
    }

    /** Ejecuta algo dentro de la base de un cliente y vuelve a la base anterior */
    public static function en(string $baseDatos, callable $fn): mixed
    {
        $anterior = self::baseActual();
        self::conectar($baseDatos);
        try {
            return $fn();
        } finally {
            self::conectar($anterior);
        }
    }
}
