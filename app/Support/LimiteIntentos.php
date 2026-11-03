<?php

namespace App\Support;

use Illuminate\Cache\RateLimiter;
use Illuminate\Database\QueryException;

/**
 * El contador de intentos (throttle) guarda en la tabla cache de MySQL. Cuando llegan dos pedidos a la vez (la carta
 * del cliente preguntando por su pedido, dos pestañas...) MySQL a veces responde "Deadlock" y la página fallaba.
 * Aquí se reintenta una vez y, si sigue trabado, se deja pasar el pedido: mejor no contar un intento que dar error.
 */
class LimiteIntentos extends RateLimiter
{
    /** Deadlock y espera de bloqueo agotada */
    private const BLOQUEOS = [1213, 1205];

    /**
     * @template T
     *
     * @param  callable(): T  $accion
     * @param  T  $siTrabado
     * @return T
     */
    private function sinBloqueo(callable $accion, mixed $siTrabado): mixed
    {
        for ($vez = 1; ; $vez++) {
            try {
                return $accion();
            } catch (QueryException $e) {
                if (! in_array($e->errorInfo[1] ?? null, self::BLOQUEOS, true)) {
                    throw $e;
                }
                if ($vez >= 2) {
                    return $siTrabado;
                }
                usleep(50_000);
            }
        }
    }

    public function increment($key, $decaySeconds = 60, $amount = 1)
    {
        return $this->sinBloqueo(fn () => parent::increment($key, $decaySeconds, $amount), 0);
    }

    public function tooManyAttempts($key, $maxAttempts)
    {
        return $this->sinBloqueo(fn () => parent::tooManyAttempts($key, $maxAttempts), false);
    }
}
