@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $doctor->nombre_profesional)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.doctores.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        @can('doctores.editar')
            <a href="{{ route('admin.doctores.edit', $doctor) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Editar</a>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                @if ($doctor->fotografia)
                    <span class="avatar avatar-xl mb-3" style="background-image: url({{ Storage::url($doctor->fotografia) }})"></span>
                @else
                    <span class="avatar avatar-xl bg-brand mb-3">
                        {{ mb_substr($doctor->nombres, 0, 1) }}{{ mb_substr($doctor->apellidos, 0, 1) }}
                    </span>
                @endif
                <h3 class="mb-1">{{ $doctor->nombre_profesional }}</h3>
                <span class="badge" style="background-color: {{ $doctor->especialidad->color }}20; color: {{ $doctor->especialidad->color }}">
                    {{ $doctor->especialidad->nombre }}
                </span>
                @if ($doctor->colegiatura)
                    <div class="text-secondary small mt-2">Colegiatura {{ $doctor->colegiatura }}</div>
                @endif
            </div>
            <div class="card-body border-top">
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
                        <div class="datagrid-title">Correo</div>
                        <div class="datagrid-content">{{ $doctor->email ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Usuario del sistema</div>
                        <div class="datagrid-content">{{ $doctor->usuario?->email ?: 'Sin enlazar' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Dirección</div>
                        <div class="datagrid-content">{{ $doctor->direccion ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Comisión sobre lo cobrado</div>
                        <div class="datagrid-content">
                            @if ((float) $doctor->porcentaje_comision > 0)
                                <span class="badge bg-yellow-lt">{{ number_format($doctor->porcentaje_comision, 2) }} %</span>
                            @else
                                <span class="text-secondary">Sin comisión</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row row-cards mb-3">
            <div class="col-sm-4">
                <x-kpi titulo="Citas atendidas" :valor="$atendidas" icono="ti ti-checkup-list" color="success" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Turnos configurados" :valor="$doctor->horarios->count()" icono="ti ti-clock-hour-4" color="azure" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Recaudado" :valor="number_format($recaudado, 2).' '.$ajustes->divisa" icono="ti ti-cash" color="yellow" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-clock-hour-4 me-2"></i>Disponibilidad semanal</h3>
                @can('horarios.crear')
                    <a href="{{ route('admin.horarios.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-sm btn-outline-primary ms-auto">
                        <i class="ti ti-plus me-1"></i>Agregar turno
                    </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Día</th><th>Turno</th><th>Horario</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($doctor->horarios as $horario)
                            <tr>
                                <td>{{ config("odontosuite.dias_semana.{$horario->dia_semana}", $horario->dia_semana) }}</td>
                                <td><span class="badge bg-azure-lt">{{ $horario->turno }}</span></td>
                                <td>{{ $horario->rango }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $horario->activo ? 'success' : 'secondary' }}-lt">
                                        {{ $horario->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Este doctor aún no tiene turnos configurados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-event me-2"></i>Próximas citas</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Paciente</th><th>Tratamiento</th><th>Fecha y hora</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($proximasCitas as $cita)
                            <tr>
                                <td>{{ $cita->paciente->nombre_completo }}</td>
                                <td class="text-secondary">{{ $cita->tratamiento->nombre }}</td>
                                <td>{{ $cita->fecha_hora }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $cita->color_estado }}-lt">{{ $cita->estado_legible }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No hay citas próximas para este doctor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
