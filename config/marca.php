<?php

/*
 * La marca con la que se ve el sistema. Por defecto TUSHPA; en el dominio de una marca blanca (wilcatsystems.pe)
 * App\Support\MarcaBlanca cambia estos valores al entrar, junto con los datos de soporte (config/soporte.php).
 */
return [
    'nombre' => 'TUSHPA',
    'es_tushpa' => true,
    'dominio' => env('TENANCY_DOMINIO'),
    'color' => '#4f46e5',
    'color_secundario' => '#7c3aed',
    // Imágenes dentro de public/
    'logo' => 'imagenes/logo.webp',
    'icono' => 'imagenes/icono.png',
    'icono180' => 'imagenes/180.png',
    'icono192' => 'imagenes/192.png',
    'icono512' => 'imagenes/512.png',
];
