@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Historia Clínica')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha del paciente</a>
        @can('historiales.crear')
            <a href="{{ route('admin.historiales.create', $paciente) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nueva Consulta
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'historial'])

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-alert-triangle me-2 text-warning"></i>Alertas del paciente</h3></div>
            <div class="card-body">
                @foreach ([
                    'Alergias' => $paciente->alergias,
                    'Enfermedades' => $paciente->enfermedades,
                    'Medicación' => $paciente->medicamentos,
                    'Grupo sanguíneo' => $paciente->grupo_sanguineo,
                ] as $titulo => $valor)
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">{{ $titulo }}</div>
                        <div class="{{ $valor ? '' : 'text-secondary' }}">{{ $valor ?: 'Sin registro' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @forelse ($historiales as $historial)
            <div class="card mb-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">
                            {{ $historial->fecha->format('d/m/Y') }}
                            @if ($historial->plantilla && config("evolucion.plantillas.{$historial->plantilla}.etiqueta"))
                                <span class="badge bg-azure-lt ms-2">{{ config("evolucion.plantillas.{$historial->plantilla}.etiqueta") }}</span>
                            @endif
                        </h3>
                        <div class="text-secondary small">
                            {{ $historial->doctor->nombre_profesional }} · {{ $historial->doctor->especialidad->nombre }}
                            @if ($historial->cita)
                                · cita {{ $historial->cita->token }}
                            @endif
                        </div>
                    </div>
                    <div class="btn-list ms-auto flex-nowrap">
                        <a href="{{ route('admin.historiales.pdf', $historial) }}" class="btn btn-sm btn-outline-danger" title="Descargar PDF">
                            <i class="ti ti-file-type-pdf"></i>
                        </a>
                        @can('historiales.editar')
                            <a href="{{ route('admin.historiales.edit', $historial) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="ti ti-edit"></i>
                            </a>
                        @endcan
                        @can('historiales.eliminar')
                            <form method="POST" action="{{ route('admin.historiales.destroy', $historial) }}"
                                  data-confirmar="¿Eliminar esta consulta de la historia clínica?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                            </form>
                        @endcan
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach ([
                            'Motivo de consulta' => $historial->motivo_consulta,
                            'Síntomas' => $historial->sintomas,
                            'Diagnóstico' => $historial->diagnostico,
                            'Tratamiento realizado' => $historial->tratamiento_realizado,
                            'Prescripción / receta' => $historial->prescripcion_receta,
                            'Anestesia' => $historial->anestesia
                                ? $historial->anestesia.($historial->anestesia_cantidad ? ' · '.$historial->anestesia_cantidad.' cartucho(s)' : '')
                                : null,
                            'Medicación' => $historial->medicacion,
                            'Observaciones' => $historial->observaciones,
                            'Indicaciones para la próxima cita' => $historial->proxima_cita_indicaciones,
                        ] as $titulo => $valor)
                            @if ($valor)
                                <div class="col-md-6">
                                    <div class="text-secondary small text-uppercase">{{ $titulo }}</div>
                                    <div style="white-space: pre-line;">{{ $valor }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body">
                <x-vacio icono="ti ti-notes-off" titulo="Historia clínica vacía"
                         texto="Registra la primera consulta de {{ $paciente->nombres }}.">
                    @can('historiales.crear')
                        <a href="{{ route('admin.historiales.create', $paciente) }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>Nueva consulta
                        </a>
                    @endcan
                </x-vacio>
            </div></div>
        @endforelse

        @if ($historiales->hasPages())
            {{ $historiales->links() }}
        @endif
    </div>
</div>
@endsection
