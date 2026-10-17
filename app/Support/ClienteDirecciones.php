<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Direcciones de un cliente con varias sucursales (un RUC): la principal y las que usó en otras ventas */
class ClienteDirecciones
{
    private static ?bool $hayTabla = null;

    private static function hayTabla(): bool
    {
        return self::$hayTabla ??= Schema::hasTable('cliente_direcciones');
    }

    /** Guarda la dirección usada en la venta si no es la principal */
    public static function recordar(int $clicod, string $direccion, string $principal): void
    {
        $direccion = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $direccion)));
        if (! self::hayTabla() || $direccion === '' || $direccion === '--' || $direccion === mb_strtoupper(trim($principal))) {
            return;
        }
        DB::table('cliente_direcciones')->updateOrInsert(['clicod' => $clicod, 'direccion' => mb_substr($direccion, 0, 200)], ['usado' => now()]);
    }

    /** @return array<int, string> La principal primero y luego las demás (las usadas hace poco arriba) */
    public static function de(int $clicod, ?string $principal): array
    {
        $otras = self::hayTabla()
            ? DB::table('cliente_direcciones')->where('clicod', $clicod)->orderByDesc('usado')->limit(30)->pluck('direccion')->all() : [];

        return array_values(array_unique(array_filter(array_merge([$principal && $principal !== '--' ? $principal : null], $otras))));
    }
}
