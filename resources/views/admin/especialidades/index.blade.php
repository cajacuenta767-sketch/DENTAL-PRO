@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', 'Especialidades')

@section('acciones')
    @can('especialidades.crear')
        <a href="{{ route('admin.especialidades.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nueva Especialidad
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Especialidades</h3>
        <form method="GET" class="ms-auto d-flex gap-2">
            <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-sm"
                   placeholder="Nombre de la especialidad">
            <button class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Especialidad</th>
                    <th>Descripción</th>
                    <th class="text-center">Doctores</th>
                    <th class="text-center">Tratamientos</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($especialidades as $especialidad)
                    <tr>
                        <td class="text-secondary">{{ $loop->iteration + ($especialidades->currentPage() - 1) * $especialidades->perPage() }}</td>
                        <td>
                            <span class="badge me-1" style="background-color: {{ $especialidad->color }}">&nbsp;</span>
                            <span class="fw-medium">{{ $especialidad->nombre }}</span>
                        </td>
                        <td class="text-secondary">{{ $especialidad->descripcion ?: '—' }}</td>
                        <td class="text-center">{{ $especialidad->doctores_count }}</td>
                        <td class="text-center">{{ $especialidad->tratamientos_count }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $especialidad->activo ? 'success' : 'secondary' }}-lt">
                                {{ $especialidad->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('especialidades.editar')
                                    <a href="{{ route('admin.especialidades.edit', $especialidad) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('especialidades.eliminar')
                                    <form method="POST" action="{{ route('admin.especialidades.destroy', $especialidad) }}"
                                          data-confirmar="¿Eliminar la especialidad {{ $especialidad->nombre }}?">
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
                            <x-vacio icono="ti ti-stethoscope" titulo="Sin especialidades"
                                     texto="Registra las especialidades que atiende tu clínica." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($especialidades->hasPages())
        <div class="card-footer">{{ $especialidades->links() }}</div>
    @endif
</div>
@endsection
