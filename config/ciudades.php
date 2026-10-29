<?php

/*
 * Páginas de SEO local de la web pública (tushpa.app/sistema-de-facturacion-electronica-{ciudad}).
 * Agregar una ciudad = agregar una entrada aquí: la ruta, el sitemap, los enlaces del pie y el middleware la toman solos.
 *
 * REGLAS PARA QUE GOOGLE NO LAS TRATE COMO "PÁGINAS PUERTA" (copias con otra ciudad):
 *  - Cada ciudad lleva texto, rubros y preguntas propios. No copies y pegues cambiando solo el nombre.
 *  - Escribe solo lo que es verdad (si no hay oficina en la ciudad, dilo: la atención es remota).
 *  - Cuando tengas clientes reales en la ciudad (con su permiso), nómbralos dentro de 'parrafos' o en una pregunta:
 *    un caso real posiciona mejor que cualquier texto genérico.
 *
 * ':dias' se reemplaza por config('sitio.dias_prueba'). Título ideal: hasta 60 caracteres; descripción: hasta 155.
 */
return [

    'iquitos' => [
        'nombre' => 'Iquitos',
        'region' => 'Loreto',
        'sede' => true,
        'titulo' => 'Sistema de Facturación Electrónica en Iquitos | TUSHPA',
        'descripcion' => 'Facturación electrónica SUNAT, comandas, hotel e inventario para negocios de Iquitos. Oficina en Loreto y soporte directo. Prueba gratis :dias días.',
        'h1' => 'Sistema de facturación electrónica en Iquitos',
        'bajada' => 'Boletas y facturas SUNAT, comandas para tu restaurante, control de hotel e inventario en un solo sistema. Hecho en Iquitos, con atención directa en nuestra oficina.',
        'parrafos' => [
            'Iquitos vive del turismo amazónico, la gastronomía regional, los hospedajes, el comercio y el transporte, y todos esos negocios tienen la misma necesidad: emitir comprobantes electrónicos sin complicarse y saber cuánto venden cada día. TUSHPA reúne en un solo sistema la facturación a SUNAT, el punto de venta, el inventario y la caja, y funciona desde el navegador de tu PC, tablet o celular.',
            'Si tu negocio opera con exoneración del IGV de la Amazonía, el sistema tiene la opción "Exonerado de IGV (Amazonía)" y maneja operaciones exoneradas en tus comprobantes y reportes. Para saber qué régimen te corresponde, consúltalo siempre con tu contador.',
        ],
        'rubros' => [
            ['fa-utensils', 'Restaurantes y cevicherías', 'El mozo toma la comanda en el celular, la cocina la ve al instante y la boleta sale en segundos, aun en horas de mucho público.'],
            ['fa-bed', 'Hoteles y hospedajes', 'Habitaciones con su tiempo, consumos y cobro, y alertas antes de que venza cada estadía.'],
            ['fa-store', 'Comercios y bodegas', 'Punto de venta con lector de código de barras, varios almacenes y créditos a clientes frecuentes.'],
            ['fa-gas-pump', 'Grifos', 'Punto de venta pensado para la isla, con cuentas por cobrar para tus clientes frecuentes.'],
        ],
        'atencion' => [
            'titulo' => 'Estamos en Iquitos',
            'texto' => 'Nuestra oficina está en Jr. Raúl Pillco Pérez 285, Iquitos. Puedes visitarnos o escribirnos por WhatsApp al +51 928 396 147, de lunes a viernes de 9:00 a. m. a 6:00 p. m. Te ayudamos a cargar tus productos, configurar tus impresoras y emitir tu primer comprobante.',
        ],
        'preguntas' => [
            ['¿Tienen oficina en Iquitos?', 'Sí. Estamos en Jr. Raúl Pillco Pérez 285, Iquitos. También te atendemos por WhatsApp.'],
            ['¿Sirve para negocios exonerados del IGV de la Amazonía?', 'El sistema tiene la opción "Exonerado de IGV (Amazonía)" y maneja operaciones exoneradas. Confirma con tu contador qué régimen te corresponde.'],
            ['¿Puedo emitir boletas y facturas desde el celular?', 'Sí. TUSHPA funciona desde el navegador en PC, tablet o celular, y tiene punto de venta móvil para vender en ruta o en el mostrador.'],
            ['¿Necesito internet para usarlo?', 'Sí. El sistema trabaja en línea para enviar tus comprobantes a SUNAT y tener tus datos al día en todos tus equipos.'],
            ['¿Cuánto cuesta el sistema de facturación en Iquitos?', 'Pruebas gratis :dias días, sin tarjeta, y luego eliges un plan mensual. Los planes y precios están en la sección Planes de nuestra portada.'],
        ],
    ],

    'piura' => [
        'nombre' => 'Piura',
        'region' => 'Piura',
        'sede' => false,
        // Partner en Piura: su contacto reemplaza al nuestro en esta página y para quien entra a la web desde Piura (ContactoWeb)
        'contacto' => [
            'nombre' => 'Gabriel Ríos',
            'whatsapp' => '51964382212',
            'telefonos' => ['964 382 212', '958 258 047'],
            'correos' => ['gabriel@tushpa.app', 'devsoftsolucionestecnologicas@gmail.com'],
            'direccion' => 'Urb. San Felipe, al costado de la Privada - Piura',
            'lugar' => 'Piura · Perú',
        ],
        'titulo' => 'Sistema de Facturación Electrónica en Piura | TUSHPA',
        'descripcion' => 'Emite boletas y facturas SUNAT, controla stock y caja desde el celular. Atención en Piura, en Urb. San Felipe y por WhatsApp. Prueba gratis :dias días.',
        'h1' => 'Sistema de facturación electrónica en Piura',
        'bajada' => 'Emite boletas y facturas SUNAT, controla tu inventario y tu caja desde cualquier equipo. Te atendemos en Piura, en persona o por WhatsApp, sin instalaciones.',
        'parrafos' => [
            'Piura es una de las plazas comerciales más movidas del norte del país, con comercios, distribuidoras, restaurantes, boticas, grifos y hospedajes que venden todos los días y necesitan comprobantes electrónicos al instante. TUSHPA te da facturación SUNAT, punto de venta, kardex y reportes en un solo sistema, sin comprar un servidor.',
            'Como el sistema es en línea, no instalas programas: creas tu cuenta con tu RUC, cargas tus productos (uno por uno o todos desde Excel) y empiezas a emitir. Tus datos viven en una base de datos propia para tu empresa, separada de la de otros negocios.',
        ],
        'rubros' => [
            ['fa-store', 'Comercios y distribuidoras', 'Varios almacenes, transferencias, stock mínimo, proformas y guías de remisión electrónicas para tus despachos.'],
            ['fa-prescription-bottle-medical', 'Farmacias y boticas', 'Venta por lote, alertas de vencimiento y kardex por producto, con tu catálogo cargado desde Excel.'],
            ['fa-gas-pump', 'Grifos', 'Punto de venta de grifo con clientes al crédito y reporte de ventas por turno.'],
            ['fa-utensils', 'Restaurantes', 'Comandas desde el celular, mesas por zona y carta digital con QR.'],
        ],
        'atencion' => [
            'titulo' => 'Estamos en Piura',
            'texto' => 'Te atiende Gabriel Ríos en Urb. San Felipe, al costado de la Privada, Piura, por WhatsApp al 964 382 212 o al 958 258 047 y por correo a gabriel@tushpa.app. Te acompañamos a cargar tus productos, configurar tu impresora de tickets y emitir tu primer comprobante.',
        ],
        'preguntas' => [
            ['¿Atienden negocios de Piura?', 'Sí. Te atendemos en Piura, en Urb. San Felipe (al costado de la Privada), y por WhatsApp al 964 382 212 o al 958 258 047.'],
            ['¿Puedo cargar mi catálogo de productos desde Excel?', 'Sí. Puedes subir todos tus productos de una sola vez desde un archivo de Excel y empezar a vender.'],
            ['¿Sirve para boticas con control de lotes y vencimientos?', 'Sí. Cada lote lleva su fecha de vencimiento y el sistema te avisa lo que está por vencer.'],
            ['¿Emite guías de remisión electrónicas?', 'Sí. Puedes emitir guías de remisión electrónicas para tus traslados y envíos desde el mismo sistema.'],
        ],
    ],

    'tarapoto' => [
        'nombre' => 'Tarapoto',
        'region' => 'San Martín',
        'sede' => false,
        'titulo' => 'Sistema de Facturación Electrónica en Tarapoto | TUSHPA',
        'descripcion' => 'Facturación electrónica SUNAT para restaurantes, hoteles y comercios de Tarapoto. Atención por WhatsApp y prueba gratis de :dias días, sin tarjeta.',
        'h1' => 'Sistema de facturación electrónica en Tarapoto',
        'bajada' => 'Factura a SUNAT y controla tu restaurante, tu hospedaje o tu tienda desde un solo sistema. Atención por WhatsApp para negocios de Tarapoto y San Martín.',
        'parrafos' => [
            'Tarapoto recibe turistas todo el año y tiene una cocina regional muy conocida, además de un comercio activo en toda la región San Martín. Para los restaurantes, hospedajes y tiendas de la ciudad, TUSHPA junta en un solo sistema la facturación electrónica SUNAT, las comandas, el control de habitaciones y el inventario.',
            'Puedes tener varias sucursales, cada una con su caja, su almacén y sus series de comprobantes, y dar a cada trabajador solo las opciones que necesita. Tus clientes reciben su comprobante por WhatsApp y pueden descargarlo desde tu propia página de consulta.',
        ],
        'rubros' => [
            ['fa-utensils', 'Restaurantes y cafeterías', 'Comandas en celular, pantalla de cocina, recetas con costo por plato y delivery con motorizado.'],
            ['fa-bed', 'Hoteles y hospedajes', 'Reservas por fecha, consumos a la habitación y cobro conjunto al salir.'],
            ['fa-store', 'Comercios y minimarkets', 'Punto de venta táctil, inventario con varios almacenes y créditos a clientes.'],
            ['fa-prescription-bottle-medical', 'Farmacias', 'Lotes y vencimientos, con venta rápida en mostrador.'],
        ],
        'atencion' => [
            'titulo' => 'Atención remota para Tarapoto',
            'texto' => 'No tenemos oficina en Tarapoto: nuestra oficina está en Iquitos y te atendemos por WhatsApp (+51 928 396 147) en horario de oficina, para dejar tu sistema funcionando y resolver tus dudas del día a día.',
        ],
        'preguntas' => [
            ['¿Atienden en Tarapoto?', 'Sí, de forma remota por WhatsApp. El sistema funciona desde el navegador, así que no necesitas instalar nada.'],
            ['¿Sirve para hoteles y hospedajes?', 'Sí. Controla cada habitación en tiempo real, con reservas por fecha, consumos y cobro al salir.'],
            ['¿Puedo tener varias sucursales?', 'Sí. Cada local tiene su propia caja, su almacén y sus series de comprobantes.'],
            ['¿Mis datos están separados de los de otras empresas?', 'Sí. Cada empresa tiene su propia base de datos y sus propios usuarios, con los permisos que tú des.'],
        ],
    ],

    'moyobamba' => [
        'nombre' => 'Moyobamba',
        'region' => 'San Martín',
        'sede' => false,
        'titulo' => 'Sistema de Facturación Electrónica en Moyobamba | TUSHPA',
        'descripcion' => 'Boletas y facturas electrónicas SUNAT para hospedajes, restaurantes y comercios de Moyobamba. Atención por WhatsApp. Prueba gratis :dias días.',
        'h1' => 'Sistema de facturación electrónica en Moyobamba',
        'bajada' => 'Boletas, facturas y control de tu negocio en un solo sistema, para hospedajes, restaurantes, comercios y consultorios de Moyobamba.',
        'parrafos' => [
            'Moyobamba, la ciudad de las orquídeas, vive del turismo, el comercio y los servicios de la región San Martín. Si tienes un hospedaje, un restaurante, una tienda o un consultorio, necesitas emitir tus comprobantes electrónicos a tiempo y tener tus cuentas claras. TUSHPA lo hace desde el navegador, sin comprar equipos especiales.',
            'Puedes emitir boletas, facturas, notas de crédito y débito, notas de venta y guías de remisión electrónicas, enviarlas por WhatsApp e imprimirlas en ticket o en A4. Al final del mes tienes los reportes listos para tu contador.',
        ],
        'rubros' => [
            ['fa-bed', 'Hospedajes y hoteles', 'Habitaciones, reservas y consumos, con alertas antes de que venza la estadía.'],
            ['fa-utensils', 'Restaurantes', 'Comandas en celular, mesas, carta digital con QR y boletas al instante.'],
            ['fa-store', 'Comercios y bodegas', 'Venta rápida con lector de código de barras y stock al día.'],
            ['fa-stethoscope', 'Clínicas y consultorios', 'Historia clínica, agenda de citas y cobro con boleta o factura de cada atención.'],
        ],
        'atencion' => [
            'titulo' => 'Atención remota para Moyobamba',
            'texto' => 'No tenemos oficina en Moyobamba: nuestra oficina está en Iquitos y te atendemos por WhatsApp (+51 928 396 147) en horario de oficina, desde la creación de tu cuenta hasta tu primer comprobante.',
        ],
        'preguntas' => [
            ['¿Atienden en Moyobamba?', 'Sí, de forma remota por WhatsApp. Creas tu cuenta con tu RUC y te acompañamos en la configuración.'],
            ['¿Qué comprobantes puedo emitir?', 'Boletas, facturas, notas de crédito y débito, notas de venta y guías de remisión electrónicas.'],
            ['¿Funciona desde el celular?', 'Sí. Funciona en PC, tablet y celular desde el navegador, y puedes instalarlo como app con un clic.'],
            ['¿Hay prueba gratis?', 'Sí, :dias días gratis y sin tarjeta. Durante la prueba los comprobantes van a SUNAT en modo de prueba, para que aprendas sin riesgo.'],
        ],
    ],

];
