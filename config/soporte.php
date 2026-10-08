<?php

// Datos de contacto del soporte de TUSHPA (los ven todas las empresas, también en el multi-empresa)
return [
    'nombre' => env('SOPORTE_NOMBRE', 'HOLAPE E.I.R.L.'),
    'telefono' => env('SOPORTE_TELEFONO', '928396147'),
    'whatsapp' => env('SOPORTE_WHATSAPP', '51928396147'),   // con código de país, sin "+"
    'correo' => env('SOPORTE_CORREO', 'holapesac@gmail.com'),
    'direccion' => env('SOPORTE_DIRECCION', 'JR. RAUL PILLCO PEREZ NRO. 285'),
    'horario' => env('SOPORTE_HORARIO', 'Lunes a sábado'),

    // Cuenta para el pago del servicio (se muestra en la pantalla de "Servicio suspendido")
    'pago' => [
        'banco' => env('PAGO_BANCO', 'BBVA'),
        'titular' => env('PAGO_TITULAR', 'HOLAPE E.I.R.L.'),
        'cuenta' => env('PAGO_CUENTA', '0011-0301-0201897749'),
        'cci' => env('PAGO_CCI', '011-301-000201897749-92'),
        'qr' => env('PAGO_QR', 'imagenes/qr-holape.png'),   // ruta dentro de public/
    ],
];
