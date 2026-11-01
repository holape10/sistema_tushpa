<?php

namespace App\Support;

use App\Events\CambioEnSucursal;
use Illuminate\Support\Facades\DB;

/**
 * Tiempo real: avisa a las pantallas abiertas de una sucursal (mesas, cocina, avisos del QR) que algo cambió, por el
 * servidor Reverb. Se manda después de responder (no hace esperar a nadie) y una sola vez por petición aunque se llame
 * varias veces. Si Reverb no está o falla, no pasa nada: las pantallas igual se actualizan solas cada cierto tiempo.
 */
class TiempoReal
{
    public const TEMAS = ['mesas', 'cocina', 'solicitudes'];

    /** @var array<int, array<string, true>> sucursal => temas por avisar al terminar */
    private static array $pendientes = [];

    /** @param  array<int, string>  $temas */
    public static function avisar(int $sucursal, array $temas = self::TEMAS): void
    {
        if (config('broadcasting.default') !== 'reverb' || ! $sucursal) {
            return;
        }
        $nuevo = ! self::$pendientes;
        foreach ($temas as $tema) {
            self::$pendientes[$sucursal][$tema] = true;
        }
        if ($nuevo) {
            app()->terminating(fn () => self::enviar());
        }
    }

    /** Manda lo acumulado (también lo usa la consola para avisar al instante) */
    public static function enviar(): void
    {
        $pendientes = self::$pendientes;
        self::$pendientes = [];
        foreach ($pendientes as $sucursal => $temas) {
            try {
                $ruc = (string) DB::table('empresa_negocios')->where('id_empresa_negocio', $sucursal)->value('IdEmpresa');
                if ($ruc !== '') {
                    broadcast(new CambioEnSucursal($ruc, (int) $sucursal, array_keys($temas)));
                }
            } catch (\Throwable) {
                // Sin Reverb las pantallas siguen con su actualización cada cierto tiempo
            }
        }
    }
}
