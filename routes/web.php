<?php

use App\Http\Controllers\CobroController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\ComandasController;
use App\Http\Controllers\ImpresionController;
use App\Http\Controllers\{TurnoController, KardexController, SunatController, SucursalController, VentaController, PosMovilController, PuntoVentaController, CompraController, SireController, ConcarController, CuentaController, PvTactilController, ProformaController, PvGrifoController};
use App\Http\Controllers\{AlmacenController, InventarioController, TransferenciaController, LoteController, NotaController, AsistenciaController, AsistenciaAdminController, ContabilidadController, GastoController, PlanillaController, TributoController, ReporteController, ContactoController};
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
    Route::get('/ventas/masiva', [\App\Http\Controllers\VentaMasivaController::class, 'index'])->name('ventas.masiva');
    Route::post('/ventas/masiva/emitir', [\App\Http\Controllers\VentaMasivaController::class, 'emitir'])->name('ventas.masiva.emitir');
    Route::get('/ventas/masiva/zip', [\App\Http\Controllers\VentaMasivaController::class, 'zip'])->name('ventas.masiva.zip');
    Route::get('/ventas/masiva/pdf/{id}', [\App\Http\Controllers\VentaMasivaController::class, 'pdf'])->whereNumber('id')->name('ventas.masiva.pdf');
    Route::get('/ventas/{id}/detalle', [VentaController::class, 'detalle'])->whereNumber('id')->name('ventas.detalle');
    Route::post('/ventas/{id}/medios', [VentaController::class, 'actualizarMedios'])->whereNumber('id')->name('ventas.medios');
    Route::post('/ventas/{id}/anular', [VentaController::class, 'anular'])->whereNumber('id')->name('ventas.anular');

    // Notas de crédito y débito electrónicas
    Route::get('/notas', [NotaController::class, 'index'])->name('notas.index');
    Route::get('/notas/nueva', [NotaController::class, 'create'])->name('notas.create');
    Route::post('/notas', [NotaController::class, 'store'])->name('notas.store');
    Route::get('/notas/datos/{id}', [NotaController::class, 'datos'])->whereNumber('id')->name('notas.datos');

    // Sucursales (empresa_negocios): datos, series y correlativos
    Route::get('/sucursales', [SucursalController::class, 'index'])->name('sucursales.index');
    Route::get('/sucursales/{id}/editar', [SucursalController::class, 'edit'])->whereNumber('id')->name('sucursales.edit');
    Route::patch('/sucursales/{id}', [SucursalController::class, 'update'])->whereNumber('id')->name('sucursales.update');

    // SUNAT: envío individual y resumen diario
    Route::get('/sunat/envios', [SunatController::class, 'envios'])->name('sunat.envios');
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
    Route::get('/importar-antiguo', [\App\Http\Controllers\ImportarAntiguoController::class, 'index'])->name('importar.index');
    Route::post('/importar-antiguo/cargar', [\App\Http\Controllers\ImportarAntiguoController::class, 'cargar'])->name('importar.cargar');
    Route::get('/importar-antiguo/revisar', [\App\Http\Controllers\ImportarAntiguoController::class, 'revisar'])->name('importar.revisar');
    Route::post('/importar-antiguo/ejecutar', [\App\Http\Controllers\ImportarAntiguoController::class, 'ejecutar'])->name('importar.ejecutar');
    Route::post('/importar-antiguo/eliminar', [\App\Http\Controllers\ImportarAntiguoController::class, 'eliminar'])->name('importar.eliminar');

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

//Route::get('/config', [EmpresaController::class, 'crearempresa'])->name('empresa.config');
//Route::post('/config', [EmpresaController::class, 'store'])->name('empresa.store');
//Route::get('/api/ruc/{ruc}', [EmpresaController::class, 'consultaRucSunat']);

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth'])
    ->name('dashboard');

require __DIR__.'/auth.php';