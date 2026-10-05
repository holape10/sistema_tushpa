<?php
// Panel multi-empresa (base central). Se carga desde bootstrap/app.php con dominio admin.{dominio} y prefijo TENANCY_ADMIN_RUTA.

use App\Http\Controllers\Admin\{ClienteController, LoginController};
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('admin.clientes.index'));

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:superadmin')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->whereNumber('cliente')->name('clientes.edit');
    Route::patch('/clientes/{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('clientes.update');
    Route::post('/clientes/{cliente}/estado', [ClienteController::class, 'estado'])->whereNumber('cliente')->name('clientes.estado');
    Route::post('/clientes/{cliente}/https', [ClienteController::class, 'https'])->whereNumber('cliente')->name('clientes.https');
    Route::get('/planes', [ClienteController::class, 'planesIndex'])->name('planes.index');
    Route::post('/planes/{plan?}', [ClienteController::class, 'planesGuardar'])->whereNumber('plan')->name('planes.guardar');
    Route::get('/ruc/{ruc}', [ClienteController::class, 'consultarRuc'])->where('ruc', '\d{11}')->name('ruc');
});
