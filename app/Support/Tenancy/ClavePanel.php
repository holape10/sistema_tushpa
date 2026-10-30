<?php

namespace App\Support\Tenancy;

use Carbon\CarbonInterface;

/**
 * Cada usuario del panel cambia su propia contraseña como máximo una vez por semana. Si la olvida, el dueño (o quien
 * lo creó) se la restablece y desde ahí puede volver a cambiarla.
 */
class ClavePanel
{
    public const DIAS = 7;

    public static function puedeCambiar(?CarbonInterface $ultimoCambio, CarbonInterface $ahora): bool
    {
        return $ultimoCambio === null || $ultimoCambio->copy()->addDays(self::DIAS)->lte($ahora);
    }

    /** Desde cuándo puede volver a cambiarla (null = ya puede) */
    public static function proximoCambio(?CarbonInterface $ultimoCambio, CarbonInterface $ahora): ?CarbonInterface
    {
        return self::puedeCambiar($ultimoCambio, $ahora) ? null : $ultimoCambio->copy()->addDays(self::DIAS);
    }
}
