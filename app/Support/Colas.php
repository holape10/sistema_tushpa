<?php

namespace App\Support;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas en segundo plano con red de seguridad:
 *  - El trabajador de colas (servicio tushpa-colas, ver deploy/colas-instalar.sh) deja un "latido" en un archivo mientras
 *    está vivo (un archivo, no la caché: la caché de cada empresa está en su propia base).
 *  - despachar(): si hay latido reciente, la tarea va a la cola y la página no espera; si el trabajador no está
 *    instalado, se detuvo o la cola no responde, la tarea se hace en el momento, como siempre. Nunca se pierde.
 */
class Colas
{
    /** Segundos sin latido para dar al trabajador por detenido (espera de 3 s entre vueltas) */
    private const VIVO = 90;

    /** Cada cuánto renueva el trabajador su latido */
    private const CADA = 20;

    /** Para las pruebas: true/false fija si hay trabajador; null = mirar el latido */
    public static ?bool $fingir = null;

    public static function archivoLatido(): string
    {
        return storage_path('framework/cola-latido');
    }

    /** El trabajador avisa que sigue vivo (lo llama él mismo en cada vuelta) */
    public static function latido(): void
    {
        $archivo = self::archivoLatido();
        if (! is_file($archivo) || time() - (int) @filemtime($archivo) >= self::CADA) {
            @touch($archivo);
        }
    }

    /** ¿Hay un trabajador atendiendo la cola ahora mismo? */
    public static function vivo(): bool
    {
        if (self::$fingir !== null) {
            return self::$fingir;
        }
        clearstatcache(true, self::archivoLatido());
        $momento = @filemtime(self::archivoLatido());

        return $momento !== false && time() - $momento < self::VIVO;
    }

    /**
     * Manda la tarea a la cola o, si no hay trabajador, la hace ya.
     *
     * @return bool true si quedó en la cola; false si se hizo en el momento
     */
    public static function despachar(ShouldQueue $tarea): bool
    {
        if (self::vivo()) {
            try {
                Bus::dispatchToQueue($tarea->onConnection('database'));

                return true;
            } catch (\Throwable $e) {
                report($e);   // la cola no respondió: se hace en el momento
            }
        }
        dispatch_sync($tarea);

        return false;
    }

    /**
     * Estado para el comando colas:estado.
     *
     * @return array{vivo: bool, latido: ?int, pendientes: ?int, fallidas: ?int}
     */
    public static function estado(): array
    {
        clearstatcache(true, self::archivoLatido());
        $momento = @filemtime(self::archivoLatido());
        $central = fn (string $tabla) => rescue(fn () => Schema::connection('central')->hasTable($tabla)
            ? DB::connection('central')->table($tabla)->count() : null, null, false);

        return ['vivo' => self::vivo(), 'latido' => $momento === false ? null : time() - $momento,
            'pendientes' => $central('jobs'), 'fallidas' => $central('failed_jobs')];
    }
}
