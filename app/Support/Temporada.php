<?php

namespace App\Support;

/**
 * Adornos de temporada (login móvil y Comandas): se prenden y apagan solos por fecha, sin que nadie tenga que quitarlos.
 */
class Temporada
{
    /** Del 20 al 31 de octubre */
    public static function halloween(): bool
    {
        $hoy = now()->format('m-d');

        return ($hoy >= '10-20' && $hoy <= '10-31')
            // Para ver el adorno en la PC de desarrollo fuera de fecha: ?tema=halloween
            || (app()->isLocal() && request()->query('tema') === 'halloween');
    }
}
