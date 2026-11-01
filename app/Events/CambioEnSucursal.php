<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Algo cambió en una sucursal (mesas, cocina, pedidos del QR): las pantallas abiertas de esa sucursal lo reciben al
 * instante y vuelven a leer sus datos. Solo lleva qué temas cambiaron, nunca datos del negocio.
 */
class CambioEnSucursal implements ShouldBroadcastNow
{
    use Dispatchable;

    /** @param  array<int, string>  $temas  mesas | cocina | solicitudes */
    public function __construct(public string $ruc, public int $sucursal, public array $temas) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::canal($this->ruc, $this->sucursal))];
    }

    public function broadcastAs(): string
    {
        return 'cambio';
    }

    /** @return array{temas: array<int, string>} */
    public function broadcastWith(): array
    {
        return ['temas' => array_values($this->temas)];
    }

    /** Canal privado de la sucursal (con el RUC: cada empresa tiene el suyo aunque compartan el servidor) */
    public static function canal(string $ruc, int $sucursal): string
    {
        return "sucursal.{$ruc}.{$sucursal}";
    }
}
