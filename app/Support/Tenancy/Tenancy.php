<?php

namespace App\Support\Tenancy;

use App\Models\Central\Cliente;
use App\Models\Central\Plan;
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
        return config('database.connections.'.config('database.default').'.database');
    }

    public static function baseCentral(): string
    {
        return config('database.connections.central.database');
    }

    public static function nombreBase(string $ruc): string
    {
        $nombre = config('tenancy.prefijo_bd').$ruc;
        // Solo letras, números y guion bajo: el nombre va dentro de CREATE/DROP DATABASE
        if (! preg_match('/^\d{11}$/', $ruc) || ! preg_match('/^[a-z0-9_]{1,64}$/i', $nombre)) {
            throw new \InvalidArgumentException('Nombre de base de datos no válido.');
        }

        return $nombre;
    }

    /** @param string $subdominio el RUC o el subdominio propio del cliente (demo) */
    public static function urlCliente(string $subdominio): string
    {
        return config('tenancy.esquema').'://'.$subdominio.'.'.config('tenancy.dominio');
    }

    /** Subdominios que nunca puede usar un cliente */
    public static function subdominiosReservados(): array
    {
        return array_merge(['www', 'www2', 'mail', 'smtp', 'pop', 'imap', 'ftp', 'api', 'panel', 'webmail', 'cpanel', 'whm', 'ns1', 'ns2',
            'admin', 'app', 'soporte', 'ayuda', 'blog', 'static', 'cdn', 'assets', 'carta', 'cartas', 'status', 'test', 'dev', 'localhost',
            config('tenancy.subdominio_admin')], config('tenancy.principales', []));
    }

    /**
     * ¿Se puede usar este subdominio? Para avisar en vivo mientras se escribe (panel y registro web).
     *
     * @return array{ok: bool, mensaje: string, sugerencias: array<int, string>}
     */
    public static function revisarSubdominio(string $sub, ?Cliente $actual = null): array
    {
        $sub = mb_strtolower(trim($sub));
        $error = self::errorSubdominio($sub, $actual);

        return ['ok' => $error === null, 'mensaje' => $error ?? "{$sub}.".config('tenancy.dominio').' está libre.',
            'sugerencias' => $error && preg_match('/^[a-z0-9-]+$/', $sub) ? self::sugerenciasSubdominio($sub) : []];
    }

    /** @return array<int, string> Hasta 3 subdominios libres parecidos (mi-tienda → mitienda, mi-tienda-pe, mi-tienda2) */
    public static function sugerenciasSubdominio(string $sub): array
    {
        $base = trim(substr(preg_replace('/[^a-z0-9-]/', '', $sub), 0, 34), '-') ?: 'empresa';
        $candidatos = collect([str_replace('-', '', $base), $base.'-pe', $base.'-peru', $base.'-app'])
            ->merge(collect(range(1, 9))->map(fn ($n) => $base.$n))
            ->filter(fn ($c) => $c !== $sub && preg_match('/^(?!\d+$)[a-z0-9][a-z0-9-]*[a-z0-9]$/', $c) && ! in_array($c, self::subdominiosReservados(), true))
            ->unique()->values();
        $usados = Cliente::whereIn('subdominio', $candidatos)->pluck('subdominio')->map(fn ($u) => mb_strtolower($u))->all();

        return $candidatos->reject(fn ($c) => in_array($c, $usados, true))->take(3)->values()->all();
    }

    /**
     * Subdominio propio: letras, números y guiones; no puede ser un RUC, uno reservado ni el de otro cliente.
     * Devuelve el error o null si se puede usar.
     */
    public static function errorSubdominio(?string $sub, ?Cliente $actual = null): ?string
    {
        if (! $sub) {
            return null;
        }
        if (! preg_match('/^(?!\d+$)[a-z0-9][a-z0-9-]*[a-z0-9]$/', $sub) || strlen($sub) > 40) {
            return 'El subdominio solo lleva letras minúsculas, números y guiones (ej. demo, mi-tienda), y no puede ser solo números.';
        }
        // Una empresa que ya lo tenía antes de reservarlo lo conserva
        if (in_array($sub, self::subdominiosReservados(), true) && $actual?->subdominio !== $sub) {
            return "El subdominio {$sub} está reservado.";
        }
        if (Cliente::where('subdominio', $sub)->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))->exists()) {
            return "El subdominio {$sub} ya lo usa otra empresa.";
        }

        return null;
    }

    /** Plan del cliente del subdominio actual (null = sin límites: empresa principal o modo de una sola empresa) */
    public static function plan(): ?Plan
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
