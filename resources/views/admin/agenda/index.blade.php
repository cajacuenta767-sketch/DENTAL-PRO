@extends('layouts.admin')

@section('pretitulo', $esAgendaPropia ? 'Mi consultorio' : 'Clínica')
@section('titulo', $saludo.', '.\Illuminate\Support\Str::before(auth()->user()->nombre, ' '))
@section('subtitulo', $esHoy
    ? 'Tenés '.$resumen['turnos'].' turnos programados para hoy.'
    : \Carbon\Carbon::parse($fecha)->format('d/m/Y').' · '.$nombreDia)

@section('acciones')
    <div class="btn-list">
        <div class="btn-group">
            <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-primary">Día</a>
            <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-outline-primary">Semana</a>
            <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-outline-primary">Mes</a>
        </div>
        @can('pacientes.crear')
            <a href="{{ route('admin.pacientes.create') }}" class="btn btn-outline-secondary">
                <i class="ti ti-user-plus me-1"></i>Nuevo paciente
            </a>
        @endcan
        @can('citas.crear')
            <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor?->id]) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Crear turno
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Franja de indicadores del día --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span>Turnos hoy: <strong>{{ $resumen['turnos'] }}</strong></span>
            <span class="text-secondary">|</span>
            <span>Pacientes: <strong>{{ $resumen['pacientes'] }}</strong></span>
            <span class="text-secondary">|</span>
            <span>Por confirmar: <strong class="text-warning">{{ $resumen['porConfirmar'] }}</strong></span>
            <span class="text-secondary">|</span>
            <span>Profesionales activos: <strong>{{ $resumen['profesionales'] }}</strong></span>
            <span class="text-secondary">|</span>
            <span>Atendidos: <strong class="text-success">{{ $resumen['atendidos'] }}</strong></span>

            @can('citas.ver')
                <a href="{{ route('admin.citas.index') }}" class="ms-auto text-brand text-decoration-none">
                    Ver turnos de los próximos días <i class="ti ti-chevron-right"></i>
                </a>
            @endcan
        </div>
    </div>
</div>

{{-- Navegación por fecha y profesional --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            @if ($puedeVerTodas)
                <div class="col-md-5">
                    <label class="form-label">Profesional</label>
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">— Toda la clínica —</option>
                        @foreach ($doctores as $d)
                            <option value="{{ $d->id }}" @selected($doctor?->id === $d->id)>
                                {{ $d->nombre_profesional }} · {{ $d->especialidad->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" value="{{ $fecha }}" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <div class="btn-list">
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => \Carbon\Carbon::parse($fecha)->subDay()->toDateString()]) }}"
                       class="btn btn-outline-secondary"><i class="ti ti-chevron-left"></i></a>
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => now()->toDateString()]) }}"
                       class="btn {{ $esHoy ? 'btn-primary' : 'btn-outline-primary' }}">Hoy</a>
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => \Carbon\Carbon::parse($fecha)->addDay()->toDateString()]) }}"
                       class="btn btn-outline-secondary"><i class="ti ti-chevron-right"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        {{-- Línea de tiempo de turnos --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="ti ti-clock-hour-4 me-2"></i>
                    {{ $doctor ? $doctor->nombre_profesional : 'Todos los profesionales' }}
                </h3>
                <span class="badge bg-secondary-lt ms-auto">{{ $nombreDia }}</span>
            </div>

            @if ($turnos->isEmpty())
                <div class="card-body">
                    <x-vacio icono="ti ti-calendar-off" titulo="Sin turnos este día"
                             texto="No hay citas programadas para la fecha seleccionada.">
                        @can('citas.crear')
                            <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor?->id]) }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>Crear turno
                            </a>
                        @endcan
                    </x-vacio>
                </div>
            @else
                <div class="card-body p-2">
                    @foreach ($turnos as $turno)
                        <div class="card card-sm mb-2" style="background: var(--tblr-bg-surface-secondary);">
                            <div class="card-body">
                                <div class="row align-items-center g-2">
                                    <div class="col-auto">
                                        <div class="h2 mb-0 font-monospace">{{ substr($turno->hora, 0, 5) }}</div>
                                    </div>
                                    <div class="col">
                                        <div class="fw-medium">
                                            <i class="ti ti-user me-1 text-secondary"></i>{{ $turno->paciente->nombre_completo }}
                                        </div>
                                        <div class="text-secondary small">
                                            <i class="ti ti-stethoscope me-1"></i>{{ $turno->doctor->nombre_profesional }}
                                        </div>
                                        <div class="text-secondary small mt-1">{{ $turno->tratamiento->nombre }}</div>
                                        @if ($turno->paciente->aseguradora)
                                            <span class="badge bg-azure-lt mt-1">
                                                <i class="ti ti-shield-heart me-1"></i>{{ $turno->paciente->aseguradora->nombre }}
                                            </span>
                                        @endif
                                        @if ($turno->origen === 'ONLINE')
                                            <span class="badge bg-purple-lt mt-1"><i class="ti ti-world me-1"></i>Reserva en línea</span>
                                        @endif
                                    </div>
                                    <div class="col-auto text-end">
                                        <span class="badge bg-{{ $turno->color_estado }}-lt mb-2 d-block">
                                            {{ $turno->estado_legible }}
                                        </span>
                                        <div class="btn-list flex-nowrap justify-content-end">
                                            @can('citas.ver')
                                                <a href="{{ route('admin.citas.show', $turno) }}"
                                                   class="btn btn-sm btn-outline-secondary" title="Ver cita">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            @endcan
                                            @can('historiales.crear')
                                                <a href="{{ route('admin.historiales.create', ['paciente' => $turno->paciente_id, 'cita_id' => $turno->id]) }}"
                                                   class="btn btn-sm btn-outline-azure" title="Registrar consulta">
                                                    <i class="ti ti-notes-medical"></i>
                                                </a>
                                            @endcan
                                            @can('odontogramas.crear')
                                                <a href="{{ route('admin.odontogramas.create', ['paciente' => $turno->paciente_id, 'cita_id' => $turno->id]) }}"
                                                   class="btn btn-sm btn-outline-purple" title="Odontograma">
                                                    <i class="ti ti-dental"></i>
                                                </a>
                                            @endcan
                                            @can('pagos.crear')
                                                @unless ($turno->esta_pagada)
                                                    <a href="{{ route('admin.pagos.create', ['cita_id' => $turno->id]) }}"
                                                       class="btn btn-sm btn-outline-orange" title="Cobrar">
                                                        <i class="ti ti-cash"></i>
                                                    </a>
                                                @endunless
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        @if ($doctor && $cupos->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Cupos del día</h3>
                    <span class="badge bg-secondary-lt ms-auto">
                        {{ $cupos->where('disponible', true)->count() }} libres
                    </span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-1">
                        @foreach ($cupos as $cupo)
                            @if ($cupo['disponible'])
                                @can('citas.crear')
                                    <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor->id, 'fecha' => $fecha, 'hora' => $cupo['hora']]) }}"
                                       class="btn btn-sm btn-outline-success" title="Agendar a las {{ $cupo['hora'] }}">{{ $cupo['hora'] }}</a>
                                @else
                                    <span class="btn btn-sm btn-outline-success disabled">{{ $cupo['hora'] }}</span>
                                @endcan
                            @else
                                <span class="btn btn-sm btn-secondary disabled" title="Ocupado">{{ $cupo['hora'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h3 class="card-title">Próximos días</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($proximosDias as $dia)
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $dia->fecha]) }}"
                       class="list-group-item list-group-item-action d-flex align-items-center">
                        <div class="flex-fill">
                            {{ ucfirst(\Carbon\Carbon::parse($dia->fecha)->translatedFormat('l d/m')) }}
                        </div>
                        <span class="badge bg-primary-lt">{{ $dia->total }} turnos</span>
                    </a>
                @empty
                    <div class="list-group-item text-secondary text-center py-4">
                        Sin turnos programados en los próximos siete días.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
