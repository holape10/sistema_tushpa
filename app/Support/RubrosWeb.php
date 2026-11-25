<?php

namespace App\Support;

/**
 * Lo que hace el sistema en cada rubro, para las páginas públicas (tushpa.app y la portada de cada marca).
 * Solo funciones que existen en el sistema y sin nombrar a TUSHPA: lo usan también los revendedores.
 */
class RubrosWeb
{
    /**
     * @return array<string, array{icono: string, nombre: string, ejemplos: string, texto: string, funciones: array<int, array{0: string, 1: string, 2: string}>}>
     */
    public static function lista(): array
    {
        $rubros = [
            'restaurante' => ['icono' => 'fa-utensils', 'nombre' => 'Restaurantes', 'ejemplos' => 'Restaurantes, cevicherías, cafeterías, bares y discotecas',
                'texto' => 'Del pedido en la mesa a la boleta, sin papelitos: el mozo toma la comanda en el celular y la cocina la ve al instante.',
                'funciones' => [
                    ['fa-mobile-screen', 'Comandas en celular o tablet', 'El mozo envía el pedido y la cocina o el bar lo imprime o lo ve en pantalla.'],
                    ['fa-table-cells', 'Mesas por piso o zona', 'Pasar a otra mesa, juntar mesas para grupos y precuenta.'],
                    ['fa-fire-burner', 'Pantalla de cocina', 'Pedidos por preparar y aviso al mozo cuando el plato está listo.'],
                    ['fa-qrcode', 'Carta digital con QR', 'Tus clientes ven la carta con fotos y precios desde su celular.'],
                    ['fa-scale-balanced', 'Recetas y food cost', 'Cuánto te cuesta cada plato, insumos que descuenta y mermas.'],
                    ['fa-motorcycle', 'Delivery y para llevar', 'Pedidos con motorizado, reservas y cuentas separadas.'],
                ]],
            'hotel' => ['icono' => 'fa-bed', 'nombre' => 'Hoteles', 'ejemplos' => 'Hoteles, hospedajes y hostales (con o sin restaurante)',
                'texto' => 'Controla cada habitación con su tiempo, sus consumos y su cobro, y recibe alertas antes de que venza la estadía.',
                'funciones' => [
                    ['fa-door-open', 'Habitaciones en tiempo real', 'Libre, ocupada o en limpieza, con el tiempo que le queda a cada una.'],
                    ['fa-calendar-check', 'Reservas por fecha', 'El sistema avisa si una reserva choca con una estadía.'],
                    ['fa-bell-concierge', 'Consumos a la habitación', 'Agrega productos o servicios y cobra todo junto al salir.'],
                    ['fa-right-left', 'Cambio de habitación', 'Mueve al huésped con todos sus consumos.'],
                    ['fa-chart-line', 'Reporte con gráficos', 'Ocupación, ventas por habitación y bitácora de cambios.'],
                    ['fa-user-shield', 'Control del administrador', 'Quitar consumos o cambiar precios pide autorización.'],
                ]],
            'comercio' => ['icono' => 'fa-store', 'nombre' => 'Comercios', 'ejemplos' => 'Bodegas, minimarkets, ferreterías, distribuidoras y tiendas',
                'texto' => 'Vende rápido desde la caja o desde el celular, con tu stock siempre al día en todos tus almacenes.',
                'funciones' => [
                    ['fa-cash-register', 'Punto de venta táctil', 'Vende con clics o con lector de código de barras.'],
                    ['fa-mobile', 'Vende desde el celular', 'Punto de venta móvil para vender en ruta o en el mostrador.'],
                    ['fa-boxes-stacked', 'Inventario y kardex', 'Varios almacenes, transferencias, inventarios físicos y stock mínimo.'],
                    ['fa-file-signature', 'Proformas', 'Cotiza y conviértela en venta con un clic.'],
                    ['fa-truck', 'Guías de remisión', 'Guías electrónicas para tus traslados y envíos.'],
                    ['fa-hand-holding-dollar', 'Créditos y cobranzas', 'Ventas al crédito con cuentas por cobrar y por pagar.'],
                ]],
            'farmacia' => ['icono' => 'fa-prescription-bottle-medical', 'nombre' => 'Farmacias', 'ejemplos' => 'Farmacias y boticas',
                'texto' => 'Vende por lote y controla los vencimientos para no perder mercadería.',
                'funciones' => [
                    ['fa-pills', 'Punto de venta de farmacia', 'Pensado para atender rápido en mostrador.'],
                    ['fa-calendar-xmark', 'Lotes y vencimientos', 'Cada lote con su fecha y alertas de lo que está por vencer.'],
                    ['fa-boxes-stacked', 'Kardex por producto', 'Entradas, salidas y stock de cada medicamento.'],
                    ['fa-file-excel', 'Carga desde Excel', 'Sube tu catálogo de productos de una sola vez.'],
                ]],
            'grifo' => ['icono' => 'fa-gas-pump', 'nombre' => 'Grifos', 'ejemplos' => 'Grifos y estaciones de servicio',
                'texto' => 'Un punto de venta hecho para la isla: rápido, con créditos para tus clientes frecuentes.',
                'funciones' => [
                    ['fa-gas-pump', 'Punto de venta de grifo', 'Vende combustible y productos en segundos.'],
                    ['fa-users', 'Clientes al crédito', 'Cuentas por cobrar y reporte por cliente.'],
                    ['fa-truck', 'Guías de remisión', 'Para tus despachos.'],
                    ['fa-chart-column', 'Reportes de ventas', 'Por turno, producto y forma de pago.'],
                ]],
            'gimnasio' => ['icono' => 'fa-dumbbell', 'nombre' => 'Gimnasios', 'ejemplos' => 'Gimnasios y academias',
                'texto' => 'Membresías, cobros y control de ingreso para que nadie entre con el plan vencido.',
                'funciones' => [
                    ['fa-id-card', 'Membresías y planes', 'Mensuales, trimestrales o como los vendas.'],
                    ['fa-door-closed', 'Control de ingreso', 'Valida en la puerta si el socio está al día.'],
                    ['fa-person-running', 'Pantalla del entrenador', 'Sus alumnos y sus rutinas.'],
                    ['fa-cash-register', 'Venta de productos', 'Suplementos y bebidas con su boleta.'],
                ]],
            'estacionamiento' => ['icono' => 'fa-square-parking', 'nombre' => 'Estacionamientos', 'ejemplos' => 'Playas de estacionamiento, cocheras y valet parking',
                'texto' => 'Registra entradas y salidas, cobra por tiempo y administra a tus abonados.',
                'funciones' => [
                    ['fa-ticket', 'Tickets de entrada y salida', 'Cobro automático según el tiempo.'],
                    ['fa-users', 'Abonados mensuales', 'Clientes fijos con su vencimiento.'],
                    ['fa-qrcode', 'Valet parking con QR', 'El cliente pide su vehículo desde su celular.'],
                    ['fa-chart-line', 'Reporte de ingresos', 'Por día, por turno y por tipo de vehículo.'],
                ]],
            'club' => ['icono' => 'fa-people-group', 'nombre' => 'Clubes', 'ejemplos' => 'Clubes, asociaciones y colegios profesionales',
                'texto' => 'Socios, cuotas y carnet digital, con un portal donde cada socio ve su estado de cuenta.',
                'funciones' => [
                    ['fa-address-card', 'Padrón de socios', 'Datos, familiares y estado de cada socio.'],
                    ['fa-coins', 'Cuotas y pagos', 'Cobros con su comprobante y deudas al día.'],
                    ['fa-qrcode', 'Carnet con QR', 'En la portería se verifica si está al día.'],
                    ['fa-user-check', 'Portal del socio', 'Cada socio ve sus pagos y su carnet.'],
                ]],
            'clinica' => ['icono' => 'fa-stethoscope', 'nombre' => 'Clínicas', 'ejemplos' => 'Clínicas, consultorios y odontología',
                'texto' => 'La historia del paciente, sus citas y su cobro en un solo lugar.',
                'funciones' => [
                    ['fa-notes-medical', 'Historia clínica', 'Atenciones de cada paciente, lista para imprimir.'],
                    ['fa-calendar-days', 'Agenda de citas', 'Organiza los turnos de cada profesional.'],
                    ['fa-prescription', 'Recetas y consentimientos', 'Se generan e imprimen desde la atención.'],
                    ['fa-cash-register', 'Cobro con comprobante', 'Boleta o factura de cada atención.'],
                ]],
            'prestamos' => ['icono' => 'fa-hand-holding-dollar', 'nombre' => 'Préstamos', 'ejemplos' => 'Préstamos diarios ("gota a gota"), semanales o mensuales',
                'texto' => 'Presta, cobra en ruta desde el celular y sabe al instante quién está al día y quién debe.',
                'funciones' => [
                    ['fa-id-card', 'Clientes con DNI', 'Ficha de cada cliente con su historial y su calificación.'],
                    ['fa-calculator', 'Simulador de préstamo', 'Interés, número de cuotas y cronograma sin domingos ni feriados.'],
                    ['fa-motorcycle', 'Cobranza en ruta', 'El cobrador ve sus clientes del día en el celular y cobra ahí mismo.'],
                    ['fa-triangle-exclamation', 'Mora y renovaciones', 'Atrasos, mora por día y renovación descontando el saldo.'],
                    ['fa-chart-pie', 'Panel y reportes', 'Lo prestado, lo cobrado, la ganancia y los deudores.'],
                    ['fa-receipt', 'Recibo y tarjeta de pagos', 'Comprobante de cada cobro para el cliente.'],
                ]],
            'colegio' => ['icono' => 'fa-school', 'nombre' => 'Colegios', 'ejemplos' => 'Colegios, academias y nidos',
                'texto' => 'Matrículas y pensiones al día: sabe quién debe, cobra varios meses de una vez y entrega su boleta o factura.',
                'funciones' => [
                    ['fa-user-graduate', 'Matrícula en un paso', 'Al matricular se generan la inscripción, la matrícula y todas las pensiones del año.'],
                    ['fa-people-roof', 'Hermanos y referidos', 'Descuento automático en la pensión del 2° y 3° alumno de la familia.'],
                    ['fa-forward', 'Pago adelantado', 'Pagan varios meses juntos y se les descuenta.'],
                    ['fa-user-tie', 'Padres y apoderado', 'Al cobrar eliges quién paga y a nombre de quién sale el comprobante.'],
                    ['fa-gift', 'Sorteo de pensión', 'Premia a los padres más puntuales con una pensión gratis.'],
                    ['fa-triangle-exclamation', 'Reporte de morosos', 'Quién debe 1, 2 o más meses, con recordatorio por WhatsApp.'],
                ]],
            'courier' => ['icono' => 'fa-truck-fast', 'nombre' => 'Courier y envíos', 'ejemplos' => 'Encomiendas, paquetería y carga entre agencias',
                'texto' => 'Registra cada encomienda, cóbrala en origen o al recoger y que tu cliente siga su envío en línea.',
                'funciones' => [
                    ['fa-scale-balanced', 'Tarifa por ruta', 'Por peso real o volumétrico, sobres, reparto a domicilio y seguro.'],
                    ['fa-qrcode', 'Guía con QR', 'Etiqueta para el paquete y comprobante del remitente en tu ticketera.'],
                    ['fa-location-crosshairs', 'Rastreo en línea', 'El cliente ve dónde está su envío con su número de guía.'],
                    ['fa-key', 'Clave de recojo', 'Solo entrega quien tiene la clave de 4 dígitos del remitente.'],
                    ['fa-hand-holding-dollar', 'Paga en origen o destino', 'Boleta o factura al enviar o al recoger.'],
                    ['fa-warehouse', 'Varias agencias', 'Cada agencia despacha, recibe y entrega lo suyo.'],
                ]],
            'avicola' => ['icono' => 'fa-pollo', 'nombre' => 'Avícola', 'ejemplos' => 'Distribuidoras de pollo vivo, pollerías y granjas',
                'texto' => 'Vende pollo por peso con sus jabas y el precio del día, y sabe cuánto te debe cada cliente.',
                'funciones' => [
                    ['fa-scale-balanced', 'Venta por peso', 'Pesadas con jabas: el sistema descuenta la tara y calcula el total.'],
                    ['fa-tags', 'Precio del día', 'Cambias el precio una vez y todas las ventas lo usan.'],
                    ['fa-box-open', 'Control de jabas', 'Cuántas jabas lleva y debe devolver cada cliente.'],
                    ['fa-hand-holding-dollar', 'Cuenta de cada cliente', 'Ventas al crédito, abonos y saldo al día.'],
                    ['fa-scissors', 'Pollería', 'Beneficio del pollo y venta por partes.'],
                    ['fa-chart-line', 'Mermas y reportes', 'Mortalidad, merma de peso y lo ganado por día.'],
                ]],
            'contabilidad' => ['icono' => 'fa-calculator', 'nombre' => 'Contabilidad', 'ejemplos' => 'Estudios contables y empresas que llevan su contabilidad',
                'texto' => 'Lleva la contabilidad, el SIRE y la planilla de tus empresas en el mismo sistema.',
                'funciones' => [
                    ['fa-book', 'Libros contables', 'Plan contable, libro diario, mayor, balance y estados financieros.'],
                    ['fa-landmark', 'SIRE de SUNAT', 'Propuestas RVIE y RCE, y resumen tributario del mes.'],
                    ['fa-file-signature', 'Planilla', 'Trabajadores, planillas y boletas de pago.'],
                    ['fa-user-clock', 'Asistencia', 'Marcación, turnos y tareo de tu personal.'],
                    ['fa-file-export', 'Exporta a CONCAR', 'Pasa tus asientos sin volver a digitarlos.'],
                ]],
        ];
        // Rubros del sistema (los mismos de la demo): si se agrega uno nuevo y aún no tiene texto aquí, sale igual con sus datos
        $equivalencias = ['RESTOBAR' => 'restaurante', 'HOTELERIA' => 'hotel', 'GENERAL' => 'comercio', 'FARMACIA' => 'farmacia', 'GRIFO' => 'grifo',
            'GIMNASIO' => 'gimnasio', 'ESTACIONAMIENTO' => 'estacionamiento', 'CLUB' => 'club', 'CLINICA' => 'clinica', 'PRESTAMOS' => 'prestamos', 'COLEGIO' => 'colegio', 'COURIER' => 'courier', 'CONTABLE' => 'contabilidad'];
        foreach (Rubros::RUBROS as $claveRubro => $rubroSistema) {
            $clave = $equivalencias[$claveRubro] ?? mb_strtolower($claveRubro);
            if ($claveRubro === 'COMPLETO' || isset($rubros[$clave])) {
                continue;
            }
            $rubros[$clave] = ['icono' => $rubroSistema['icono'], 'nombre' => $rubroSistema['nombre'], 'ejemplos' => $rubroSistema['ejemplos'],
                'texto' => 'Todo lo de tu negocio en un solo sistema, con facturación electrónica, caja y reportes.',
                'funciones' => [['fa-check', $rubroSistema['nombre'], $rubroSistema['ejemplos']], ['fa-file-invoice', 'Facturación electrónica', 'Boletas y facturas a SUNAT.'],
                    ['fa-cash-register', 'Caja y reportes', 'Apertura, cierre y reportes de ventas.']]];
        }

        return $rubros;
    }
}
