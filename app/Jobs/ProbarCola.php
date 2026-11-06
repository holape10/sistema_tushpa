<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Tarea de prueba (php artisan colas:probar): deja escrito que el trabajador la atendió */
class ProbarCola implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function __construct(public string $marca) {}

    public function handle(): void
    {
        file_put_contents(storage_path('framework/cola-prueba'), $this->marca);
    }
}
