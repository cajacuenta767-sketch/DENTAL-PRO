<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licencia de uso
    |--------------------------------------------------------------------------
    |
    | El sistema se entrega con un PIN de prueba gratuita y se renueva con
    | PINes que emite el proveedor. Cada instalación tiene un código propio y
    | los PINes de renovación solo sirven para esa instalación.
    |
    | "activa" apaga toda la verificación (útil en desarrollo y pruebas).
    | Si la clave privada está configurada, esa instalación es la del
    | proveedor: nunca se bloquea y puede emitir códigos desde el panel.
    |
    */

    'activa' => (bool) env('LICENCIA_ACTIVA', true),

    // Clave pública Ed25519 (base64) con la que se verifican los códigos de activación.
    'clave_publica' => env('LICENCIA_CLAVE_PUBLICA', '32Moi0l1qp1UiVMS99qHPU82UnefEtJ0+o1Y6l1Tkoo='),

    // Solo en la instalación del proveedor. Nunca la incluyas en una copia entregada.
    'clave_privada' => env('LICENCIA_CLAVE_PRIVADA'),

    // Contacto que ve el cliente para pedir o renovar su PIN.
    'contacto_nombre' => env('LICENCIA_CONTACTO_NOMBRE', 'Soporte OdontoSuite'),
    'contacto_whatsapp' => env('LICENCIA_CONTACTO_WHATSAPP'),

    // Días antes del vencimiento en que aparece el aviso en el panel.
    'aviso_dias' => 3,

    // Un código de activación caduca si no se usa dentro de este plazo.
    'dias_para_activar' => 30,

    // Fecha cero del calendario de licencias y horizonte máximo (20 años).
    'epoca' => '2026-01-01',
    'dias_maximos' => 7300,
];
