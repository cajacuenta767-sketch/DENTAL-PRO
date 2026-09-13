@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Odontogramas')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha del paciente</a>
        @can('odontogramas.crear')
            <a href="{{ route('admin.odontogramas.create', ['paciente' => $paciente, 'tipo' => 'INFANTIL']) }}" class="btn btn-outline-primary">
                <i class="ti ti-mood-kid me-1"></i>Nuevo infantil
            </a>
            <a href="{{ route('admin.odontogramas.create', $paciente) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nuevo Odontograma
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@forelse ($odontogramas as $odontograma)
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">
                    {{ $odontograma->fecha->format('d/m/Y') }}
                    <span class="badge bg-{{ $odontograma->tipo === 'INFANTIL' ? 'pink' : 'azure' }}-lt ms-2">{{ $odontograma->tipo }}</span>
                </h3>
                <div class="text-secondary small">
                    {{ $odontograma->doctor?->nombre_profesional ?? 'Sin doctor asignado' }}
                    @if ($odontograma->cita) · cita {{ $odontograma->cita->token }} @endif
                </div>
            </div>
            <div class="btn-list ms-auto flex-nowrap">
                <span class="badge bg-orange-lt align-self-center">
                    {{ $odontograma->piezas_afectadas }} piezas con hallazgo
                </span>
                @can('odontogramas.editar')
                    <a href="{{ route('admin.odontogramas.edit', $odontograma) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="ti ti-edit"></i>
                    </a>
                @endcan
                @can('odontogramas.eliminar')
                    <form method="POST" action="{{ route('admin.odontogramas.destroy', $odontograma) }}"
                          data-confirmar="¿Eliminar este odontograma?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="card-body">
            @include('componentes.odontograma', [
                'tipo' => $odontograma->tipo,
                'piezas' => $odontograma->piezas ?? [],
                'editable' => false,
            ])

            @if ($odontograma->observaciones)
                <div class="mt-3 border-top pt-3">
                    <div class="text-secondary small text-uppercase">Observaciones</div>
                    <div>{{ $odontograma->observaciones }}</div>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-dental-off" titulo="Sin odontogramas"
                 texto="Registra el primer odontograma de {{ $paciente->nombres }}.">
            @can('odontogramas.crear')
                <a href="{{ route('admin.odontogramas.create', $paciente) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Nuevo odontograma
                </a>
            @endcan
        </x-vacio>
    </div></div>
@endforelse

@if ($odontogramas->hasPages())
    {{ $odontogramas->links() }}
@endif
@endsection
