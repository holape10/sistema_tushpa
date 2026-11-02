<?php

// Contrato de servicio que firman las empresas clientes con el dueño del sistema (HOLAPE E.I.R.L. · TUSHPA)
return [
    'proveedor' => [
        'razon_social' => env('CONTRATO_RAZON_SOCIAL', 'HOLAPE E.I.R.L.'),
        'ruc' => env('CONTRATO_RUC', '20614580063'),
        'direccion' => env('CONTRATO_DIRECCION', 'JR. RAUL PILLCO PEREZ NRO. 285'),
        'representante' => env('CONTRATO_REPRESENTANTE', 'XIMENA MISCHELLE TANGOA NUÑEZ DE HUANAQUIRI'),
        'dni_representante' => env('CONTRATO_DNI_REPRESENTANTE', '47664500'),
        'marca' => 'TUSHPA',
        'web' => 'www.tushpa.app',
    ],
    // Logo del encabezado del contrato (dentro de public/)
    'logo' => 'imagenes/holape-logo.png',
    // Archivos que sube el cliente (copia del DNI y contrato escaneado): fuera de public/, solo los ve el panel
    'carpeta' => 'contratos',
    'max_kb' => 8192,
];
