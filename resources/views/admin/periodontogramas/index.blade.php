@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Periodontogramas')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha del paciente</a>
        @can('periodontogramas.crear')
            @if ($periodontogramas->isNotEmpty())
                <a href="{{ route('admin.periodontogramas.create', ['paciente' => $paciente, 'desde' => 'ultimo']) }}"
                   class="btn btn-outline-primary" title="Nuevo periodontograma a partir del último registrado">
                    <i class="ti ti-copy me-1"></i>Control desde el último
                </a>
            @endif
            <a href="{{ route('admin.periodontogramas.create', $paciente) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nuevo periodontograma
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'periodontograma'])

@forelse ($periodontogramas as $periodontograma)
    @php
        $indices = $periodontograma->indices();
        $diagnostico = $periodontograma->diagnosticoOrientativo();
        $gravedad = match (true) {
            $indices['bolsas_profundas'] > 0 || $indices['profundidad_media'] >= 5 => 'danger',
            $indices['bolsas'] > 0 || $indices['profundidad_media'] >= 4 => 'warning',
            $indices['sangrado'] >= 10 => 'azure',
            default => 'success',
        };
    @endphp
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">{{ $periodontograma->fecha->format('d/m/Y') }}</h3>
                <div class="text-secondary small">
                    {{ $periodontograma->doctor?->nombre_profesional ?? 'Sin doctor asignado' }}
                    @if ($periodontograma->cita) · cita {{ $periodontograma->cita->token }} @endif
                </div>
            </div>
            <div class="btn-list ms-auto flex-nowrap">
                <a href="{{ route('admin.periodontogramas.pdf', $periodontograma) }}" target="_blank"
                   class="btn btn-sm btn-outline-danger" title="Descargar PDF">
                    <i class="ti ti-file-type-pdf"></i>
                </a>
                @can('periodontogramas.editar')
                    <a href="{{ route('admin.periodontogramas.edit', $periodontograma) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="ti ti-edit"></i>
                    </a>
                @endcan
                @can('periodontogramas.eliminar')
                    <form method="POST" action="{{ route('admin.periodontogramas.destroy', $periodontograma) }}"
                          data-confirmar="¿Eliminar este periodontograma?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 text-center mb-3">
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0 text-danger">{{ number_format($indices['sangrado'], 1) }} %</div>
                        <div class="text-secondary" style="font-size: .7rem;">SANGRADO</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0 text-warning">{{ number_format($indices['placa'], 1) }} %</div>
                        <div class="text-secondary" style="font-size: .7rem;">PLACA</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0">{{ number_format($indices['profundidad_media'], 2) }} mm</div>
                        <div class="text-secondary" style="font-size: .7rem;">PROF. MEDIA</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0 text-warning">{{ $indices['bolsas'] }}</div>
                        <div class="text-secondary" style="font-size: .7rem;">SITIOS ≥ 4 MM</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0 text-danger">{{ $indices['bolsas_profundas'] }}</div>
                        <div class="text-secondary" style="font-size: .7rem;">SITIOS ≥ 6 MM</div>
                    </div></div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <div class="card card-sm"><div class="card-body py-2">
                        <div class="h2 mb-0">{{ $indices['movilidad'] }} <span class="text-secondary fs-5">/ {{ $indices['ausentes'] }}</span></div>
                        <div class="text-secondary" style="font-size: .7rem;">MOVILIDAD / AUSENTES</div>
                    </div></div>
                </div>
            </div>

            <div class="alert alert-{{ $gravedad }} py-2 mb-0">
                <i class="ti ti-stethoscope me-1"></i>
                <strong>Diagnóstico orientativo:</strong> {{ $diagnostico }}
                <span class="text-secondary small d-block">Guía a partir de los índices; no sustituye el criterio clínico.</span>
            </div>

            @if ($periodontograma->observaciones)
                <div class="mt-3 border-top pt-3">
                    <div class="text-secondary small text-uppercase">Observaciones</div>
                    <div style="white-space: pre-line;">{{ $periodontograma->observaciones }}</div>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-dental-off" titulo="Sin periodontogramas"
                 texto="Registra el primer sondaje periodontal de {{ $paciente->nombres }}.">
            @can('periodontogramas.crear')
                <a href="{{ route('admin.periodontogramas.create', $paciente) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Nuevo periodontograma
                </a>
            @endcan
        </x-vacio>
    </div></div>
@endforelse

@if ($periodontogramas->hasPages())
    {{ $periodontogramas->links() }}
@endif
@endsection
