<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Consultas DNI (respaldo) y tipo de cambio SUNAT: https://apiperu.dev (token en "Api Tokens" de su panel)
    'apiperu' => [
        'token' => env('APIPERU_TOKEN'),
        'url' => env('APIPERU_URL', 'https://api.apiperu.dev'),
    ],

    // XML de las facturas de compra (con la Clave SOL principal del cliente): https://json.pe (token de su panel)
    'jsonpe' => [
        'token' => env('JSONPE_TOKEN'),
        'url' => env('JSONPE_URL', 'https://api.json.pe/api'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // "Continuar con Google" en tushpa.app (Google One Tap). Vacío = no se muestra
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // WhatsApp Business (API de Meta) del sistema: recordatorios de citas de las clínicas que no pusieron su propia cuenta
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'idioma' => env('WHATSAPP_IDIOMA', 'es'),
        'plantilla_paciente' => env('WHATSAPP_PLANTILLA_PACIENTE', 'recordatorio_cita'),
        'plantilla_doctor' => env('WHATSAPP_PLANTILLA_DOCTOR', 'agenda_doctor'),
    ],

    // Monitor de errores: avisa al Telegram del dueño cuando el sistema falla en alguna empresa. Vacío = solo se anota en el panel
    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    // Cobro en línea del servicio TUSHPA (tarjeta, Yape y billeteras con QR) a las empresas que le pagan al dueño del
    // sistema. Llaves de prueba (pk_test / sk_test) o de producción (pk_live / sk_live) del CulqiPanel → Desarrollo → API Keys
    'culqi' => [
        'publica' => env('CULQI_PUBLIC_KEY'),
        'privada' => env('CULQI_SECRET_KEY'),
    ],

    // Avisos al celular del mozo (Web Push). Las claves las crea: php artisan push:claves
    'push' => [
        'publica' => env('VAPID_PUBLIC_KEY'),
        'privada' => env('VAPID_PRIVATE_KEY'),
        'contacto' => env('VAPID_SUBJECT', 'mailto:soporte@tushpa.app'),
    ],

];
