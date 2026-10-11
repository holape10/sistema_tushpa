<?php

// Web pública del dominio principal (tushpa.app). Se carga desde routes/web.php solo con el multi-empresa activo.

use App\Http\Controllers\SitioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SitioController::class, 'inicio'])->name('inicio');
Route::get('/tutorial', [SitioController::class, 'tutorial'])->name('tutorial');
Route::post('/contacto', [SitioController::class, 'contacto'])->middleware('throttle:5,10,sitio-contacto')->name('contacto');

Route::get('/ingresar', [SitioController::class, 'ingresar'])->name('ingresar');
Route::post('/ingresar', [SitioController::class, 'irAEmpresa'])->middleware('throttle:20,1,sitio-ingresar')->name('ingresar.ir');

Route::get('/registro', [SitioController::class, 'registro'])->name('registro');
Route::post('/registro', [SitioController::class, 'registrar'])->middleware('throttle:6,10,sitio-registro')->name('registrar');
Route::get('/registro/ruc/{ruc}', [SitioController::class, 'consultarRuc'])->where('ruc', '(10|15|17|20)\d{9}')
    ->middleware('throttle:20,1,sitio-ruc')->name('registro.ruc');
Route::get('/registro/listo/{cliente}', [SitioController::class, 'listo'])->whereNumber('cliente')->middleware('signed')->name('registro.listo');
Route::get('/registro/listo/{cliente}/https', [SitioController::class, 'estadoHttps'])->whereNumber('cliente')
    ->middleware(['signed', 'throttle:60,1,sitio-https'])->name('registro.https');
