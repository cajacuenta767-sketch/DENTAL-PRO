<?php

/*
|--------------------------------------------------------------------------
| CONTROL · licencias centrales de la agencia
|--------------------------------------------------------------------------
|
| DENTAL-PRO se activa con una clave CTL-XXXX-XXXX-XXXX-XXXX emitida desde
| CONTROL y recibe un token firmado (Ed25519) que se verifica sin internet
| con la clave pública. Ver docs/licencia.md.
|
*/

return [

    // Si es false (desarrollo y pruebas) no se exige licencia en ninguna ruta.
    'activo' => (bool) env('CONTROL_ACTIVO', false),

    // URL base de CONTROL, sin barra final.
    'url' => rtrim((string) env('CONTROL_URL', 'https://control.tuagencia.com'), '/'),

    // Clave de licencia. También puede registrarse desde la pantalla /licencia
    // (o con `php artisan licencia:activar`), en cuyo caso queda en `archivo`.
    'licencia' => env('CONTROL_LICENCIA'),

    // Clave pública Ed25519 de CONTROL en base64 (32 bytes crudos). Se obtiene
    // una sola vez con GET {CONTROL_URL}/api/v1/licencias/clave-publica.
    'clave_publica' => env('CONTROL_CLAVE_PUBLICA'),

    // Código de este producto en el catálogo de CONTROL.
    'producto' => 'dental-pro',

    // Identificador del equipo o dominio al que se ata la licencia. Si es null:
    // en localhost/127.0.0.1 (escritorio) se usa sha256(hostname) recortado;
    // en un servidor, el dominio de APP_URL.
    'huella' => env('CONTROL_HUELLA'),

    // Dónde se guardan la clave, el último token válido y la info del latido.
    'archivo' => storage_path('app/control/licencia.json'),

    // Nombres de ruta (patrones fnmatch) o rutas de URL que nunca se bloquean.
    'rutas_libres' => [
        'login', 'login.*', 'logout',
        'password.*', 'register',
        'verification.*', 'social.*',
        'licencia.*', 'publico.inicio',
        '/', 'up', 'build/*',
    ],

    // Nombres de ruta (patrones fnmatch) o rutas de URL que se bloquean cuando
    // la licencia no es válida. El resto pasa.
    'rutas_protegidas' => [
        'admin.*', 'portal.*', 'reservas.*', 'api.*', 'api/*',
    ],

    // Cuando el token guardado expira en menos de estos días, se intenta un latido.
    'renovar_dias' => 2,

    // Segundos mínimos entre latidos automáticos disparados por las peticiones.
    'latido_cada' => 3600,

];
