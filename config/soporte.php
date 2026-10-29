<?php

// Datos de contacto del soporte de TUSHPA (los ven todas las empresas, también en el multi-empresa)
return [
    'nombre' => env('SOPORTE_NOMBRE', 'HOLAPE E.I.R.L.'),
    'telefono' => env('SOPORTE_TELEFONO', '928396147'),
    'whatsapp' => env('SOPORTE_WHATSAPP', '51928396147'),   // con código de país, sin "+"
    'correo' => env('SOPORTE_CORREO', 'soporte@tushpa.app'),
    // Correos que se muestran en la página web (contacto y pie); con partner de la ciudad, los suyos
    'correos_web' => ['soporte@tushpa.app', 'jacker@tushpa.app'],
    'direccion' => env('SOPORTE_DIRECCION', 'JR. RAUL PILLCO PEREZ NRO. 285'),
    'horario' => env('SOPORTE_HORARIO', 'Lunes a sábado'),
    // Línea discreta al pie de todos los comprobantes
    'firma_comprobante' => env('SOPORTE_FIRMA', 'Sistema desarrollado por tushpa.app - 928 396 147'),

    // Cuenta para el pago del servicio (se muestra en la pantalla de "Servicio suspendido")
    'pago' => [
        'banco' => env('PAGO_BANCO', 'BBVA'),
        'titular' => env('PAGO_TITULAR', 'HOLAPE E.I.R.L.'),
        'cuenta' => env('PAGO_CUENTA', '0011-0301-0201897749'),
        'cci' => env('PAGO_CCI', '011-301-000201897749-92'),
        'qr' => env('PAGO_QR', 'imagenes/qr-holape.png'),   // ruta dentro de public/
    ],
];
