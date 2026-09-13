@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Imagenología')

@section('acciones')
    @can('imagenologia.crear')
        <a href="{{ route('admin.estudios.create') }}" class="btn btn-primary">
            <i class="ti ti-upload me-1"></i>Cargar Estudio
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Estudios archivados" :valor="$totales['estudios']" icono="ti ti-photo-scan" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Panorámicas" :valor="$totales['panoramicas']" icono="ti ti-dental" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Cargados este mes" :valor="$totales['esteMes']" icono="ti ti-calendar-plus" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Pacientes con estudios" :valor="$totales['pacientes']" icono="ti ti-users" color="indigo" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Título del estudio, paciente o documento">
            </div>
            <div class="col-md-3">
                <select name="tipo" class="form-select">
                    <option value="">— Todos los tipos —</option>
                    @foreach (\App\Models\EstudioImagen::TIPOS as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('tipo') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary w-100"><i class="ti ti-search me-1"></i>Buscar</button>
            </div>
        </form>
    </div>
</div>

@if ($estudios->isEmpty())
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-photo-off" titulo="Sin estudios"
                 texto="Ningún estudio coincide con la búsqueda." />
    </div></div>
@else
    <div class="row row-cards">
        @foreach ($estudios as $estudio)
            <div class="col-md-6 col-xl-3">
                <x-tarjeta-estudio :estudio="$estudio" />
                <div class="text-center small mt-1">
                    <a href="{{ route('admin.estudios.paciente', $estudio->paciente_id) }}" class="text-secondary">
                        {{ $estudio->paciente->nombre_completo }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    @if ($estudios->hasPages())
        <div class="mt-3">{{ $estudios->links() }}</div>
    @endif
@endif

@include('componentes.visor-estudio')
@endsection
