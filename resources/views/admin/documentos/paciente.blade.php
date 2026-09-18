@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Recetas y Certificados')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i>Ficha del paciente
        </a>
        @can('documentos.crear')
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="ti ti-plus me-1"></i>Nuevo documento
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    @foreach (\App\Models\DocumentoClinico::TIPOS as $clave => $etiqueta)
                        <a class="dropdown-item"
                           href="{{ route('admin.documentos.create', ['paciente_id' => $paciente->id, 'tipo' => $clave]) }}">
                            <i class="{{ \App\Models\DocumentoClinico::ICONOS[$clave] }} me-2"></i>{{ $etiqueta }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'documentos'])

@forelse ($documentos as $documento)
    <div class="card mb-3 {{ $documento->estado === 'ANULADO' ? 'opacity-75' : '' }}">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">
                    <i class="{{ $documento->icono }} me-2"></i>{{ $documento->titulo }}
                </h3>
                <div class="text-secondary small">
                    {{ $documento->folio }} · {{ $documento->fecha_emision->format('d/m/Y') }} ·
                    {{ $documento->doctor->nombre_profesional }}
                </div>
            </div>
            <div class="btn-list ms-auto flex-nowrap">
                @if ($documento->esta_firmado)
                    <span class="badge bg-success-lt badge-sm align-self-center"
                          title="Firmado el {{ $documento->firmado_en->format('d/m/Y H:i') }}">
                        <i class="ti ti-writing-sign me-1"></i>Firmado
                    </span>
                @endif
                @if ($documento->estado === 'ANULADO')
                    <span class="badge bg-danger align-self-center">Anulado</span>
                @elseif ($documento->vence_el)
                    <span class="badge bg-{{ $documento->esta_vigente ? 'success' : 'secondary' }}-lt align-self-center">
                        Vence {{ $documento->vence_el->format('d/m/Y') }}
                    </span>
                @endif
                <a href="{{ route('admin.documentos.pdf', $documento) }}" target="_blank"
                   class="btn btn-sm btn-outline-danger" title="Ver PDF">
                    <i class="ti ti-file-type-pdf"></i>
                </a>
                @can('documentos.editar')
                    @if ($documento->estado !== 'ANULADO')
                        <a href="{{ route('admin.documentos.edit', $documento) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                            <i class="ti ti-edit"></i>
                        </a>
                    @endif
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div style="white-space: pre-line;">{{ $documento->contenido }}</div>
            @if ($documento->indicaciones)
                <div class="mt-3 border-top pt-3">
                    <div class="text-secondary small text-uppercase">Indicaciones</div>
                    <div style="white-space: pre-line;">{{ $documento->indicaciones }}</div>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-file-off" titulo="Sin documentos emitidos"
                 texto="Emite la primera receta o certificado de {{ $paciente->nombres }}.">
            @can('documentos.crear')
                <a href="{{ route('admin.documentos.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Nuevo documento
                </a>
            @endcan
        </x-vacio>
    </div></div>
@endforelse
@endsection
