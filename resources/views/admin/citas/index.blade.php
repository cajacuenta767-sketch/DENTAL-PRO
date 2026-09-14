@extends('layouts.admin')

@section('pretitulo', 'Administración')
@section('titulo', 'Gestión de Citas Médicas')

@section('acciones')
    @can('citas.crear')
        <a href="{{ route('admin.citas.create') }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i>Agendar Cita
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Citas para hoy" :valor="$totales['hoy']" icono="ti ti-calendar-event" color="primary" pie="programadas" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Por confirmar" :valor="$totales['pendientes']" icono="ti ti-clock-hour-4" color="warning" pie="pendientes" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Confirmadas" :valor="$totales['confirmadas']" icono="ti ti-calendar-check" color="azure" pie="citas" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Completadas" :valor="$totales['completadas']" icono="ti ti-circle-check" color="success" pie="atendidas" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h3 class="card-title"><i class="ti ti-filter me-2"></i>Filtros de Búsqueda</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Paciente, documento, token...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-select">
                    <option value="">— Todos los doctores —</option>
                    @foreach ($doctores as $doctor)
                        <option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>
                            {{ $doctor->nombre_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">— Todos —</option>
                    @foreach (\App\Models\Cita::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>
                            {{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" value="{{ request('fecha') }}" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill"><i class="ti ti-search me-1"></i>Filtrar</button>
                <a href="{{ route('admin.citas.index') }}" class="btn btn-outline-secondary" title="Limpiar">
                    <i class="ti ti-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-list me-2"></i>Listado de Citas Registradas</h3>
        <span class="badge bg-secondary-lt ms-auto">{{ $citas->total() }} registros</span>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Paciente</th>
                    <th>Doctor / Especialidad</th>
                    <th>Tratamiento</th>
                    <th>Fecha y hora</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Cobro</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($citas as $cita)
                    <tr class="{{ $cita->estado === 'CANCELADA' ? 'opacity-75' : '' }}">
                        <td class="text-secondary">{{ $loop->iteration + ($citas->currentPage() - 1) * $citas->perPage() }}</td>
                        <td>
                            <div class="fw-medium">{{ $cita->paciente->nombre_completo }}</div>
                            <div class="text-secondary small">
                                <i class="ti ti-id me-1"></i>{{ $cita->paciente->numero_documento }}
                                @if ($cita->paciente->telefono)
                                    <span class="ms-2"><i class="ti ti-phone me-1"></i>{{ $cita->paciente->telefono }}</span>
                                @endif
                            </div>
                            @if ($cita->recordatorio_enviado_en)
                                <span class="badge bg-green-lt mt-1"><i class="ti ti-bell-check me-1"></i>Recordado</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $cita->doctor->nombre_profesional }}</div>
                            <div class="text-secondary small">{{ $cita->doctor->especialidad->nombre }}</div>
                        </td>
                        <td>
                            <div>{{ $cita->tratamiento->nombre }}</div>
                            <div class="text-secondary small">
                                {{ $cita->tratamiento->duracion }} min | {{ number_format($cita->tratamiento->precio, 2) }} {{ $ajustes->divisa }}
                            </div>
                        </td>
                        <td>
                            <div>
                                {{ $cita->fecha->format('d/m/Y') }}
                                @if ($cita->serie_id)
                                    <i class="ti ti-repeat text-azure ms-1" title="Forma parte de una serie de citas recurrentes"></i>
                                @endif
                            </div>
                            <div class="text-secondary small">{{ substr($cita->hora, 0, 5) }}</div>
                        </td>
                        <td class="text-center">
                            @can('citas.editar')
                                <form method="POST" action="{{ route('admin.citas.estado', $cita) }}">
                                    @csrf @method('PATCH')
                                    <select name="estado" class="form-select form-select-sm border-{{ $cita->color_estado }}"
                                            onchange="this.form.submit()" {{ $cita->estado === 'COMPLETADA' ? 'disabled' : '' }}>
                                        @foreach (\App\Models\Cita::ESTADOS as $estado)
                                            <option value="{{ $estado }}" @selected($cita->estado === $estado)>
                                                {{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @else
                                <span class="badge bg-{{ $cita->color_estado }}-lt">{{ $cita->estado_legible }}</span>
                            @endcan
                        </td>
                        <td class="text-center">
                            @if ($cita->esta_pagada)
                                <span class="badge bg-success-lt"><i class="ti ti-check me-1"></i>Pagado</span>
                            @elseif ($cita->pagos->isNotEmpty())
                                <span class="badge bg-warning-lt">Parcial</span>
                            @else
                                <span class="badge bg-secondary-lt">Sin pago</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.citas.show', $cita) }}" class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                    <i class="ti ti-eye"></i>
                                </a>
                                @can('citas.confirmar')
                                    <form method="POST" action="{{ route('admin.citas.reenviar', $cita) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-azure" title="Reenviar confirmación por correo">
                                            <i class="ti ti-mail"></i>
                                        </button>
                                    </form>
                                @endcan
                                @can('citas.editar')
                                    <a href="{{ route('admin.citas.edit', $cita) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('pagos.crear')
                                    @if (! $cita->esta_pagada)
                                        <a href="{{ route('admin.pagos.create', ['cita_id' => $cita->id]) }}"
                                           class="btn btn-sm btn-outline-orange" title="Cobrar">
                                            <i class="ti ti-cash"></i>
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-vacio icono="ti ti-calendar-off" titulo="Sin citas"
                                     texto="Ninguna cita coincide con los filtros aplicados." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($citas->hasPages())
        <div class="card-footer">{{ $citas->links() }}</div>
    @endif
</div>
@endsection
