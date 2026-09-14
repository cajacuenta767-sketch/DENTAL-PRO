@props(['estudio'])

<div class="card h-100 os-module-card" data-estudio style="cursor: zoom-in;"
     data-estudio-titulo="{{ $estudio->titulo }}"
     data-estudio-tipo="{{ $estudio->tipo_legible }}"
     data-estudio-fecha="{{ $estudio->fecha_estudio->format('d/m/Y') }}"
     data-estudio-iso="{{ $estudio->fecha_estudio->format('Y-m-d') }}"
     data-estudio-archivo="{{ $estudio->nombre_original }}"
     data-estudio-url="{{ $estudio->url }}"
     data-estudio-descarga="{{ route('admin.estudios.descargar', $estudio) }}"
     data-estudio-hallazgos="{{ $estudio->hallazgos }}"
     data-estudio-visualizable="{{ $estudio->es_visualizable ? '1' : '0' }}"
     data-estudio-pdf="{{ $estudio->es_pdf ? '1' : '0' }}"
     data-estudio-familia="{{ $estudio->familia }}"
     data-estudio-anotaciones-url="{{ route('admin.estudios.anotaciones', $estudio) }}"
     data-anotaciones="{{ json_encode($estudio->anotaciones ?: []) }}">

    <div class="ratio ratio-16x9 bg-dark rounded-top overflow-hidden">
        @if ($estudio->es_visualizable)
            <img src="{{ $estudio->url }}" alt="{{ $estudio->titulo }}" style="object-fit: cover;" loading="lazy">
        @else
            <div class="d-flex flex-column align-items-center justify-content-center text-white-50">
                <i class="ti {{ $estudio->icono }} fs-1"></i>
                <span class="small text-uppercase">{{ $estudio->extension }}</span>
            </div>
        @endif
    </div>

    <div class="card-body">
        <div class="d-flex align-items-start gap-2">
            <div class="flex-fill">
                <div class="fw-medium text-truncate">{{ $estudio->titulo }}</div>
                <div class="text-secondary small">
                    {{ $estudio->fecha_estudio->format('d/m/Y') }}
                    @if ($estudio->doctor) · {{ $estudio->doctor->nombre_profesional }} @endif
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-azure-lt">{{ $estudio->tipo_legible }}</span>
                <span class="badge bg-orange-lt {{ $estudio->anotaciones ? '' : 'd-none' }}" data-estudio-badge-anotado
                      title="Este estudio tiene anotaciones dibujadas">
                    <i class="ti ti-pencil me-1"></i>Anotado
                </span>
            </div>
        </div>

        @if ($estudio->piezas_referidas)
            <div class="text-secondary small mt-2">
                <i class="ti ti-dental me-1"></i>Piezas {{ $estudio->piezas_referidas }}
            </div>
        @endif
    </div>

    <div class="card-footer d-flex align-items-center gap-2">
        <span class="text-secondary small">{{ $estudio->tamano_legible }}</span>
        <div class="btn-list ms-auto flex-nowrap">
            @can('imagenologia.descargar')
                <a href="{{ route('admin.estudios.descargar', $estudio) }}" class="btn btn-sm btn-outline-secondary" title="Descargar">
                    <i class="ti ti-download"></i>
                </a>
            @endcan
            @can('imagenologia.editar')
                <a href="{{ route('admin.estudios.edit', $estudio) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                    <i class="ti ti-edit"></i>
                </a>
            @endcan
            @can('imagenologia.eliminar')
                <form method="POST" action="{{ route('admin.estudios.destroy', $estudio) }}"
                      data-confirmar="¿Eliminar el estudio {{ $estudio->titulo }}?">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                </form>
            @endcan
        </div>
    </div>
</div>
