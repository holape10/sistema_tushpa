<?php

namespace App\Jobs;

use App\Models\Central\Cliente;
use App\Support\EstudioDescargas;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Un paso de la descarga automática de un estudio contable (lo manda estudio:descargas cada 10 minutos). Si ese día toca,
 * arma la lista de lo que hay que traer; luego avanza lo pendiente unos minutos. Nunca corren dos a la vez para el mismo estudio.
 */
class AvanzarDescargasEstudio implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $base, public ?int $clienteId = null) {}

    public function handle(): void
    {
        $candado = Cache::lock('estudio-descargas:'.$this->base, 900);
        if (! $candado->get()) {
            return;
        }
        $cliente = $this->clienteId ? Cliente::find($this->clienteId) : null;
        try {
            if ($cliente) {
                // La Clave SOL del propio estudio y los enlaces del aviso son los de este cliente
                app()->instance('tenancy.cliente', $cliente);
                URL::forceRootUrl($cliente->url());
            }
            $paso = function () {
                if (EstudioDescargas::toca()) {
                    EstudioDescargas::programar(EstudioDescargas::periodo());
                }
                EstudioDescargas::avanzar(480);
            };
            $cliente ? Tenancy::en($this->base, $paso) : $paso();
        } finally {
            if ($cliente) {
                app()->forgetInstance('tenancy.cliente');
            }
            $candado->release();
        }
    }
}
