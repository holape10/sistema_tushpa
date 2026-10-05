<?php

// Datos de contacto del soporte de TUSHPA (los ven todas las empresas, también en el multi-empresa)
return [
    'nombre'    => env('SOPORTE_NOMBRE', 'HOLAPE E.I.R.L.'),
    'telefono'  => env('SOPORTE_TELEFONO', '928396147'),
    'whatsapp'  => env('SOPORTE_WHATSAPP', '51928396147'),   // con código de país, sin "+"
    'correo'    => env('SOPORTE_CORREO', 'holapesac@gmail.com'),
    'direccion' => env('SOPORTE_DIRECCION', 'JR. RAUL PILLCO PEREZ NRO. 285'),
    'horario'   => env('SOPORTE_HORARIO', 'Lunes a sábado'),
];
