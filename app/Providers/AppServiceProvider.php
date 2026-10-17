<?php

namespace App\Providers;

use App\Support\HoraLocal\ConexionMysql;
use App\View\Composers\MenuComposer;
use Illuminate\Database\Connection;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', MenuComposer::class);
    }
}
