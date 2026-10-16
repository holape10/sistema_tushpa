<?php

/*
 * Web pública en el dominio principal (tushpa.app): información, planes y registro con prueba gratis.
 * Solo se activa con el multi-empresa (TENANCY_DOMINIO).
 */
return [

    // Botón "Ver demo" de la web: empresa de demostración y su acceso
    'demo' => [
        'url' => env('SITIO_DEMO_URL', 'https://demos.tushpa.app'),
        'usuario' => env('SITIO_DEMO_USUARIO', 'DEMO'),
        'clave' => env('SITIO_DEMO_CLAVE', 'DEMO'),
    ],

    // Días de prueba gratis al registrarse desde la web
    'dias_prueba' => (int) env('SITIO_DIAS_PRUEBA', 15),

    // Días después de suspendida (por fin de la prueba) en que el panel la marca como "para eliminar"
    'dias_para_eliminar' => (int) env('SITIO_DIAS_ELIMINAR', 30),

    // Registros permitidos por IP cada 24 horas (evita que alguien cree empresas en serie)
    'registros_por_ip' => (int) env('SITIO_REGISTROS_POR_IP', 3),

    // Cloudflare Turnstile (captcha gratis). Vacío = sin captcha
    'turnstile' => [
        'sitio' => env('TURNSTILE_SITE_KEY'),
        'secreto' => env('TURNSTILE_SECRET_KEY'),
    ],

    // Correo que recibe los mensajes del formulario de contacto (además quedan en el panel). Vacío = solo el panel
    'correo_contacto' => env('SITIO_CORREO_CONTACTO'),

    // Carpeta de los respaldos que se sacan antes de eliminar una empresa
    'respaldos' => storage_path('app/respaldos'),

    // Programa para sacar el respaldo (en el VPS basta "mysqldump"; en Windows/Laragon la ruta completa)
    'mysqldump' => env('MYSQLDUMP_BIN', 'mysqldump'),

    // Programa para restaurar una copia (en el VPS basta "mysql")
    'mysql' => env('MYSQL_BIN', 'mysql'),
];
