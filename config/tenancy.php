<?php

/*
 * Multi-empresa: cada cliente tiene su propia base de datos (bd_{RUC}) y entra por {RUC}.{dominio}.
 * Sin TENANCY_DOMINIO el sistema funciona como siempre, con la base del .env.
 */
return [

    // Dominio de los clientes, ej. tushpa.app => 20614580063.tushpa.app
    'dominio' => env('TENANCY_DOMINIO'),

    // Panel del dueño del sistema: https://{subdominio_admin}.{dominio}/{ruta_admin}
    'subdominio_admin' => env('TENANCY_ADMIN_SUBDOMINIO', 'admin'),
    'ruta_admin' => trim(env('TENANCY_ADMIN_RUTA', 'panel'), '/'),

    // IPs que pueden abrir el panel, separadas por coma (vacío = cualquiera, igual pide usuario y contraseña)
    'admin_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_ADMIN_IPS', ''))))),

    // Subdominios que entran a la base del .env (la empresa dueña del sistema), separados por coma. Ej. "a" => a.tushpa.app
    'principales' => array_values(array_filter(array_map(fn($s) => strtolower(trim($s)), explode(',', (string) env('TENANCY_PRINCIPALES', ''))))),

    // Nombre de la base de cada cliente: {prefijo_bd}{RUC}
    'prefijo_bd' => env('TENANCY_PREFIJO_BD', 'bd_'),

    // Esquema de los enlaces a los clientes (https en producción)
    'esquema' => env('TENANCY_ESQUEMA', 'https'),
];
