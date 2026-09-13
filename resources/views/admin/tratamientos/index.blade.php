@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', 'Tratamientos')

@section('acciones')
    @can('tratamientos.crear')
        <a href="{{ route('admin.tratamientos.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nuevo Tratamiento
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h3 class="card-title">Listado de Tratamientos</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre del tratamiento o especialidad">
            </div>
            <div class="col-md-4">
                <select name="especialidad_id" class="form-select">
                    <option value="">— Todas las especialidades —</option>
                    @foreach ($especialidades as $especialidad)
                        <option value="{{ $especialidad->id }}" @selected(request('especialidad_id') == $especialidad->id)>
                            {{ $especialidad->nombre }}
                        </option>
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
                    <th style="width: 3rem;">#</th>
                    <th>Nombre</th>
                    <th>Especialidad</th>
                    <th class="text-end">Precio</th>
                    <th class="text-center">Duración</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tratamientos as $tratamiento)
                    <tr>
                        <td class="text-secondary">{{ $loop->iteration + ($tratamientos->currentPage() - 1) * $tratamientos->perPage() }}</td>
                        <td class="fw-medium">{{ $tratamiento->nombre }}</td>
                        <td>
                            <span class="badge" style="background-color: {{ $tratamiento->especialidad->color }}20; color: {{ $tratamiento->especialidad->color }}">
                                {{ $tratamiento->especialidad->nombre }}
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-blue-lt">{{ number_format($tratamiento->precio, 2) }} {{ $ajustes->divisa }}</span>
                        </td>
                        <td class="text-center text-secondary">
                            <i class="ti ti-clock me-1"></i>{{ $tratamiento->duracion }} min
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $tratamiento->activo ? 'success' : 'secondary' }}-lt">
                                {{ $tratamiento->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('tratamientos.editar')
                                    <a href="{{ route('admin.tratamientos.edit', $tratamiento) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('tratamientos.eliminar')
                                    <form method="POST" action="{{ route('admin.tratamientos.destroy', $tratamiento) }}"
                                          data-confirmar="¿Eliminar el tratamiento {{ $tratamiento->nombre }}?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-vacio icono="ti ti-dental" titulo="Sin tratamientos"
                                     texto="Registra el catálogo de tratamientos con su precio y duración." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($tratamientos->hasPages())
        <div class="card-footer">{{ $tratamientos->links() }}</div>
    @endif
</div>
@endsection
