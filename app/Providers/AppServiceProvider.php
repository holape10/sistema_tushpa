<?php

namespace App\Providers;

use App\Support\BitacoraSesion;
use App\Support\Colas;
use App\Support\ContactoWeb;
use App\Support\HoraLocal\ConexionMysql;
use App\Support\LimiteIntentos;
use App\View\Composers\MenuComposer;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Connection;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Las ventas y demás registros con hora toman la de Perú (PHP), no la del reloj de MySQL (UTC en el servidor)
        Connection::resolverFor('mysql', fn ($pdo, $database, $prefix, $config) => new ConexionMysql($pdo, $database, $prefix, $config));
        // El contador de intentos no tumba la página si MySQL se traba (deadlock en la tabla cache)
        $this->app->extend(RateLimiter::class, fn ($limiter, $app) => new LimiteIntentos($app->make('cache')->driver($app['config']->get('cache.limiter'))));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', MenuComposer::class);
        // Inicios de sesión, intentos fallidos y bloqueos en la bitácora de cada empresa
        BitacoraSesion::escuchar();
        // El trabajador de colas deja su latido: así el sistema sabe si puede mandarle tareas (si no, las hace en el momento)
        // (solo el trabajador de verdad: una tarea hecha en el momento, "sync", no cuenta)
        // Ojo: no devolver nada; si quien escucha Looping devuelve false, el trabajador se pausa
        Event::listen(Looping::class, function (Looping $e): void {
            if ($e->connectionName !== 'sync') {
                Colas::latido();
            }
        });
        Event::listen(JobProcessing::class, function (JobProcessing $e): void {
            if ($e->connectionName !== 'sync') {
                Colas::latido();
            }
        });
        // Web pública: contacto del partner de la ciudad (Piura) o el nuestro
        View::composer('sitio.*', fn ($vista) => $vista->with('contactoPartner', ContactoWeb::aplicar()));
    }
}
