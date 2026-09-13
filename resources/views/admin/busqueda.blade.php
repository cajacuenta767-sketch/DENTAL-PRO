@extends('layouts.admin')

@section('pretitulo', 'Búsqueda')
@section('titulo', $termino ? 'Resultados para "'.$termino.'"' : 'Búsqueda global')

@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="q" value="{{ $termino }}" class="form-control form-control-lg"
                           placeholder="Paciente, doctor, cita, recibo o presupuesto…" autofocus>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-lg">Buscar</button>
            </div>
        </form>
    </div>
</div>

@if (! $termino)
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-search" titulo="Busca en todo el sistema"
                 texto="Escribe al menos dos caracteres. Solo verás resultados de los módulos a los que tienes acceso." />
    </div></div>
@elseif (empty($grupos))
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-mood-empty" titulo="Sin coincidencias"
                 texto="No encontramos nada que coincida con «{{ $termino }}»." />
    </div></div>
@else
    <div class="row row-cards">
        @foreach ($grupos as $grupo)
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h3 class="card-title"><i class="{{ $grupo['icono'] }} me-2"></i>{{ $grupo['titulo'] }}</h3>
                        <span class="badge bg-secondary-lt ms-auto">{{ count($grupo['items']) }}</span>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($grupo['items'] as $item)
                            <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action">
                                <div class="fw-medium">{{ $item['titulo'] }}</div>
                                <div class="text-secondary small">{{ $item['detalle'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
