{{-- Pestañas de la ficha del paciente. $activa: ficha|historial|odontograma|periodontograma|panoramicas|documentos|presupuestos --}}
@php
    $pestanas = [
        'ficha' => ['Ficha del paciente', 'ti ti-id', 'pacientes.ver', route('admin.pacientes.show', $paciente)],
        'historial' => ['Historial clínica', 'ti ti-notes-medical', 'historiales.ver', route('admin.historiales.index', $paciente)],
        'odontograma' => ['Odontograma', 'ti ti-dental', 'odontogramas.ver', route('admin.odontogramas.index', $paciente)],
        'periodontograma' => ['Periodontograma', 'ti ti-dental-broken', 'periodontogramas.ver', route('admin.periodontogramas.index', $paciente)],
        'panoramicas' => ['Panorámicas', 'ti ti-photo-scan', 'imagenologia.ver', route('admin.estudios.paciente', $paciente)],
        'documentos' => ['Recetas y certificados', 'ti ti-prescription', 'documentos.ver', route('admin.documentos.paciente', $paciente)],
        'presupuestos' => ['Presupuestos', 'ti ti-file-invoice', 'presupuestos.ver', route('admin.presupuestos.index', ['buscar' => $paciente->numero_documento])],
    ];
@endphp

<div class="mb-3 d-flex flex-wrap gap-2">
    @foreach ($pestanas as $clave => [$etiqueta, $icono, $permiso, $url])
        @can($permiso)
            <a href="{{ $url }}"
               class="btn {{ ($activa ?? '') === $clave ? 'btn-primary' : 'btn-outline-secondary' }}">
                <i class="{{ $icono }} me-1"></i>{{ $etiqueta }}
            </a>
        @endcan
    @endforeach
</div>
