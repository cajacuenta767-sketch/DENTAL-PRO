@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', 'Aseguradoras y Convenios')

@section('acciones')
    @can('aseguradoras.crear')
        <a href="{{ route('admin.aseguradoras.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nueva Aseguradora
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Convenios registrados" :valor="$totales['convenios']" icono="ti ti-shield-heart" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Activos" :valor="$totales['activas']" icono="ti ti-circle-check" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Pacientes afiliados" :valor="$totales['afiliados']" icono="ti ti-users" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Cobertura promedio" :valor="$totales['coberturaMedia'].'%'" icono="ti ti-percentage" color="indigo" />
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Listado de Aseguradoras</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre o código de la aseguradora">
            </div>
            <div class="col-md-3">
                <select name="tipo" class="form-select">
                    <option value="">— Todos los tipos —</option>
                    @foreach (\App\Models\Aseguradora::TIPOS as $tipo)
                        <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary w-100"><i class="ti ti-search me-1"></i>Buscar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Aseguradora</th>
                    <th>Tipo</th>
                    <th class="text-center">Cobertura</th>
                    <th class="text-end">Tope anual</th>
                    <th>Contacto</th>
                    <th class="text-center">Afiliados</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($aseguradoras as $aseguradora)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $aseguradora->nombre }}</div>
                            @if ($aseguradora->codigo)
                                <div class="text-secondary small">Código {{ $aseguradora->codigo }}</div>
                            @endif
                        </td>
                        <td><span class="badge bg-azure-lt">{{ $aseguradora->tipo }}</span></td>
                        <td class="text-center">
                            <span class="badge bg-primary-lt">{{ number_format($aseguradora->porcentaje_cobertura, 0) }}%</span>
                        </td>
                        <td class="text-end text-secondary">
                            {{ $aseguradora->tope_anual ? number_format($aseguradora->tope_anual, 2).' '.$ajustes->divisa : 'Sin tope' }}
                        </td>
                        <td class="text-secondary small">
                            @if ($aseguradora->contacto)<div>{{ $aseguradora->contacto }}</div>@endif
                            @if ($aseguradora->telefono)<div><i class="ti ti-phone me-1"></i>{{ $aseguradora->telefono }}</div>@endif
                            @if (! $aseguradora->contacto && ! $aseguradora->telefono)—@endif
                        </td>
                        <td class="text-center">{{ $aseguradora->pacientes_count }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $aseguradora->activo ? 'success' : 'secondary' }}-lt">
                                {{ $aseguradora->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('aseguradoras.editar')
                                    <a href="{{ route('admin.aseguradoras.edit', $aseguradora) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('aseguradoras.eliminar')
                                    <form method="POST" action="{{ route('admin.aseguradoras.destroy', $aseguradora) }}"
                                          data-confirmar="¿Eliminar la aseguradora {{ $aseguradora->nombre }}?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-vacio icono="ti ti-shield-off" titulo="Sin aseguradoras"
                                     texto="Registra las obras sociales y convenios con los que trabaja tu clínica." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($aseguradoras->hasPages())
        <div class="card-footer">{{ $aseguradoras->links() }}</div>
    @endif
</div>
@endsection
