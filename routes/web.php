<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AlmacenController;
use App\Http\Controllers\AsistenciaAdminController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClinicaController;
use App\Http\Controllers\CobroController;
use App\Http\Controllers\CocinaController;
use App\Http\Controllers\ComandasController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\ConcarController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EntradaController;
use App\Http\Controllers\EntrenadorController;
use App\Http\Controllers\EstacionamientoController;
use App\Http\Controllers\FidelizacionController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\GimnasioController;
use App\Http\Controllers\GuiaController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ImportarAntiguoController;
use App\Http\Controllers\ImpresionController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\MedioPagoController;
use App\Http\Controllers\MermaController;
use App\Http\Controllers\MesaController;
use App\Http\Controllers\MotorizadoController;
use App\Http\Controllers\NotaController;
use App\Http\Controllers\PisoController;
use App\Http\Controllers\PlanillaController;
use App\Http\Controllers\PosMovilController;
use App\Http\Controllers\PreparadoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\PuntoVentaController;
use App\Http\Controllers\PvGrifoController;
use App\Http\Controllers\PvTactilController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\SireController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\SocioPortalController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\SunatController;
use App\Http\Controllers\TiendaConfigController;
use App\Http\Controllers\TiendaController;
use App\Http\Controllers\TipoCambioController;
use App\Http\Controllers\TransferenciaController;
use App\Http\Controllers\TributoController;
use App\Http\Controllers\TurnoController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\VentaMasivaController;

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
    Route::get('/comandas/opciones/{id}', [ComandasController::class, 'opcionesProducto'])->whereNumber('id')->name('comandas.opciones');
    Route::post('/comandas/carrito/actualizar', [ComandasController::class, 'updateCartItem'])->name('comandas.update_cart_item');
    Route::post('/comandas/carrito/quitar', [ComandasController::class, 'removeCartItem'])->name('comandas.remove_cart_item');
    Route::get('/comandas/carrito', [ComandasController::class, 'getCartDetails'])->name('comandas.get_cart_details');
    Route::post('/comandas/enviar', [ComandasController::class, 'enviarComanda'])->name('comandas.enviar');
    Route::post('/comandas/carrito/vaciar', [ComandasController::class, 'clearCart'])->name('comandas.clear_cart');
    Route::get('/comandas/pedido/{ped_id}', [ComandasController::class, 'getPedidoDetails'])->name('comandas.pedido_details');
    Route::get('/comandas/activos-llevar-delivery', [ComandasController::class, 'activosLlevarDelivery'])->name('comandas.activos_llevar_delivery');
    Route::post('/comandas/carrito/reducir-autorizado', [ComandasController::class, 'reducirItemAutorizado'])->name('comandas.reducir_autorizado');
    Route::post('/comandas/carrito/eliminar-autorizado', [ComandasController::class, 'eliminarItemAutorizado'])->name('comandas.eliminar_autorizado');
    Route::get('/comandas/precuenta/{ped_id}', [ComandasController::class, 'precuenta'])->whereNumber('ped_id')->name('comandas.precuenta');
    Route::get('/comandas/mesas-disponibles', [ComandasController::class, 'mesasDisponibles'])->name('comandas.mesas_disponibles');
    Route::post('/comandas/cambiar-mesa', [ComandasController::class, 'cambiarMesa'])->name('comandas.cambiar_mesa');
    Route::post('/comandas/unir-mesa', [ComandasController::class, 'unirMesa'])->name('comandas.unir_mesa');
});

Route::middleware('auth')->group(function () {
    // Turnos de caja
    Route::get('/turnos', [TurnoController::class, 'index'])->name('turnos.index');
    Route::post('/turnos/abrir', [TurnoController::class, 'abrir'])->name('turnos.abrir');
    Route::post('/turnos/cerrar', [TurnoController::class, 'cerrar'])->name('turnos.cerrar');
    Route::post('/turnos/movimiento', [TurnoController::class, 'movimiento'])->name('turnos.movimiento');
    Route::post('/turnos/movimiento/{id}/anular', [TurnoController::class, 'anularMovimiento'])->name('turnos.anular_movimiento');
    Route::get('/turnos/listado', [TurnoController::class, 'listado'])->name('turnos.listado');
    Route::get('/turnos/{id}', [TurnoController::class, 'show'])->whereNumber('id')->name('turnos.show');

    // Pantalla de cocina (KDS)
    Route::get('/cocina', [CocinaController::class, 'index'])->name('cocina.index');
    Route::get('/cocina/datos', [CocinaController::class, 'datos'])->name('cocina.datos');
    Route::post('/cocina/item/{id}/alternar', [CocinaController::class, 'alternarItem'])->whereNumber('id');
    Route::post('/cocina/ticket/{id}/listo', [CocinaController::class, 'listo'])->whereNumber('id');
    Route::post('/cocina/ticket/{id}/recuperar', [CocinaController::class, 'recuperar'])->whereNumber('id');
    Route::post('/cocina/configuracion', [CocinaController::class, 'configuracion'])->name('cocina.config');
    Route::post('/cocina/entregado/{ped_id}', [CocinaController::class, 'entregado'])->whereNumber('ped_id');

    // Reservas
    // Clínica: historias clínicas y agenda de citas
    Route::controller(ClinicaController::class)->prefix('clinica')->name('clinica.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/pacientes', 'guardarPaciente')->name('paciente');
        Route::get('/pacientes/buscar', 'buscar')->name('buscar');
        Route::get('/historia/{id}', 'ver')->whereNumber('id')->name('historia');
        Route::post('/historia/{id}/ficha', 'guardarFicha')->whereNumber('id')->name('ficha');
        Route::post('/historia/{id}/atencion', 'nuevaAtencion')->whereNumber('id')->name('nueva_atencion');
        Route::get('/historia/{id}/imprimir', 'imprimirHistoria')->whereNumber('id')->name('imprimir');
        Route::get('/historia/{id}/consentimiento', 'consentimiento')->whereNumber('id')->name('consentimiento');
        Route::get('/atencion/{id}', 'verAtencion')->whereNumber('id')->name('atencion');
        Route::post('/atencion/{id}', 'guardarAtencion')->whereNumber('id')->name('atencion.guardar');
        Route::post('/atencion/{id}/servicio', 'agregarServicio')->whereNumber('id')->name('atencion.servicio');
        Route::delete('/atencion/{id}/servicio/{det}', 'quitarServicio')->whereNumber('id')->whereNumber('det');
        Route::get('/atencion/{id}/receta', 'receta')->whereNumber('id')->name('receta');
        Route::post('/especialidades', 'guardarEspecialidad')->name('especialidad');
    });
    Route::controller(AgendaController::class)->prefix('clinica')->name('clinica.')->group(function () {
        Route::get('/agenda', 'index')->name('agenda');
        Route::get('/agenda/datos', 'datos')->name('agenda.datos');
        Route::post('/citas', 'guardar')->name('cita');
        Route::post('/citas/{id}/estado', 'estado')->whereNumber('id')->name('cita.estado');
        Route::get('/citas/{id}/atender', 'atender')->whereNumber('id')->name('cita.atender');
    });

    // Socios (clubes y asociaciones)
    Route::controller(SocioController::class)->prefix('socios')->name('socios.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'guardar')->name('guardar');
        Route::get('/generar/previa', 'previa')->name('previa');
        Route::post('/generar', 'generar')->name('generar');
        Route::post('/cargos', 'cargar')->name('cargar');
        Route::post('/cargos/{id}/anular', 'anularCargo')->whereNumber('id')->name('anular_cargo');
        Route::post('/config', 'guardarConfig')->name('config');
        Route::post('/categorias', 'guardarCategoria')->name('categoria');
        Route::post('/desde-clientes', 'desdeClientes')->name('desde_clientes');
        Route::post('/eliminar', 'eliminar')->name('eliminar');
        Route::post('/poner-categoria', 'ponerCategoria')->name('poner_categoria');
        Route::get('/{id}', 'ver')->whereNumber('id')->name('ver');
        Route::post('/{id}/estado', 'estado')->whereNumber('id')->name('estado');
        Route::post('/{id}/clave', 'restablecerClave')->whereNumber('id')->name('clave');
        Route::post('/{id}/cobrar', 'cobrar')->whereNumber('id')->name('cobrar');
        Route::get('/{id}/carnet', 'carnet')->whereNumber('id')->name('carnet');
    });

    // Motorizados del delivery
    Route::get('/motorizados', [MotorizadoController::class, 'index'])->name('motorizados.index');
    Route::post('/motorizados', [MotorizadoController::class, 'guardar'])->name('motorizados.guardar');
    Route::post('/motorizados/asignar', [MotorizadoController::class, 'asignar'])->name('motorizados.asignar');
    Route::post('/motorizados/{id}/eliminar', [MotorizadoController::class, 'eliminar'])->whereNumber('id')->name('motorizados.eliminar');

    // Fidelización: puntos y premios
    Route::controller(FidelizacionController::class)->prefix('fidelizacion')->name('fidelizacion.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/config', 'guardarConfig')->name('config');
        Route::post('/reglas', 'guardarRegla')->name('regla');
        Route::post('/reglas/{id}/eliminar', 'eliminarRegla')->whereNumber('id')->name('regla.eliminar');
        Route::get('/previa', 'previa')->name('previa');
        Route::post('/reservar', 'reservar')->name('reservar');
        Route::post('/premios', 'guardarPremio')->name('premio');
        Route::get('/productos', 'productos')->name('productos');
        Route::get('/clientes', 'buscar')->name('buscar');
        Route::get('/clientes/{clicod}', 'cliente')->whereNumber('clicod')->name('cliente');
        Route::post('/clientes/{clicod}/canjear', 'canjear')->whereNumber('clicod')->name('canjear');
        Route::post('/clientes/{clicod}/ajustar', 'ajustar')->whereNumber('clicod')->name('ajustar');
    });

    // Ubigeos (INEI): buscar distrito por nombre
    Route::get('/ubigeos', [GuiaController::class, 'ubigeos'])->name('ubigeos');

    // Guías de remisión electrónicas
    Route::controller(GuiaController::class)->prefix('guias')->name('guias.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/crear', 'crear')->name('crear');
        Route::post('/', 'guardar')->name('guardar');
        Route::get('/productos', 'productos')->name('productos');
        Route::get('/documento/{doc}', 'documento')->where('doc', '[0-9]{8,11}')->name('documento');
        Route::post('/{id}/enviar', 'enviar')->whereNumber('id')->name('enviar');
        Route::post('/{id}/consultar', 'consultar')->whereNumber('id')->name('consultar');
        Route::post('/{id}/eliminar', 'eliminar')->whereNumber('id')->name('eliminar');
        Route::get('/{id}/imprimir', 'imprimir')->whereNumber('id')->name('imprimir');
        Route::get('/{id}/pdf', 'pdf')->whereNumber('id')->name('pdf');
        Route::get('/{id}/{tipo}', 'archivo')->whereNumber('id')->whereIn('tipo', ['xml', 'cdr'])->name('archivo');
    });

    // Gimnasio: recepción, control de ingreso y panel del entrenador
    Route::controller(GimnasioController::class)->prefix('gimnasio')->name('gimnasio.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/clientes', 'lista')->name('lista');
        Route::post('/clientes', 'guardar')->name('guardar');
        Route::get('/clientes/{id}', 'ficha')->whereNumber('id')->name('ficha');
        Route::post('/clientes/{id}/estado', 'estado')->whereNumber('id')->name('estado');
        Route::post('/clientes/{id}/clave', 'restablecerClave')->whereNumber('id')->name('clave');
        Route::post('/clientes/{id}/vender', 'vender')->whereNumber('id')->name('vender');
        Route::post('/clientes/{id}/congelar', 'congelar')->whereNumber('id')->name('congelar');
        Route::post('/congelamientos/{id}', 'editarCongelamiento')->whereNumber('id')->name('congelamiento');
        Route::post('/congelamientos/{id}/anular', 'anularCongelamiento')->whereNumber('id')->name('congelamiento.anular');
        Route::post('/congelamientos/{id}/levantar', 'levantarCongelamiento')->whereNumber('id')->name('congelamiento.levantar');
        Route::post('/planes', 'guardarPlan')->name('plan');
        Route::get('/acceso', 'acceso')->name('acceso');
        Route::get('/acceso/hoy', 'ingresosHoy')->name('acceso.hoy');
        Route::post('/acceso/marcar', 'marcar')->middleware('throttle:120,1')->name('acceso.marcar');
        Route::post('/acceso/aprobar', 'aprobar')->middleware('throttle:30,1')->name('acceso.aprobar');
    });
    Route::controller(EntrenadorController::class)->prefix('gimnasio/entrenador')->name('entrenador.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/clientes/{id}', 'cliente')->whereNumber('id')->name('cliente');
        Route::post('/clientes/{id}/asignar', 'asignar')->whereNumber('id')->name('asignar');
        Route::post('/clientes/{id}/nutricion', 'guardarNutricion')->whereNumber('id')->name('nutricion');
        Route::post('/nutricion/{id}/quitar', 'quitarNutricion')->whereNumber('id')->name('nutricion.quitar');
    });

    // Hotel / hospedaje
    Route::get('/hotel', [HotelController::class, 'index'])->name('hotel.index');
    Route::get('/hotel/estado', [HotelController::class, 'estado'])->name('hotel.estado');
    Route::get('/hotel/estadia/{id}', [HotelController::class, 'detalle'])->whereNumber('id')->name('hotel.detalle');
    Route::post('/hotel/ingresar', [HotelController::class, 'ingresar'])->name('hotel.ingresar');
    Route::post('/hotel/extender', [HotelController::class, 'extender'])->name('hotel.extender');
    Route::post('/hotel/consumo', [HotelController::class, 'consumo'])->name('hotel.consumo');
    Route::post('/hotel/salida', [HotelController::class, 'salida'])->name('hotel.salida');
    Route::post('/hotel/anular', [HotelController::class, 'anular'])->name('hotel.anular');
    Route::post('/hotel/exceso', [HotelController::class, 'cobrarExceso'])->name('hotel.exceso');
    Route::post('/hotel/quitar', [HotelController::class, 'quitarItem'])->name('hotel.quitar');
    Route::post('/hotel/cambiar', [HotelController::class, 'cambiarHabitacion'])->name('hotel.cambiar');
    Route::get('/hotel/reservas', [HotelController::class, 'reservas'])->name('hotel.reservas');
    Route::post('/hotel/reservas', [HotelController::class, 'guardarReserva'])->name('hotel.reserva');
    Route::post('/hotel/reservas/cancelar', [HotelController::class, 'cancelarReserva'])->name('hotel.reserva.cancelar');
    Route::post('/hotel/configurar', [HotelController::class, 'configurar'])->name('hotel.configurar');
    Route::get('/hotel/reporte', [HotelController::class, 'reporte'])->name('hotel.reporte');
    Route::post('/hotel/estado-habitacion', [HotelController::class, 'cambiarEstado'])->name('hotel.cambiar_estado');
    Route::post('/hotel/habitaciones', [HotelController::class, 'guardarHabitacion'])->name('hotel.habitacion');
    Route::delete('/hotel/habitaciones/{id}', [HotelController::class, 'eliminarHabitacion'])->whereNumber('id');
    Route::post('/hotel/servicios', [HotelController::class, 'guardarServicio'])->name('hotel.servicio');
    Route::delete('/hotel/servicios/{id}', [HotelController::class, 'quitarServicio'])->whereNumber('id');

    Route::get('/reservas', [ReservaController::class, 'index'])->name('reservas.index');
    Route::get('/reservas/hoy', [ReservaController::class, 'delDia'])->name('reservas.dia');
    Route::post('/reservas', [ReservaController::class, 'guardar'])->name('reservas.guardar');
    Route::post('/reservas/{id}', [ReservaController::class, 'guardar'])->whereNumber('id');
    Route::post('/reservas/{id}/estado', [ReservaController::class, 'estado'])->whereNumber('id');
    Route::post('/reservas/{id}/atender', [ReservaController::class, 'atender'])->whereNumber('id');

    // Impresión directa (sin vista previa) y configuración de impresoras
    Route::post('/impresion/comprobante/{id}', [ImpresionController::class, 'comprobante'])->whereNumber('id')->name('impresion.comprobante');
    Route::post('/impresion/precuenta/{ped_id}', [ImpresionController::class, 'precuenta'])->whereNumber('ped_id')->name('impresion.precuenta');
    Route::get('/impresoras', [ImpresionController::class, 'index'])->name('impresion.index');
    Route::post('/impresoras', [ImpresionController::class, 'guardarImpresora'])->name('impresion.guardar');
    Route::post('/impresoras/asignaciones', [ImpresionController::class, 'asignaciones'])->name('impresion.asignaciones');
    Route::post('/impresoras/agente', [ImpresionController::class, 'descargarAgente'])->name('impresion.agente');
    Route::post('/impresoras/cola/{id}/reintentar', [ImpresionController::class, 'reintentar'])->whereNumber('id')->name('impresion.reintentar');
    Route::post('/impresoras/{id}', [ImpresionController::class, 'guardarImpresora'])->whereNumber('id')->name('impresion.actualizar');
    Route::post('/impresoras/{id}/prueba', [ImpresionController::class, 'prueba'])->whereNumber('id')->name('impresion.prueba');
    Route::delete('/impresoras/{id}', [ImpresionController::class, 'eliminarImpresora'])->whereNumber('id')->name('impresion.eliminar');

    // Panel de ventas
    Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('/ventas/exportar', [VentaController::class, 'exportar'])->name('ventas.exportar');

    // Venta masiva: comprobantes mensuales de los clientes con facturación mensual
    Route::get('/ventas/masiva', [VentaMasivaController::class, 'index'])->name('ventas.masiva');
    Route::post('/ventas/masiva/emitir', [VentaMasivaController::class, 'emitir'])->name('ventas.masiva.emitir');
    Route::get('/ventas/masiva/zip', [VentaMasivaController::class, 'zip'])->name('ventas.masiva.zip');
    Route::get('/ventas/masiva/pdf/{id}', [VentaMasivaController::class, 'pdf'])->whereNumber('id')->name('ventas.masiva.pdf');
    Route::get('/ventas/{id}/detalle', [VentaController::class, 'detalle'])->whereNumber('id')->name('ventas.detalle');
    Route::post('/ventas/{id}/medios', [VentaController::class, 'actualizarMedios'])->whereNumber('id')->name('ventas.medios');
    Route::post('/ventas/{id}/anular', [VentaController::class, 'anular'])->whereNumber('id')->name('ventas.anular');
    Route::get('/ventas/{id}/whatsapp', [VentaController::class, 'whatsapp'])->whereNumber('id')->name('ventas.whatsapp');
    Route::post('/ventas/{id}/telefono', [VentaController::class, 'telefono'])->whereNumber('id')->name('ventas.telefono');

    // Notas de crédito y débito electrónicas
    Route::get('/notas', [NotaController::class, 'index'])->name('notas.index');
    Route::get('/notas/nueva', [NotaController::class, 'create'])->name('notas.create');
    Route::post('/notas', [NotaController::class, 'store'])->name('notas.store');
    Route::get('/notas/datos/{id}', [NotaController::class, 'datos'])->whereNumber('id')->name('notas.datos');

    // Sucursales (empresa_negocios): datos, series y correlativos
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::get('/sucursales/nueva', [SucursalController::class, 'create'])->name('sucursales.create');
    Route::post('/sucursales', [SucursalController::class, 'store'])->name('sucursales.store');
    Route::post('/sucursales/cambiar', [SucursalController::class, 'cambiar'])->name('sucursales.cambiar');
    Route::get('/sucursales/{id}/editar', [SucursalController::class, 'edit'])->whereNumber('id')->name('sucursales.edit');
    Route::patch('/sucursales/{id}', [SucursalController::class, 'update'])->whereNumber('id')->name('sucursales.update');

    // SUNAT: envío individual y resumen diario
    Route::get('/sunat/envios', [SunatController::class, 'envios'])->name('sunat.envios');
    Route::get('/sunat/campana', [SunatController::class, 'campana'])->name('sunat.campana');
    Route::post('/sunat/enviar/{id}', [SunatController::class, 'enviar'])->whereNumber('id')->name('sunat.enviar');
    Route::get('/sunat/descargar/{id}/{tipo}', [SunatController::class, 'descargar'])->whereNumber('id')->name('sunat.descargar');
    Route::get('/sunat/resumenes', [SunatController::class, 'resumenes'])->name('sunat.resumenes');
    Route::post('/sunat/resumenes', [SunatController::class, 'resumenEnviar'])->name('sunat.resumen_enviar');
    Route::post('/sunat/resumenes/{id}/consultar', [SunatController::class, 'resumenConsultar'])->whereNumber('id')->name('sunat.resumen_consultar');
    Route::get('/sunat/resumenes/{id}/{tipo}', [SunatController::class, 'resumenDescargar'])->whereNumber('id')->name('sunat.resumen_descargar');

    // Kardex y stock
    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex.index');
    Route::get('/kardex/stock', [KardexController::class, 'stock'])->name('kardex.stock');
    Route::get('/kardex/movimiento', [KardexController::class, 'movimiento'])->name('kardex.movimiento');
    Route::post('/kardex/movimiento', [KardexController::class, 'guardarMovimiento'])->name('kardex.guardar_movimiento');
});

Route::middleware('auth')->group(function () {
    Route::get('/cobrarmesa/{ped_id}', [CobroController::class, 'cobrar'])->name('cobros.cobrar');
    Route::get('/cobrarmesa/{ped_id}/separadas', [CobroController::class, 'separadas'])->name('cobros.separadas');
    Route::get('/comandas/punto-venta', [CobroController::class, 'directa'])->name('cobros.directa');
    Route::post('/comandas/punto-venta', [CobroController::class, 'registrarDirecta'])->name('cobros.directa.registrar');
    Route::get('/cobros/clientes', [CobroController::class, 'sugerirClientes'])->name('cobros.clientes');
    Route::post('/cobros/registrar', [CobroController::class, 'registrar'])->name('cobros.registrar');
    Route::get('/cobros/cliente/{doc}', [CobroController::class, 'buscarCliente'])->name('cobros.cliente');
    Route::get('/voucher/{id}', [CobroController::class, 'voucher'])->name('cobros.voucher');

    // Cuentas por cobrar (ventas al crédito) y por pagar (compras al crédito)
    Route::prefix('/cuentas/{tipo}')->whereIn('tipo', ['cobrar', 'pagar'])->group(function () {
        Route::get('/', [CuentaController::class, 'index'])->name('cuentas.index');
        Route::get('/reporte', [CuentaController::class, 'reporte'])->name('cuentas.reporte');
        Route::get('/{id}/pagos', [CuentaController::class, 'pagos'])->whereNumber('id')->name('cuentas.pagos');
        Route::post('/{id}/pagar', [CuentaController::class, 'pagar'])->whereNumber('id')->name('cuentas.pagar');
        Route::post('/pago/{detId}/anular', [CuentaController::class, 'anularPago'])->whereNumber('detId')->name('cuentas.anular_pago');
        Route::get('/pago/{detId}/recibo', [CuentaController::class, 'recibo'])->whereNumber('detId')->name('cuentas.recibo');
    });

    // CONCAR: exportar ventas y compras como asientos
    Route::get('/concar', [ConcarController::class, 'index'])->name('concar.index');
    Route::post('/concar/config', [ConcarController::class, 'guardarConfig'])->name('concar.config');
    Route::get('/concar/{libro}/excel', [ConcarController::class, 'descargar'])->whereIn('libro', ['ventas', 'compras'])->name('concar.excel');

    // Soporte: contacto del proveedor del sistema (todos los usuarios)
    Route::view('/soporte', 'empresas.soporte')->name('soporte');

    // SIRE (SUNAT): credenciales, propuesta RVIE/RCE y cuadre con el sistema
    Route::get('/sire/credenciales', [SireController::class, 'credenciales'])->name('sire.credenciales');
    Route::post('/sire/credenciales', [SireController::class, 'guardarCredenciales'])->name('sire.credenciales.guardar');
    Route::get('/sire/{libro}', [SireController::class, 'index'])->whereIn('libro', ['ventas', 'compras'])->name('sire.index');
    Route::post('/sire/{libro}/solicitar', [SireController::class, 'solicitar'])->whereIn('libro', ['ventas', 'compras'])->name('sire.solicitar');
    Route::get('/sire/{libro}/solicitud/{id}/estado', [SireController::class, 'estado'])->whereIn('libro', ['ventas', 'compras'])->whereNumber('id')->name('sire.estado');
    Route::get('/sire/{libro}/solicitud/{id}/excel', [SireController::class, 'excel'])->whereIn('libro', ['ventas', 'compras'])->whereNumber('id')->name('sire.excel');
    Route::get('/sire/{libro}/solicitud/{id}/archivo', [SireController::class, 'archivo'])->whereIn('libro', ['ventas', 'compras'])->whereNumber('id')->name('sire.archivo');

    // Compras: ingreso de mercadería al kardex
    // Tipo de cambio SUNAT (apiperu.dev)
    Route::get('/tipo-cambio', [TipoCambioController::class, 'index'])->name('tipo_cambio.index');
    Route::get('/tipo-cambio/consultar', [TipoCambioController::class, 'consultar'])->middleware('throttle:30,1')->name('tipo_cambio.consultar');

    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/nueva', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{id}/editar', [CompraController::class, 'edit'])->whereNumber('id')->name('compras.edit');
    Route::put('/compras/{id}', [CompraController::class, 'update'])->whereNumber('id')->name('compras.update');
    Route::post('/compras/{id}/anular', [CompraController::class, 'anular'])->whereNumber('id')->name('compras.anular');
    Route::get('/compras/productos', [CompraController::class, 'productos'])->name('compras.productos');
    Route::get('/compras/proveedores', [CompraController::class, 'proveedores'])->name('compras.proveedores');
    Route::get('/compras/proveedor/{doc}', [CompraController::class, 'proveedor'])->name('compras.proveedor');

    // Punto Venta (escritorio): venta directa con teclado y lector de barras
    Route::get('/punto-venta', [PuntoVentaController::class, 'index'])->name('pv.index');
    Route::post('/punto-venta/registrar', [PuntoVentaController::class, 'registrar'])->name('pv.registrar');

    // PV Farmacia: punto de venta con lotes y vencimientos (FEFO)
    Route::get('/pv-farmacia', [PuntoVentaController::class, 'farmacia'])->name('pv.farmacia');
    Route::post('/pv-farmacia/registrar', [PuntoVentaController::class, 'registrarFarmacia'])->name('pv.farmacia.registrar');

    // Lotes y vencimientos
    Route::get('/lotes', [LoteController::class, 'index'])->name('lotes.index');
    Route::get('/lotes/excel', [LoteController::class, 'exportar'])->name('lotes.exportar');
    Route::post('/lotes/configuracion', [LoteController::class, 'configuracion'])->name('lotes.configuracion');

    // PV táctil (pantallas táctiles): emite e imprime el comprobante
    Route::get('/pv', [PvTactilController::class, 'index'])->name('pv.tactil');
    Route::post('/pv/registrar', [PvTactilController::class, 'registrar'])->name('pv.tactil.registrar');

    // Importar datos del sistema antiguo (solo Administrador)
    Route::get('/importar-antiguo', [ImportarAntiguoController::class, 'index'])->name('importar.index');
    Route::post('/importar-antiguo/cargar', [ImportarAntiguoController::class, 'cargar'])->name('importar.cargar');
    Route::get('/importar-antiguo/revisar', [ImportarAntiguoController::class, 'revisar'])->name('importar.revisar');
    Route::post('/importar-antiguo/ejecutar', [ImportarAntiguoController::class, 'ejecutar'])->name('importar.ejecutar');
    Route::post('/importar-antiguo/eliminar', [ImportarAntiguoController::class, 'eliminar'])->name('importar.eliminar');

    // PV Grifo: combustible por importe o galones (placa para factura) y productos de la tienda
    Route::get('/pv-grifo', [PvGrifoController::class, 'index'])->name('pv.grifo');
    Route::post('/pv-grifo/registrar', [PvGrifoController::class, 'registrar'])->name('pv.grifo.registrar');
    Route::get('/pv-grifo/placas', [PvGrifoController::class, 'placas'])->name('pv.grifo.placas');

    // Precios vigentes (precio dinámico) para refrescar el catálogo del PV táctil sin recargar
    Route::get('/pv/precios', [PvTactilController::class, 'precios'])->name('pv.tactil.precios');

    // Proformas: se crean en los puntos de venta y se cobran abriéndolas en la caja
    Route::get('/proformas', [ProformaController::class, 'index'])->name('proformas.index');
    Route::post('/proformas/guardar', [ProformaController::class, 'guardar'])->name('proformas.guardar');
    Route::get('/proformas/{id}/imprimir', [ProformaController::class, 'imprimir'])->whereNumber('id')->name('proformas.imprimir');
    Route::delete('/proformas/{id}', [ProformaController::class, 'destroy'])->whereNumber('id')->name('proformas.destroy');

    // PV Móvil: venta directa desde celular o tablet
    Route::get('/pv-movil', [PosMovilController::class, 'index'])->name('pos.movil');
    Route::get('/pv-movil/productos', [PosMovilController::class, 'productos'])->name('pos.productos');
    Route::post('/pv-movil/registrar', [PosMovilController::class, 'registrar'])->name('pos.registrar');
});

// Asistencia: el celular del trabajador abre este enlace firmado al escanear el QR del kiosko (sin sesión)
Route::get('/asistencia/m/{emp}/{accion}', [AsistenciaController::class, 'celular'])
    ->whereNumber('emp')->whereIn('accion', ['check_in_1', 'check_out_1', 'check_in_2', 'check_out_2'])
    ->middleware('throttle:30,1')->name('asistencia.celular');

// App instalable (PWA): manifiesto por empresa (sin sesión)
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');

// PDF A4 del comprobante que se envía por WhatsApp (enlace firmado, sin sesión)
Route::get('/cpe/pdf/{id}', [VentaController::class, 'pdfPublico'])->whereNumber('id')
    ->middleware(['signed', 'throttle:30,1'])->name('comprobante.pdf');

// QR de la guía de remisión impresa: datos del traslado y estado en SUNAT (sin sesión)
Route::get('/guia/v/{token}', [GuiaController::class, 'verificar'])->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:60,1')->name('guias.verificar');

// Portería: el QR del carnet de socio muestra si está al día (sin sesión)
Route::get('/socio/v/{token}', [SocioController::class, 'verificar'])->where('token', '[A-Za-z0-9]{32}')
    ->middleware('throttle:60,1')->name('socios.verificar');

// Portal del socio: {subdominio}/socio (estado de cuenta, pagos y carnet digital; sin sesión del sistema)
Route::prefix('socio')->name('socio.portal')->controller(SocioPortalController::class)->group(function () {
    Route::get('/', 'index');
    Route::post('/ingresar', 'entrar')->middleware('throttle:20,1')->name('.entrar');
    Route::post('/salir', 'salir')->name('.salir');
    Route::post('/clave', 'cambiarClave')->middleware('throttle:10,1')->name('.clave');
    Route::get('/estado', 'estado')->middleware('throttle:30,1')->name('.estado');
    Route::get('/comprobante/{id}', 'comprobante')->whereNumber('id')->middleware('throttle:30,1')->name('.comprobante');
    Route::post('/congelar', 'congelar')->middleware('throttle:10,1')->name('.congelar');
});

// Gestión de preparados: porciones del día
Route::middleware('auth')->prefix('preparados')->name('preparados.')->controller(PreparadoController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/controlar', 'controlar')->name('controlar');
    Route::post('/anotar', 'anotar')->name('anotar');
    Route::post('/guardar', 'guardarTodo')->name('guardar');
});

// Entradas del menú y platos que llevan entrada
Route::middleware('auth')->prefix('entradas')->name('entradas.')->controller(EntradaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'guardar')->name('guardar');
    Route::post('/platos', 'platos')->name('platos');
    Route::post('/{id}', 'actualizar')->whereNumber('id')->name('actualizar');
});

// Mermas: lo que se pierde sin venderse
Route::middleware('auth')->prefix('mermas')->name('mermas.')->controller(MermaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/productos', 'productos')->name('productos');
    Route::post('/', 'guardar')->name('guardar');
    Route::post('/{id}/anular', 'anular')->whereNumber('id')->name('anular');
});

// Recetas y food cost de los platos (Administrador)
Route::middleware('auth')->prefix('recetas')->name('recetas.')->controller(RecetaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/insumos', 'insumos')->name('insumos');
    Route::post('/insumos', 'crearInsumo')->name('insumo.crear');
    Route::get('/{id}', 'editar')->whereNumber('id')->name('editar');
    Route::get('/{id}/receta', 'receta')->whereNumber('id')->name('receta');
    Route::post('/{id}', 'guardar')->whereNumber('id')->name('guardar');
});

// Estacionamiento / valet parking: operación, configuración, abonados y reporte
Route::middleware('auth')->prefix('estacionamiento')->name('estacionamiento.')->controller(EstacionamientoController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/estado', 'estado')->name('estado');
    Route::post('/entrada', 'entrada')->name('entrada');
    Route::get('/buscar', 'buscar')->name('buscar');
    Route::get('/tickets/{id}', 'detalle')->whereNumber('id')->name('detalle');
    Route::get('/tickets/{id}/imprimir', 'imprimir')->whereNumber('id')->name('imprimir');
    Route::post('/tickets/{id}/cobrar', 'cobrar')->whereNumber('id')->name('cobrar');
    Route::post('/tickets/{id}/solicitar', 'solicitar')->whereNumber('id')->name('solicitar');
    Route::post('/tickets/{id}/espacio', 'moverEspacio')->whereNumber('id')->name('espacio');
    Route::post('/tickets/{id}/anular', 'anular')->whereNumber('id')->name('anular');
    Route::post('/tarifas', 'guardarTarifa')->name('tarifa');
    Route::post('/espacios', 'generarEspacios')->name('espacios.generar');
    Route::post('/espacios/{id}', 'guardarEspacio')->whereNumber('id')->name('espacios.guardar');
    Route::post('/espacios/{id}/quitar', 'quitarEspacio')->whereNumber('id')->name('espacios.quitar');
    Route::get('/abonados', 'abonados')->name('abonados');
    Route::post('/abonados', 'venderPension')->name('abonados.vender');
    Route::post('/abonados/{id}', 'editarAbonado')->whereNumber('id')->name('abonados.editar');
    Route::get('/reporte', 'reporte')->name('reporte');
});
// El cliente escanea el QR de su ticket: ve su tiempo y pide su auto (sin sesión)
Route::get('/valet/{codigo}', [EstacionamientoController::class, 'publico'])->where('codigo', '[A-Za-z0-9]{10}')->middleware('throttle:60,1')->name('valet.ver');
Route::post('/valet/{codigo}', [EstacionamientoController::class, 'pedir'])->where('codigo', '[A-Za-z0-9]{10}')->middleware('throttle:10,1')->name('valet.pedir');

// Carta digital con QR: configuración (Administrador) y la carta pública que ven los clientes (sin sesión)
Route::middleware('auth')->prefix('carta')->name('carta.')->controller(CartaController::class)->group(function () {
    Route::get('/configuracion', 'configuracion')->name('config');
    Route::post('/configuracion', 'guardar')->name('guardar');
    Route::get('/imprimir', 'imprimir')->name('imprimir');
});
Route::get('/carta/{sucursal?}', [CartaController::class, 'ver'])->whereNumber('sucursal')->middleware('throttle:120,1')->name('carta.ver');

// Tienda virtual pública de la empresa: {subdominio}/tiendavirtual (sin sesión del sistema)
Route::prefix('tiendavirtual')->name('tienda.')->controller(TiendaController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/ingresar', 'login')->name('login');
    Route::post('/ingresar', 'entrar')->middleware('throttle:20,1')->name('entrar');
    Route::get('/registro', 'registro')->name('registro');
    Route::post('/registro', 'registrar')->middleware('throttle:10,1')->name('registrar');
    Route::post('/salir', 'salir')->name('salir');
    Route::get('/mi-cuenta', 'cuenta')->name('cuenta');
    Route::post('/mi-cuenta/clave', 'cambiarClave')->name('clave');
    Route::post('/pedido', 'pedido')->middleware('throttle:20,1')->name('pedido');
});

Route::middleware('auth')->group(function () {
    Route::get('/tienda/configuracion', [TiendaConfigController::class, 'edit'])->name('tienda.config');
    Route::post('/tienda/configuracion', [TiendaConfigController::class, 'update'])->name('tienda.config.guardar');
});

Route::middleware('auth')->group(function () {
    // Asistencia: kiosko de marcación
    Route::get('/asistencia', [AsistenciaController::class, 'kiosko'])->name('asistencia.kiosko');
    Route::get('/asistencia/estado', [AsistenciaController::class, 'estado'])->name('asistencia.estado');
    Route::post('/asistencia/lector', [AsistenciaController::class, 'marcarLector'])->name('asistencia.lector');
    Route::get('/asistencia/qr/{emp}', [AsistenciaController::class, 'qr'])->whereNumber('emp')->name('asistencia.qr');
    Route::post('/asistencia/autorizar', [AsistenciaController::class, 'autorizar'])->name('asistencia.autorizar');

    // Asistencia: administración y reportes
    Route::get('/asistencia/turnos', [AsistenciaAdminController::class, 'turnos'])->name('asistencia.turnos');
    Route::post('/asistencia/turnos', [AsistenciaAdminController::class, 'turnoGuardar'])->name('asistencia.turnos.guardar');
    Route::put('/asistencia/turnos/{id}', [AsistenciaAdminController::class, 'turnoActualizar'])->whereNumber('id')->name('asistencia.turnos.actualizar');
    Route::delete('/asistencia/turnos/{id}', [AsistenciaAdminController::class, 'turnoEliminar'])->whereNumber('id')->name('asistencia.turnos.eliminar');
    Route::get('/asistencia/matriz', [AsistenciaAdminController::class, 'matriz'])->name('asistencia.matriz');
    Route::post('/asistencia/matriz', [AsistenciaAdminController::class, 'matrizGuardar'])->name('asistencia.matriz.guardar');
    Route::post('/asistencia/matriz/copiar', [AsistenciaAdminController::class, 'matrizCopiar'])->name('asistencia.matriz.copiar');
    Route::get('/asistencia/motivos', [AsistenciaAdminController::class, 'motivos'])->name('asistencia.motivos');
    Route::post('/asistencia/motivos/{id?}', [AsistenciaAdminController::class, 'motivoGuardar'])->whereNumber('id')->name('asistencia.motivos.guardar');
    Route::delete('/asistencia/motivos/{id}', [AsistenciaAdminController::class, 'motivoEliminar'])->whereNumber('id')->name('asistencia.motivos.eliminar');
    Route::get('/asistencia/configuracion', [AsistenciaAdminController::class, 'configuracion'])->name('asistencia.configuracion');
    Route::post('/asistencia/configuracion/ip', [AsistenciaAdminController::class, 'configuracionIp'])->name('asistencia.configuracion.ip');
    Route::post('/asistencia/feriados', [AsistenciaAdminController::class, 'feriadoGuardar'])->name('asistencia.feriados.guardar');
    Route::delete('/asistencia/feriados/{id}', [AsistenciaAdminController::class, 'feriadoEliminar'])->whereNumber('id')->name('asistencia.feriados.eliminar');
    Route::get('/asistencia/tareo', [AsistenciaAdminController::class, 'tareo'])->name('asistencia.tareo');
    Route::get('/asistencia/jornadas', [AsistenciaAdminController::class, 'jornadas'])->name('asistencia.jornadas');
});

Route::middleware('auth')->group(function () {
    // Gastos (servicios, alquiler, honorarios…) e importación desde el SIRE
    Route::get('/gastos', [GastoController::class, 'index'])->name('gastos.index');
    Route::get('/gastos/reporte', [GastoController::class, 'reporte'])->name('gastos.reporte');
    Route::post('/gastos/{id?}', [GastoController::class, 'guardar'])->whereNumber('id')->name('gastos.guardar');
    Route::post('/gastos/{id}/anular', [GastoController::class, 'anular'])->whereNumber('id')->name('gastos.anular');
    Route::post('/gastos/categorias/{id?}', [GastoController::class, 'categoriaGuardar'])->whereNumber('id')->name('gastos.categoria');
    Route::get('/gastos/sire', [GastoController::class, 'sire'])->name('gastos.sire');
    Route::post('/gastos/sire', [GastoController::class, 'sireImportar'])->name('gastos.sire.importar');

    // Planilla y boletas de pago
    Route::get('/planilla', [PlanillaController::class, 'index'])->name('planilla.index');
    Route::post('/planilla/generar', [PlanillaController::class, 'generar'])->name('planilla.generar');
    Route::post('/planilla/fila/{id}', [PlanillaController::class, 'fila'])->whereNumber('id')->name('planilla.fila');
    Route::delete('/planilla/fila/{id}', [PlanillaController::class, 'quitar'])->whereNumber('id')->name('planilla.quitar');
    Route::post('/planilla/{id}/cerrar', [PlanillaController::class, 'cerrar'])->whereNumber('id')->name('planilla.cerrar');
    Route::post('/planilla/{id}/reabrir', [PlanillaController::class, 'reabrir'])->whereNumber('id')->name('planilla.reabrir');
    Route::get('/planilla/{id}/boletas', [PlanillaController::class, 'boletas'])->whereNumber('id')->name('planilla.boletas');
    Route::get('/planilla/trabajadores', [PlanillaController::class, 'trabajadores'])->name('planilla.trabajadores');
    Route::post('/planilla/trabajadores/{emp}', [PlanillaController::class, 'trabajadorGuardar'])->whereNumber('emp')->name('planilla.trabajador');
    Route::get('/planilla/parametros', [PlanillaController::class, 'parametros'])->name('planilla.parametros');
    Route::post('/planilla/parametros', [PlanillaController::class, 'parametrosGuardar'])->name('planilla.parametros.guardar');

    // Contactos: clientes y proveedores
    Route::get('/contactos/sunat/{doc}', [ContactoController::class, 'sunat'])->name('contactos.sunat');
    Route::get('/{tipo}', [ContactoController::class, 'index'])->whereIn('tipo', ['clientes', 'proveedores'])->name('contactos.index');
    Route::post('/{tipo}/{id?}', [ContactoController::class, 'guardar'])->whereIn('tipo', ['clientes', 'proveedores'])->whereNumber('id')->name('contactos.guardar');
    Route::delete('/{tipo}/{id}', [ContactoController::class, 'eliminar'])->whereIn('tipo', ['clientes', 'proveedores'])->whereNumber('id')->name('contactos.eliminar');
    Route::get('/{tipo}/{id}/historial', [ContactoController::class, 'historial'])->whereIn('tipo', ['clientes', 'proveedores'])->whereNumber('id')->name('contactos.historial');

    // Reportes de ventas y compras (pantalla, Excel y PDF)
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/{clave}', [ReporteController::class, 'ver'])->name('reportes.ver');

    // Resumen tributario
    Route::get('/tributos', [TributoController::class, 'index'])->name('tributos.index');
    Route::post('/tributos/configuracion', [TributoController::class, 'config'])->name('tributos.config');
});

// Contabilidad (solo Administrador)
Route::middleware('auth')->prefix('contabilidad')->name('contabilidad.')->group(function () {
    Route::get('/plan', [ContabilidadController::class, 'plan'])->name('plan');
    Route::get('/plan/excel', [ContabilidadController::class, 'planExcel'])->name('plan.excel');
    Route::post('/plan', [ContabilidadController::class, 'cuentaGuardar'])->name('cuenta.guardar');
    Route::delete('/plan/{id}', [ContabilidadController::class, 'cuentaEliminar'])->whereNumber('id')->name('cuenta.eliminar');
    Route::post('/configuracion', [ContabilidadController::class, 'configGuardar'])->name('config');
    Route::get('/centralizar/{tipo}', [ContabilidadController::class, 'centralizar'])->whereIn('tipo', ['ventas', 'compras'])->name('centralizar');
    Route::post('/centralizar/{tipo}', [ContabilidadController::class, 'centralizarEjecutar'])->whereIn('tipo', ['ventas', 'compras'])->name('centralizar.ejecutar');
    Route::get('/diario', [ContabilidadController::class, 'diario'])->name('diario');
    Route::post('/asientos/{id?}', [ContabilidadController::class, 'asientoGuardar'])->whereNumber('id')->name('asiento.guardar');
    Route::delete('/asientos/{id}', [ContabilidadController::class, 'asientoEliminar'])->whereNumber('id')->name('asiento.eliminar');
    Route::post('/periodo', [ContabilidadController::class, 'periodoEstado'])->name('periodo');
    Route::get('/mayor', [ContabilidadController::class, 'mayor'])->name('mayor');
    Route::get('/balance', [ContabilidadController::class, 'balance'])->name('balance');
    Route::get('/estados', [ContabilidadController::class, 'estados'])->name('estados');
});

Route::middleware('auth')->group(function () {
    // Almacén: almacenes, inventarios (conteo físico / saldo inicial) y transferencias entre almacenes
    Route::get('/almacenes', [AlmacenController::class, 'index'])->name('almacenes.index');
    Route::post('/almacenes', [AlmacenController::class, 'store'])->name('almacenes.store');
    Route::put('/almacenes/{id}', [AlmacenController::class, 'update'])->whereNumber('id')->name('almacenes.update');
    Route::post('/almacenes/{id}/predeterminado', [AlmacenController::class, 'predeterminado'])->whereNumber('id')->name('almacenes.predeterminado');
    Route::delete('/almacenes/{id}', [AlmacenController::class, 'destroy'])->whereNumber('id')->name('almacenes.destroy');

    Route::get('/inventarios', [InventarioController::class, 'index'])->name('inventarios.index');
    Route::get('/inventarios/nuevo', [InventarioController::class, 'create'])->name('inventarios.create');
    Route::post('/inventarios', [InventarioController::class, 'store'])->name('inventarios.store');
    Route::get('/inventarios/plantilla', [InventarioController::class, 'plantilla'])->name('inventarios.plantilla');
    Route::post('/inventarios/leer-excel', [InventarioController::class, 'leerExcel'])->name('inventarios.leer_excel');
    Route::get('/inventarios/{id}', [InventarioController::class, 'show'])->whereNumber('id')->name('inventarios.show');
    Route::get('/inventarios/{id}/excel', [InventarioController::class, 'exportar'])->whereNumber('id')->name('inventarios.exportar');

    Route::get('/transferencias', [TransferenciaController::class, 'index'])->name('transferencias.index');
    Route::get('/transferencias/nueva', [TransferenciaController::class, 'create'])->name('transferencias.create');
    Route::post('/transferencias', [TransferenciaController::class, 'store'])->name('transferencias.store');
    Route::get('/transferencias/{id}', [TransferenciaController::class, 'show'])->whereNumber('id')->name('transferencias.show');
    Route::post('/transferencias/{id}/anular', [TransferenciaController::class, 'anular'])->whereNumber('id')->name('transferencias.anular');

    // Productos en Excel (antes del resource para que no choquen con productos/{producto})
    Route::get('/productos/exportar', [ProductoController::class, 'exportar'])->name('productos.exportar');
    Route::get('/productos/plantilla', [ProductoController::class, 'plantilla'])->name('productos.plantilla');
    Route::post('/productos/importar', [ProductoController::class, 'importar'])->name('productos.importar');

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

Route::middleware('auth')->group(function () {
    Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::get('/empresa/{id}/editar', [EmpresaController::class, 'edit'])->name('empresas.edit');
    Route::patch('/empresa/{id}/update', [EmpresaController::class, 'update'])->name('empresas.update');
});

// Registro inicial: público solo mientras no exista ninguna empresa (ver EmpresaController::puedeRegistrar)
Route::get('/config', [EmpresaController::class, 'crearempresa'])->name('empresa.config');
Route::post('/config', [EmpresaController::class, 'store'])->name('empresa.store');
Route::get('/api/ruc/{ruc}', [EmpresaController::class, 'consultaRucSunat'])->name('api.ruc');

// Route::get('/config', [EmpresaController::class, 'crearempresa'])->name('empresa.config');
// Route::post('/config', [EmpresaController::class, 'store'])->name('empresa.store');
// Route::get('/api/ruc/{ruc}', [EmpresaController::class, 'consultaRucSunat']);

Route::get('/inicio', [InicioController::class, 'index'])
    ->middleware(['auth'])
    ->name('inicio');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

require __DIR__.'/auth.php';
