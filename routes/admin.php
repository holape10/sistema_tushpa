<?php

// Panel multi-empresa (base central). Se carga desde bootstrap/app.php con dominio admin.{dominio} y prefijo TENANCY_ADMIN_RUTA.

use App\Http\Controllers\Admin\AccesoController;
use App\Http\Controllers\Admin\AgenteController;
use App\Http\Controllers\Admin\AnuncioController;
use App\Http\Controllers\Admin\CertificadoController;
use App\Http\Controllers\Admin\ClienteController;
use App\Http\Controllers\Admin\ComprobanteController;
use App\Http\Controllers\Admin\DatosCobroController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PerfilController;
use App\Http\Controllers\Admin\PromocionController;
use App\Http\Controllers\Admin\ProspectoController;
use App\Http\Controllers\Admin\RespaldoController;
use App\Http\Controllers\Admin\TableroController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TutorialController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Admin\VisitaController;
use App\Http\Middleware\PanelActivo;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.clientes.index'));

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

Route::middleware(['auth:superadmin', PanelActivo::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/tablero', [TableroController::class, 'index'])->name('tablero');
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/nuevo', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('/clientes/{cliente}/editar', [ClienteController::class, 'edit'])->whereNumber('cliente')->name('clientes.edit');
    Route::patch('/clientes/{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('clientes.update');
    Route::post('/clientes/{cliente}/estado', [ClienteController::class, 'estado'])->whereNumber('cliente')->name('clientes.estado');
    Route::post('/clientes/{cliente}/aviso', [ClienteController::class, 'aviso'])->whereNumber('cliente')->name('clientes.aviso');
    Route::post('/clientes/{cliente}/aviso/quitar', [ClienteController::class, 'quitarAviso'])->whereNumber('cliente')->name('clientes.aviso.quitar');
    Route::post('/clientes/{cliente}/https', [ClienteController::class, 'https'])->whereNumber('cliente')->name('clientes.https');
    Route::post('/clientes/{cliente}/pagado', [ClienteController::class, 'pagado'])->whereNumber('cliente')->name('clientes.pagado');
    Route::post('/clientes/{cliente}/eliminar', [ClienteController::class, 'eliminar'])->whereNumber('cliente')->name('clientes.eliminar');
    Route::get('/clientes/{cliente}/reiniciar', [ClienteController::class, 'reiniciarPrevia'])->whereNumber('cliente')->name('clientes.reiniciar');
    Route::post('/clientes/{cliente}/reiniciar', [ClienteController::class, 'reiniciar'])->whereNumber('cliente')->name('clientes.reiniciar.confirmar');
    Route::get('/mensajes', [ClienteController::class, 'mensajesWeb'])->name('mensajes.index');
    Route::get('/logos', [ClienteController::class, 'logos'])->name('logos.index');
    Route::get('/agente', [AgenteController::class, 'index'])->name('agente.index');
    Route::post('/agente', [AgenteController::class, 'subir'])->name('agente.subir');
    Route::get('/respaldos', [RespaldoController::class, 'index'])->name('respaldos.index');
    Route::post('/respaldos/crear', [RespaldoController::class, 'crear'])->name('respaldos.crear');
    Route::post('/respaldos/todas', [RespaldoController::class, 'crearTodas'])->name('respaldos.todas');
    Route::get('/respaldos/todas/descargar', [RespaldoController::class, 'descargarTodas'])->name('respaldos.todas.descargar');
    Route::get('/respaldos/descargar', [RespaldoController::class, 'descargar'])->name('respaldos.descargar');
    Route::post('/respaldos/eliminar', [RespaldoController::class, 'eliminar'])->name('respaldos.eliminar');
    Route::post('/respaldos/restaurar', [RespaldoController::class, 'restaurar'])->name('respaldos.restaurar');
    Route::post('/logos', [ClienteController::class, 'logosSubir'])->name('logos.subir');
    Route::post('/logos/eliminar', [ClienteController::class, 'logoEliminar'])->name('logos.eliminar');
    Route::post('/mensajes/{id}/atendido', [ClienteController::class, 'mensajeWebAtendido'])->whereNumber('id')->name('mensajes.atendido');
    Route::get('/planes', [ClienteController::class, 'planesIndex'])->name('planes.index');
    Route::post('/planes/{plan?}', [ClienteController::class, 'planesGuardar'])->whereNumber('plan')->name('planes.guardar');
    Route::get('/visitas', [VisitaController::class, 'index'])->name('visitas.index');
    Route::get('/anuncios', [AnuncioController::class, 'index'])->name('anuncios.index');
    Route::post('/anuncios/{id?}', [AnuncioController::class, 'guardar'])->whereNumber('id')->name('anuncios.guardar');
    Route::post('/anuncios/{id}/estado', [AnuncioController::class, 'estado'])->whereNumber('id')->name('anuncios.estado');
    Route::post('/anuncios/{id}/eliminar', [AnuncioController::class, 'eliminar'])->whereNumber('id')->name('anuncios.eliminar');
    Route::get('/promociones', [PromocionController::class, 'index'])->name('promociones.index');
    Route::post('/promociones/{id?}', [PromocionController::class, 'guardar'])->whereNumber('id')->name('promociones.guardar');
    Route::post('/promociones/{id}/estado', [PromocionController::class, 'estado'])->whereNumber('id')->name('promociones.estado');
    Route::post('/promociones/{id}/eliminar', [PromocionController::class, 'eliminar'])->whereNumber('id')->name('promociones.eliminar');
    Route::get('/tutoriales', [TutorialController::class, 'index'])->name('tutoriales.index');
    Route::post('/tutoriales/{id?}', [TutorialController::class, 'guardar'])->whereNumber('id')->name('tutoriales.guardar');
    Route::post('/tutoriales/{id}/eliminar', [TutorialController::class, 'eliminar'])->whereNumber('id')->name('tutoriales.eliminar');
    Route::get('/certificados', [CertificadoController::class, 'index'])->name('certificados.index');
    Route::post('/certificados/{id?}', [CertificadoController::class, 'guardar'])->whereNumber('id')->name('certificados.guardar');
    Route::post('/certificados/{id}/eliminar', [CertificadoController::class, 'eliminar'])->whereNumber('id')->name('certificados.eliminar');
    Route::get('/prospectos', [ProspectoController::class, 'index'])->name('prospectos.index');
    Route::get('/prospectos/excel', [ProspectoController::class, 'excel'])->name('prospectos.excel');
    Route::post('/prospectos/carta/{id}/contactado', [ProspectoController::class, 'contactado'])->whereNumber('id')->name('prospectos.contactado');
    Route::get('/clientes/{cliente}/accesos', [AccesoController::class, 'index'])->whereNumber('cliente')->name('clientes.accesos');
    Route::post('/clientes/{cliente}/accesos/{usuario}', [AccesoController::class, 'clave'])->whereNumber(['cliente', 'usuario'])->middleware('throttle:20,1')->name('clientes.accesos.clave');
    Route::get('/clientes/{cliente}/comprobantes', [ComprobanteController::class, 'index'])->whereNumber('cliente')->name('clientes.comprobantes');
    Route::post('/clientes/{cliente}/comprobantes', [ComprobanteController::class, 'accion'])->whereNumber('cliente')->name('clientes.comprobantes.accion');
    Route::post('/cartas/{id}/estado', [VisitaController::class, 'cartaEstado'])->whereNumber('id')->name('cartas.estado');
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
    Route::post('/tickets/{id}/responder', [TicketController::class, 'responder'])->whereNumber('id')->name('tickets.responder');
    Route::post('/tickets/{id}/estado', [TicketController::class, 'estado'])->whereNumber('id')->name('tickets.estado');
    Route::get('/tickets/{id}/archivo/{mensaje}/{n}', [TicketController::class, 'archivo'])->whereNumber(['id', 'mensaje', 'n'])->name('tickets.archivo');
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::post('/usuarios/{usuario}/permiso', [UsuarioController::class, 'permiso'])->whereNumber('usuario')->name('usuarios.permiso');
    Route::post('/usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->whereNumber('usuario')->name('usuarios.estado');
    Route::post('/usuarios/{usuario}/clave', [UsuarioController::class, 'clave'])->whereNumber('usuario')->name('usuarios.clave');
    // Mis cuentas y QR de cobro (cada usuario, también el dueño, puede tener varios)
    Route::get('/mis-cobros', [DatosCobroController::class, 'index'])->name('cobros.index');
    Route::post('/mis-cobros/cuenta/{id?}', [DatosCobroController::class, 'guardarCuenta'])->whereNumber('id')->name('cobros.cuenta');
    Route::post('/mis-cobros/qr/{id?}', [DatosCobroController::class, 'guardarQr'])->whereNumber('id')->name('cobros.qr');
    Route::post('/mis-cobros/{id}/eliminar', [DatosCobroController::class, 'eliminar'])->whereNumber('id')->name('cobros.eliminar');
    Route::post('/mis-cobros/whatsapp', [DatosCobroController::class, 'guardarWhatsapp'])->name('cobros.whatsapp');
    // Mi perfil: foto, nombre, celular, cumpleaños y contraseña (una vez por semana)
    Route::get('/mi-perfil', [PerfilController::class, 'index'])->name('perfil');
    Route::post('/mi-perfil', [PerfilController::class, 'guardar'])->name('perfil.guardar');
    Route::post('/mi-perfil/foto/quitar', [PerfilController::class, 'quitarFoto'])->name('perfil.foto.quitar');
    Route::post('/mi-perfil/clave', [PerfilController::class, 'clave'])->middleware('throttle:10,1')->name('perfil.clave');
    Route::get('/subdominio/{sub}', [ClienteController::class, 'revisarSubdominio'])->where('sub', '[A-Za-z0-9-]{1,40}')->name('subdominio');
    Route::get('/ruc/{ruc}', [ClienteController::class, 'consultarRuc'])->where('ruc', '\d{11}')->name('ruc');
});
