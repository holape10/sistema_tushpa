<?php

use App\Http\Controllers\ImpresionController;
use Illuminate\Support\Facades\Route;

// Agente de impresión (PC de las impresoras): se identifica con el token de su sucursal en la cabecera X-Token
Route::get('/impresion/trabajos', [ImpresionController::class, 'trabajos']);
Route::post('/impresion/resultado', [ImpresionController::class, 'resultado']);
