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
            'acciones' => ['ver', 'editar', 'reservas'],
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
            'acciones' => ['ver', 'todos'],
            'grupo' => 'Clínica',
        ],
        'lista_espera' => [
            'etiqueta' => 'Lista de espera',
            'icono' => 'ti ti-hourglass-high',
            'ruta' => 'admin.lista-espera.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
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
        'periodontogramas' => [
            'etiqueta' => 'Periodontograma',
            'icono' => 'ti ti-dental',
            'ruta' => null,
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Clínica',
            'oculto_en_menu' => true,
        ],
        'aseguradoras' => [
            'etiqueta' => 'Aseguradoras',
            'icono' => 'ti ti-shield-heart',
            'ruta' => 'admin.aseguradoras.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Catálogos',
        ],
        'imagenologia' => [
            'etiqueta' => 'Imagenología',
            'icono' => 'ti ti-photo-scan',
            'ruta' => 'admin.estudios.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'descargar'],
            'grupo' => 'Clínica',
        ],
        'laboratorio' => [
            'etiqueta' => 'Laboratorio Dental',
            'icono' => 'ti ti-flask',
            'ruta' => 'admin.laboratorio.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'estado'],
            'grupo' => 'Clínica',
        ],
        'vademecum' => [
            'etiqueta' => 'Vademécum Dental',
            'icono' => 'ti ti-pill',
            'ruta' => 'admin.vademecum.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Catálogos',
        ],
        'documentos' => [
            'etiqueta' => 'Recetas y Certificados',
            'icono' => 'ti ti-prescription',
            'ruta' => 'admin.documentos.index',
            'acciones' => ['ver', 'crear', 'editar', 'anular'],
            'grupo' => 'Clínica',
        ],
        'presupuestos' => [
            'etiqueta' => 'Presupuestos',
            'icono' => 'ti ti-file-invoice',
            'ruta' => 'admin.presupuestos.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'aprobar', 'ejecutar'],
            'grupo' => 'Finanzas',
        ],
        'inventario' => [
            'etiqueta' => 'Inventario',
            'icono' => 'ti ti-package',
            'ruta' => 'admin.inventario.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'movimientos'],
            'grupo' => 'Operación',
        ],
        'facturacion' => [
            'etiqueta' => 'Facturación',
            'icono' => 'ti ti-receipt-tax',
            'ruta' => 'admin.facturacion.index',
            'acciones' => ['ver', 'emitir', 'anular'],
            'grupo' => 'Finanzas',
        ],
        'pagos' => [
            'etiqueta' => 'Caja y Pagos',
            'icono' => 'ti ti-cash-register',
            'ruta' => 'admin.pagos.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
            'grupo' => 'Finanzas',
        ],
        'caja' => [
            'etiqueta' => 'Cierre de caja',
            'icono' => 'ti ti-cash-banknote',
            'ruta' => 'admin.caja.index',
            'acciones' => ['ver', 'cerrar'],
            'grupo' => 'Finanzas',
        ],
        'reportes' => [
            'etiqueta' => 'Reportes',
            'icono' => 'ti ti-chart-histogram',
            'ruta' => 'admin.reportes.index',
            'acciones' => ['ver', 'exportar'],
            'grupo' => 'Finanzas',
        ],
        'sucursales' => [
            'etiqueta' => 'Sucursales',
            'icono' => 'ti ti-building-hospital',
            'ruta' => 'admin.sucursales.index',
            'acciones' => ['ver', 'crear', 'editar', 'eliminar'],
            'grupo' => 'Configuración',
        ],
        'respaldos' => [
            'etiqueta' => 'Respaldos',
            'icono' => 'ti ti-database-export',
            'ruta' => 'admin.respaldos.index',
            'acciones' => ['ver', 'crear', 'descargar'],
            'grupo' => 'Configuración',
        ],
        'api' => [
            'etiqueta' => 'API',
            'icono' => 'ti ti-api',
            'ruta' => null,
            'acciones' => ['usar'],
            'grupo' => 'Configuración',
            'oculto_en_menu' => true,
        ],
        'auditoria' => [
            'etiqueta' => 'Auditoría',
            'icono' => 'ti ti-history',
            'ruta' => 'admin.auditoria.index',
            'acciones' => ['ver'],
            'grupo' => 'Configuración',
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
                'home.ver', 'ajustes.ver', 'ajustes.editar', 'auditoria.ver', 'sucursales.*', 'respaldos.*', 'api.usar',
                'pacientes.*', 'especialidades.*', 'tratamientos.*', 'aseguradoras.*',
                'doctores.*', 'horarios.*', 'citas.*', 'agenda.*', 'lista_espera.*',
                'historiales.*', 'odontogramas.*', 'periodontogramas.*', 'imagenologia.*', 'documentos.*',
                'presupuestos.*', 'inventario.*', 'pagos.*', 'caja.*', 'facturacion.*', 'reportes.*',
            ],
        ],

        'DOCTOR' => [
            'descripcion' => 'Atiende su agenda y registra historia clínica y odontograma.',
            'permisos' => [
                'home.ver', 'agenda.ver', 'lista_espera.ver',
                'pacientes.ver', 'pacientes.editar',
                'citas.ver', 'citas.editar', 'citas.atender',
                'historiales.ver', 'historiales.crear', 'historiales.editar',
                'odontogramas.ver', 'odontogramas.crear', 'odontogramas.editar',
                'periodontogramas.ver', 'periodontogramas.crear', 'periodontogramas.editar',
                'imagenologia.ver', 'imagenologia.crear', 'imagenologia.editar', 'imagenologia.descargar',
                'documentos.ver', 'documentos.crear', 'documentos.editar', 'documentos.anular',
                'presupuestos.ver', 'presupuestos.crear', 'presupuestos.editar', 'presupuestos.ejecutar',
                'inventario.ver', 'inventario.movimientos',
                'tratamientos.ver', 'especialidades.ver', 'aseguradoras.ver',
            ],
        ],

        'RECEPCION' => [
            'descripcion' => 'Agenda citas, registra pacientes y cobra en caja.',
            'permisos' => [
                'home.ver', 'agenda.ver', 'agenda.todos', 'lista_espera.*',
                'pacientes.ver', 'pacientes.crear', 'pacientes.editar',
                'citas.ver', 'citas.crear', 'citas.editar', 'citas.confirmar', 'citas.cancelar',
                'doctores.ver', 'horarios.ver', 'tratamientos.ver', 'especialidades.ver',
                'aseguradoras.ver', 'imagenologia.ver', 'imagenologia.crear',
                'presupuestos.ver', 'presupuestos.crear', 'presupuestos.editar',
                'inventario.ver', 'inventario.movimientos',
                'pagos.ver', 'pagos.crear', 'pagos.editar', 'caja.ver', 'caja.cerrar',
                'facturacion.ver', 'facturacion.emitir',
                'reportes.ver', 'periodontogramas.ver',
            ],
        ],

        // Sin permisos de panel: solo entra al portal y ve lo suyo.
        'PACIENTE' => [
            'descripcion' => 'Consulta sus propias citas, documentos y pagos desde el portal.',
            'permisos' => [],
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
