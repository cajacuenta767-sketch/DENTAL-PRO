@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Panorámicas y Estudios')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i>Ficha del paciente
        </a>
        @can('imagenologia.crear')
            <a href="{{ route('admin.estudios.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary">
                <i class="ti ti-upload me-1"></i>Cargar Estudio
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'panoramicas'])

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-secondary small text-uppercase me-2">Tipo de estudio:</span>
            <a href="{{ route('admin.estudios.paciente', $paciente) }}"
               class="btn btn-sm {{ request('tipo') ? 'btn-outline-secondary' : 'btn-primary' }}">Todos</a>
            @foreach (\App\Models\EstudioImagen::TIPOS as $clave => $etiqueta)
                <a href="{{ route('admin.estudios.paciente', [$paciente, 'tipo' => $clave]) }}"
                   class="btn btn-sm {{ request('tipo') === $clave ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach
        </form>
    </div>
</div>

@if ($estudios->isEmpty())
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-photo-off" titulo="Sin estudios cargados"
                 texto="Sube la primera radiografía o fotografía clínica de {{ $paciente->nombres }}.">
            @can('imagenologia.crear')
                <a href="{{ route('admin.estudios.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary">
                    <i class="ti ti-upload me-1"></i>Cargar estudio
                </a>
            @endcan
        </x-vacio>
    </div></div>
@else
    <div class="row row-cards">
        @foreach ($estudios as $estudio)
            <div class="col-md-6 col-xl-4">
                <x-tarjeta-estudio :estudio="$estudio" />
            </div>
        @endforeach
    </div>
@endif

@include('componentes.visor-estudio')
@endsection
