<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mensajería (WhatsApp / SMS)
    |--------------------------------------------------------------------------
    |
    | Proveedor usado por App\Services\MensajeriaService para los recordatorios
    | de cita: "log" (solo escribe en el log), "twilio" o "meta" (WhatsApp
    | Cloud API). El prefijo de país se antepone a los números de 8 dígitos.
    |
    */

    'mensajeria' => [
        'proveedor' => env('MENSAJERIA_PROVEEDOR', 'log'),
        'prefijo_pais' => env('MENSAJERIA_PREFIJO_PAIS', '591'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'desde_sms' => env('TWILIO_DESDE_SMS'),
            'desde_whatsapp' => env('TWILIO_DESDE_WHATSAPP'),
        ],
        'meta' => [
            'token' => env('META_WHATSAPP_TOKEN'),
            'phone_id' => env('META_WHATSAPP_PHONE_ID'),
        ],
    ],

    'facturacion_electronica' => [
        'proveedor' => env('FACTURACION_PROVEEDOR', 'simulado'),
        'endpoint' => env('FACTURACION_ENDPOINT'),
        'token' => env('FACTURACION_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pasarela de pagos en línea
    |--------------------------------------------------------------------------
    |
    | Proveedor usado por App\Services\PasarelaPagoService para cobrar saldos
    | desde el portal del paciente: "simulado" (sin cobro real, para
    | desarrollo y pruebas) o "stripe" (Checkout + webhook firmado).
    |
    */

    'pasarela' => [
        'proveedor' => env('PASARELA_PROVEEDOR', 'simulado'),
        'stripe' => [
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
