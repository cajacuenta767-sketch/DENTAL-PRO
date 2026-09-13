<?php

return [

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Módulos del sistema
    |--------------------------------------------------------------------------
    |
    | Única fuente de verdad para la barra de navegación, la pantalla de roles
    | y el seeder de permisos. Cada módulo genera un permiso por acción con el
    | formato "<modulo>.<accion>".
    |
    */

    'modulos' => [
        'home' => [
            'etiqueta' => 'Home',
            'icono' => 'ti ti-home',
            'ruta' => 'admin.home',
            'acciones' => ['ver'],
            'grupo' => 'General',
        ],
        'ajustes' => [
            'etiqueta' => 'Ajustes',
            'icono' => 'ti ti-settings',
            'ruta' => 'admin.ajustes.edit',
            'acciones' => ['ver', 'editar'],
            'grupo' => 'Configuración',
        ],
        'roles' => [
            'etiqueta' => 'Roles',
            'icono' => 'ti ti-shield-lock',
            'ruta' => 'admin.roles.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Configuración',
        ],
        'usuarios' => [
            'etiqueta' => 'Usuarios',
            'icono' => 'ti ti-users',
            'ruta' => 'admin.usuarios.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Configuración',
        ],
        'pacientes' => [
            'etiqueta' => 'Pacientes',
            'icono' => 'ti ti-user-heart',
            'ruta' => 'admin.pacientes.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
        ],
        'especialidades' => [
            'etiqueta' => 'Especialidades',
            'icono' => 'ti ti-stethoscope',
            'ruta' => 'admin.especialidades.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Catálogos',
        ],
        'tratamientos' => [
            'etiqueta' => 'Tratamientos',
            'icono' => 'ti ti-dental',
            'ruta' => 'admin.tratamientos.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Catálogos',
        ],
        'doctores' => [
            'etiqueta' => 'Doctores',
            'icono' => 'ti ti-user-check',
            'ruta' => 'admin.doctores.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
        ],
        'horarios' => [
            'etiqueta' => 'Horarios',
            'icono' => 'ti ti-clock-hour-4',
            'ruta' => 'admin.horarios.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
        ],
        'citas' => [
            'etiqueta' => 'Citas',
            'icono' => 'ti ti-calendar-event',
            'ruta' => 'admin.citas.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'confirmar', 'cancelar', 'atender'],
            'grupo' => 'Clínica',
        ],
        'agenda' => [
            'etiqueta' => 'Mi Agenda',
            'icono' => 'ti ti-calendar-user',
            'ruta' => 'admin.agenda.index',
            'acciones' => ['ver'],
            'grupo' => 'Clínica',
        ],
        'historiales' => [
            'etiqueta' => 'Historia Clínica',
            'icono' => 'ti ti-notes-medical',
            'ruta' => null,
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
            'oculto_en_menu' => true,
        ],
        'odontogramas' => [
            'etiqueta' => 'Odontograma',
            'icono' => 'ti ti-dental-broken',
            'ruta' => null,
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
            'oculto_en_menu' => true,
        ],
        'pagos' => [
            'etiqueta' => 'Caja y Pagos',
            'icono' => 'ti ti-cash-register',
            'ruta' => 'admin.pagos.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
            'grupo' => 'Finanzas',
        ],
        'reportes' => [
            'etiqueta' => 'Reportes',
            'icono' => 'ti ti-chart-histogram',
            'ruta' => 'admin.reportes.index',
            'acciones' => ['ver', 'exportar'],
            'grupo' => 'Finanzas',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles predefinidos
    |--------------------------------------------------------------------------
    |
    | "*" concede todos los permisos. El seeder crea estos roles y les asigna
    | los permisos listados; los administradores pueden ajustarlos después
    | desde el módulo de Roles.
    |
    */

    'roles' => [
        'SUPER ADMINISTRADOR' => ['descripcion' => 'Control total del sistema.', 'permisos' => '*'],

        'ADMINISTRADOR' => [
            'descripcion' => 'Gestiona la clínica sin tocar roles ni usuarios del sistema.',
            'permisos' => [
                'home.ver', 'ajustes.ver', 'ajustes.editar',
                'pacientes.*', 'especialidades.*', 'tratamientos.*',
                'doctores.*', 'horarios.*', 'citas.*', 'agenda.ver',
                'historiales.*', 'odontogramas.*', 'pagos.*', 'reportes.*',
            ],
        ],

        'DOCTOR' => [
            'descripcion' => 'Atiende su agenda y registra historia clínica y odontograma.',
            'permisos' => [
                'home.ver', 'agenda.ver',
                'pacientes.ver', 'pacientes.editar',
                'citas.ver', 'citas.editar', 'citas.atender',
                'historiales.ver', 'historiales.crear', 'historiales.editar',
                'odontogramas.ver', 'odontogramas.crear', 'odontogramas.editar',
                'tratamientos.ver', 'especialidades.ver',
            ],
        ],

        'RECEPCION' => [
            'descripcion' => 'Agenda citas, registra pacientes y cobra en caja.',
            'permisos' => [
                'home.ver',
                'pacientes.ver', 'pacientes.crear', 'pacientes.editar',
                'citas.ver', 'citas.crear', 'citas.editar', 'citas.confirmar', 'citas.cancelar',
                'doctores.ver', 'horarios.ver', 'tratamientos.ver', 'especialidades.ver',
                'pagos.ver', 'pagos.crear', 'pagos.editar',
                'reportes.ver',
            ],
        ],

        'PACIENTE' => [
            'descripcion' => 'Consulta sus propias citas desde el portal.',
            'permisos' => ['citas.ver'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parámetros clínicos
    |--------------------------------------------------------------------------
    */

    'dias_semana' => [
        'LUNES' => 'Lunes',
        'MARTES' => 'Martes',
        'MIERCOLES' => 'Miércoles',
        'JUEVES' => 'Jueves',
        'VIERNES' => 'Viernes',
        'SABADO' => 'Sábado',
        'DOMINGO' => 'Domingo',
    ],
];
