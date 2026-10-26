<?php

// Web pública del dominio principal (tushpa.app). Se carga desde routes/web.php solo con el multi-empresa activo.

use App\Http\Controllers\CartaWebController;
use App\Http\Controllers\GoogleWebController;
use App\Http\Controllers\PromocionWebController;
use App\Http\Controllers\SitioController;
use App\Support\SeoSitio;
use Illuminate\Support\Facades\Route;

Route::get('/', [SitioController::class, 'inicio'])->name('inicio');
Route::get('/tutorial', [SitioController::class, 'tutorial'])->name('tutorial');
Route::get('/tutorial/{slug}', [SitioController::class, 'tutorialVer'])->where('slug', '[a-z0-9-]+')->name('tutorial.ver');

// SEO: una página por ciudad (se agregan en config/ciudades.php) y el sitemap para Google
Route::get('/sistema-de-facturacion-electronica-{ciudad}', [SitioController::class, 'ciudad'])
    ->where('ciudad', implode('|', array_map('preg_quote', SeoSitio::slugsCiudades())) ?: 'sin-ciudades')
    ->name('ciudad');
Route::get('/sitemap.xml', [SitioController::class, 'sitemap'])->name('sitemap');
Route::post('/contacto', [SitioController::class, 'contacto'])->middleware('throttle:5,10,sitio-contacto')->name('contacto');

Route::get('/ingresar', [SitioController::class, 'ingresar'])->name('ingresar');
Route::post('/ingresar', [SitioController::class, 'irAEmpresa'])->middleware('throttle:20,1,sitio-ingresar')->name('ingresar.ir');

Route::get('/registro', [SitioController::class, 'registro'])->name('registro');
Route::post('/registro', [SitioController::class, 'registrar'])->middleware('throttle:6,10,sitio-registro')->name('registrar');
Route::get('/registro/ruc/{ruc}', [SitioController::class, 'consultarRuc'])->where('ruc', '(10|15|17|20)\d{9}')
    ->middleware('throttle:20,1,sitio-ruc')->name('registro.ruc');
Route::get('/registro/subdominio/{sub}', [SitioController::class, 'revisarSubdominio'])->where('sub', '[A-Za-z0-9-]{1,40}')
    ->middleware('throttle:40,1,sitio-subdominio')->name('registro.subdominio');
Route::get('/registro/listo/{cliente}', [SitioController::class, 'listo'])->whereNumber('cliente')->middleware('signed')->name('registro.listo');
Route::get('/registro/listo/{cliente}/https', [SitioController::class, 'estadoHttps'])->whereNumber('cliente')
    ->middleware(['signed', 'throttle:60,1,sitio-https'])->name('registro.https');

// Crea tu carta: carta digital gratis con foto, diseño, enlace y QR (tushpa.app/c/su-negocio)
Route::get('/crea-tu-carta', [CartaWebController::class, 'crear'])->name('carta.crear');
Route::post('/crea-tu-carta', [CartaWebController::class, 'guardar'])->middleware('throttle:5,60,sitio-carta')->name('carta.guardar');
Route::post('/crea-tu-carta/foto', [CartaWebController::class, 'subirFoto'])->middleware('throttle:120,60,sitio-carta-foto')->name('carta.foto');
Route::post('/crea-tu-carta/recuperar', [CartaWebController::class, 'recuperar'])->middleware('throttle:3,10,sitio-carta-recuperar')->name('carta.recuperar');
Route::get('/crea-tu-carta/{slug}/lista', [CartaWebController::class, 'lista'])->where('slug', '[a-z0-9-]+')->name('carta.lista');
Route::get('/crea-tu-carta/{slug}/editar', [CartaWebController::class, 'editar'])->where('slug', '[a-z0-9-]+')->name('carta.editar');
Route::post('/crea-tu-carta/{slug}/editar', [CartaWebController::class, 'actualizar'])->where('slug', '[a-z0-9-]+')->middleware('throttle:20,10')->name('carta.actualizar');
Route::get('/c/{slug}', [CartaWebController::class, 'ver'])->where('slug', '[a-z0-9-]+')->name('carta.ver');

// Promociones creadas en el panel: su página de oferta, y el conteo de vistas y clics
Route::get('/ofertas/{slug}', [PromocionWebController::class, 'oferta'])->where('slug', '[a-z0-9-]+')->name('oferta');
Route::post('/promo/{id}/vista', [PromocionWebController::class, 'vista'])->whereNumber('id')->middleware('throttle:60,1,sitio-promo')->name('promo.vista');
Route::get('/promo/{id}', [PromocionWebController::class, 'clic'])->whereNumber('id')->name('promo.clic');

// Continuar con Google (One Tap): llena el registro con el nombre y el correo
Route::post('/google', [GoogleWebController::class, 'entrar'])->middleware('throttle:10,1,sitio-google')->name('google');
