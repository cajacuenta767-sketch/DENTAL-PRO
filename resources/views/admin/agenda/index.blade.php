@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $esAgendaPropia ? 'Mi Agenda' : 'Agenda del Doctor')
@section('subtitulo', \Carbon\Carbon::parse($fecha)->format('d/m/Y').' · '.$nombreDia)

@section('acciones')
    @can('citas.crear')
        <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor?->id]) }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i>Agendar Cita
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                    @foreach ($doctores as $d)
                        <option value="{{ $d->id }}" @selected($doctor?->id === $d->id)>
                            {{ $d->nombre_profesional }} · {{ $d->especialidad->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" value="{{ $fecha }}" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <div class="btn-list">
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => \Carbon\Carbon::parse($fecha)->subDay()->toDateString()]) }}"
                       class="btn btn-outline-secondary"><i class="ti ti-chevron-left"></i></a>
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => now()->toDateString()]) }}"
                       class="btn btn-outline-primary">Hoy</a>
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => \Carbon\Carbon::parse($fecha)->addDay()->toDateString()]) }}"
                       class="btn btn-outline-secondary"><i class="ti ti-chevron-right"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

@if (! $doctor)
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-user-off" titulo="Sin doctores activos"
                 texto="Registra al menos un doctor activo para poder ver una agenda." />
    </div></div>
@elseif ($cupos->isEmpty())
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-clock-off" titulo="Sin atención este día"
                 texto="{{ $doctor->nombre_profesional }} no tiene turnos configurados para el {{ $nombreDia }}.">
            @can('horarios.crear')
                <a href="{{ route('admin.horarios.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Configurar horario
                </a>
            @endcan
        </x-vacio>
    </div></div>
@else
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ $doctor->nombre_profesional }}</h3>
                    <span class="badge bg-secondary-lt ms-auto">
                        {{ $cupos->where('disponible', false)->count() }} de {{ $cupos->count() }} cupos ocupados
                    </span>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($cupos as $cupo)
                        <div class="list-group-item {{ $cupo['disponible'] ? '' : 'bg-primary-lt' }}">
                            <div class="row align-items-center g-2">
                                <div class="col-auto">
                                    <span class="badge bg-{{ $cupo['disponible'] ? 'secondary' : 'primary' }}-lt fs-4 px-3">
                                        {{ $cupo['hora'] }}
                                    </span>
                                </div>
                                @if ($cupo['cita'])
                                    <div class="col">
                                        <div class="fw-medium">{{ $cupo['cita']->paciente->nombre_completo }}</div>
                                        <div class="text-secondary small">
                                            {{ $cupo['cita']->tratamiento->nombre }} · código {{ $cupo['cita']->token }}
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <span class="badge bg-{{ $cupo['cita']->color_estado }}-lt">
                                            {{ $cupo['cita']->estado_legible }}
                                        </span>
                                    </div>
                                    <div class="col-auto">
                                        <div class="btn-list flex-nowrap">
                                            @can('citas.ver')
                                                <a href="{{ route('admin.citas.show', $cupo['cita']) }}"
                                                   class="btn btn-sm btn-outline-secondary" title="Ver cita">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                            @endcan
                                            @can('historiales.crear')
                                                <a href="{{ route('admin.historiales.create', ['paciente' => $cupo['cita']->paciente_id, 'cita_id' => $cupo['cita']->id]) }}"
                                                   class="btn btn-sm btn-outline-azure" title="Registrar consulta">
                                                    <i class="ti ti-notes-medical"></i>
                                                </a>
                                            @endcan
                                            @can('odontogramas.crear')
                                                <a href="{{ route('admin.odontogramas.create', ['paciente' => $cupo['cita']->paciente_id, 'cita_id' => $cupo['cita']->id]) }}"
                                                   class="btn btn-sm btn-outline-purple" title="Odontograma">
                                                    <i class="ti ti-dental"></i>
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                @else
                                    <div class="col text-secondary">Cupo libre · turno {{ $cupo['turno'] }}</div>
                                    <div class="col-auto">
                                        @can('citas.crear')
                                            <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor->id]) }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="ti ti-plus me-1"></i>Agendar
                                            </a>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="row row-cards">
                <div class="col-sm-6 col-lg-12">
                    <x-kpi titulo="Citas del día" :valor="$cupos->whereNotNull('cita')->count()" icono="ti ti-calendar-event" color="primary" />
                </div>
                <div class="col-sm-6 col-lg-12">
                    <x-kpi titulo="Cupos libres" :valor="$cupos->where('disponible', true)->count()" icono="ti ti-calendar-plus" color="success" />
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Turnos configurados</h3></div>
                        <div class="list-group list-group-flush">
                            @foreach ($doctor->horarios()->activos()->orderBy('hora_inicio')->get()->groupBy('dia_semana') as $dia => $turnos)
                                <div class="list-group-item">
                                    <div class="fw-medium">{{ config("odontosuite.dias_semana.{$dia}", $dia) }}</div>
                                    <div class="text-secondary small">
                                        {{ $turnos->map(fn ($t) => $t->rango)->implode(' · ') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
