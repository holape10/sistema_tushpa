<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mozos y promotores solo usan el sistema conectados al Wi-Fi del local. Todos los celulares conectados al mismo Wi-Fi
 * salen a internet con la misma IP pública: esa IP (o varias, separadas por coma) se guarda en la sucursal.
 */
class RedLocal
{
    /** Roles que trabajan dentro del local con su celular: mozo y promotor */
    public const ROLES = [8, 13];

    /** @return array<int, string> IPs permitidas de la sucursal (vacío = sin restricción) */
    public static function ips(int $sucursal): array
    {
        $texto = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('ip_mozos');

        return array_values(array_filter(array_map('trim', explode(',', $texto))));
    }

    /** ¿Este usuario puede usar el sistema desde esta IP? (administrador y caja, siempre) */
    public static function permite(User $user, string $ip): bool
    {
        if (! $user->tieneRol(self::ROLES) || $user->esAdminOCaja() || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }
        $ips = self::ips((int) $user->id_empresa_negocio);

        return ! $ips || in_array($ip, $ips, true);
    }

    public static function mensaje(): string
    {
        return 'Conéctate al Wi-Fi del local para usar el sistema.';
    }
}
