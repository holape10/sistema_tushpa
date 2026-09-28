<?php

use App\Http\Controllers\CobroController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ComandasController;
use App\Http\Controllers\{ProductoController, CategoriaController, MesaController, PisoController, MedioPagoController, UsuarioController};

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('home.usuario');
    }
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/comandas', [ComandasController::class, 'seleccionServicio'])->name('comandas.seleccion');
    Route::get('/comandas/mesas/{piso_id}', [ComandasController::class, 'getMesasPorPiso'])->name('comandas.mesas_por_piso');
    Route::post('/comandas/set-servicio', [ComandasController::class, 'setServiceData'])->name('comandas.set_servicio');
    Route::get('/comandas/menu', [ComandasController::class, 'menuPedido'])->name('comandas.menu');
    Route::get('/comandas/productos', [ComandasController::class, 'searchProducts'])->name('comandas.search_products');
    Route::post('/comandas/carrito/agregar', [ComandasController::class, 'addToCart'])->name('comandas.add_to_cart');
    Route::post('/comandas/carrito/actualizar', [ComandasController::class, 'updateCartItem'])->name('comandas.update_cart_item');
    Route::post('/comandas/carrito/quitar', [ComandasController::class, 'removeCartItem'])->name('comandas.remove_cart_item');
    Route::get('/comandas/carrito', [ComandasController::class, 'getCartDetails'])->name('comandas.get_cart_details');
    Route::post('/comandas/enviar', [ComandasController::class, 'enviarComanda'])->name('comandas.enviar');
    Route::post('/comandas/carrito/vaciar', [ComandasController::class, 'clearCart'])->name('comandas.clear_cart');
    Route::get('/comandas/pedido/{ped_id}', [ComandasController::class, 'getPedidoDetails'])->name('comandas.pedido_details');
    Route::get('/comandas/activos-llevar-delivery', [ComandasController::class, 'activosLlevarDelivery'])->name('comandas.activos_llevar_delivery');
});

Route::get('/cobrarmesa/{ped_id}', [CobroController::class, 'cobrar'])->name('cobros.cobrar');
Route::post('/cobros/registrar', [CobroController::class, 'registrar'])->name('cobros.registrar');
Route::get('/cobros/cliente/{doc}', [CobroController::class, 'buscarCliente'])->name('cobros.cliente');
Route::get('/voucher/{id}', [CobroController::class, 'voucher'])->name('cobros.voucher');

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