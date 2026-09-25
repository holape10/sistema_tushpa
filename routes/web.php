<?php

use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\{ProductoController, CategoriaController, MesaController, PisoController, MedioPagoController, UsuarioController};

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home.usuario');
    }
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::resource('productos', ProductoController::class)->except('show');
    Route::resource('categorias', CategoriaController::class)->except('show');
    Route::resource('mesas', MesaController::class)->except('show');
    Route::resource('pisos', PisoController::class)->except('show');
    Route::resource('mediospagos', MedioPagoController::class)->except('show')->parameters(['mediospagos' => 'medioPago']);
    Route::resource('usuarios', UsuarioController::class)->except('show');
});

Route::get('/home', function () {
    return redirect()->route(auth()->user()->rutaInicio());
})->middleware('auth')->name('home.usuario');

Route::get('/config', [EmpresaController::class, 'crearempresa'])->name('empresa.config');
Route::post('/config', [EmpresaController::class, 'store'])->name('empresa.store');
Route::get('/api/ruc/{ruc}', [EmpresaController::class, 'consultaRucSunat']);

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

require __DIR__.'/auth.php';