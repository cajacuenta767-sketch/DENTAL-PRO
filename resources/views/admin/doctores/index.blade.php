@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Equipo Médico')

@section('acciones')
    @can('doctores.crear')
        <a href="{{ route('admin.doctores.create') }}" class="btn btn-primary">
            <i class="ti ti-user-plus me-1"></i>Nuevo Doctor
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre, apellido o documento">
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
</div>

<div class="row row-cards">
    @forelse ($doctores as $doctor)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @if ($doctor->fotografia)
                            <span class="avatar avatar-lg" style="background-image: url({{ Storage::url($doctor->fotografia) }})"></span>
                        @else
                            <span class="avatar avatar-lg bg-brand">
                                {{ mb_substr($doctor->nombres, 0, 1) }}{{ mb_substr($doctor->apellidos, 0, 1) }}
                            </span>
                        @endif
                        <div class="flex-fill">
                            <h3 class="mb-0">{{ $doctor->nombre_profesional }}</h3>
                            <span class="badge" style="background-color: {{ $doctor->especialidad->color }}20; color: {{ $doctor->especialidad->color }}">
                                {{ $doctor->especialidad->nombre }}
                            </span>
                        </div>
                    </div>

                    <div class="datagrid">
                        <div class="datagrid-item">
                            <div class="datagrid-title">Documento</div>
                            <div class="datagrid-content">{{ $doctor->tipo_documento }} {{ $doctor->numero_documento }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Teléfono</div>
                            <div class="datagrid-content">{{ $doctor->telefono ?: '—' }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Citas</div>
                            <div class="datagrid-content">{{ $doctor->citas_count }}</div>
                        </div>
                        <div class="datagrid-item">
                            <div class="datagrid-title">Turnos</div>
                            <div class="datagrid-content">{{ $doctor->horarios_count }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center gap-2">
                    <span class="badge bg-{{ $doctor->activo ? 'success' : 'secondary' }}-lt">
                        {{ $doctor->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                    <div class="btn-list ms-auto flex-nowrap">
                        <a href="{{ route('admin.doctores.show', $doctor) }}" class="btn btn-sm btn-outline-secondary" title="Ver ficha">
                            <i class="ti ti-eye"></i>
                        </a>
                        @can('doctores.editar')
                            <a href="{{ route('admin.doctores.edit', $doctor) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="ti ti-edit"></i>
                            </a>
                        @endcan
                        @can('horarios.ver')
                            <a href="{{ route('admin.horarios.index', ['doctor_id' => $doctor->id]) }}"
                               class="btn btn-sm btn-outline-azure" title="Horarios">
                                <i class="ti ti-clock-hour-4"></i>
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card"><div class="card-body">
                <x-vacio icono="ti ti-user-off" titulo="Sin doctores registrados"
                         texto="Registra a tu equipo médico para poder agendar citas." />
            </div></div>
        </div>
    @endforelse
</div>

@if ($doctores->hasPages())
    <div class="mt-3">{{ $doctores->links() }}</div>
@endif
@endsection
