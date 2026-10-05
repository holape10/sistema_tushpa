<?php

use App\Http\Middleware\IdentificarEmpresa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Panel multi-empresa: https://admin.{dominio}/{ruta} (solo si el multi-empresa está activo)
            if ($dominio = config('tenancy.dominio')) {
                Route::middleware('web')
                    ->domain(config('tenancy.subdominio_admin').'.'.$dominio)
                    ->prefix(config('tenancy.ruta_admin'))
                    ->name('admin.')
                    ->group(base_path('routes/admin.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Antes que todo (incluida la sesión): elige la base de datos según el subdominio
        $middleware->prepend(IdentificarEmpresa::class);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->attributes->get('panel_admin')
            ? route('admin.login')
            : '/login');
        // aquí luego registramos un middleware "VerificarEmpresaCreada"
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
