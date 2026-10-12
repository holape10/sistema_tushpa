<?php

use App\Http\Controllers\ImpresionController;
use Illuminate\Support\Facades\Route;

// Agente de impresión (PC de las impresoras): se identifica con el token de su sucursal en la cabecera X-Token
Route::get('/impresion/trabajos', [ImpresionController::class, 'trabajos']);
Route::post('/impresion/resultado', [ImpresionController::class, 'resultado']);

// Instalador del agente: canjea su código de un solo uso por el bloque de config.ini (sin sesión)
Route::get('/agente/instalar/{codigo}', [ImpresionController::class, 'instalarAgente'])->where('codigo', '[A-Za-z0-9]{10}')
    ->middleware('throttle:10,1');
