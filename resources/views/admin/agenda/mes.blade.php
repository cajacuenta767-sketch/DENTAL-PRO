@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Agenda mensual')
@section('subtitulo', ucfirst($mes->locale('es')->isoFormat('MMMM [de] YYYY')).' · '.$totalCitas.' citas')

@push('head')
<style>
    .mes-tabla { table-layout: fixed; }
    .mes-tabla th { text-align: center; font-size: .75rem; text-transform: uppercase; }
    .mes-tabla td { height: 7.5rem; padding: 0 !important; vertical-align: top; }
    .mes-dia { display: block; height: 100%; padding: .4rem .5rem; color: inherit; text-decoration: none; }
    .mes-dia:hover { background: var(--tblr-bg-surface-secondary); }
    .mes-dia.fuera-de-mes { color: var(--tblr-secondary); opacity: .55; }
    .mes-dia .numero { font-weight: 600; font-size: .9rem; }
    .mes-dia.es-hoy .numero { display: inline-flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: 50%; background: var(--tblr-primary); color: #fff; }
    .mes-dia .cita-linea { font-size: .72rem; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mes-dia .cita-linea .hora { font-family: var(--tblr-font-monospace); color: var(--tblr-secondary); }
    .mes-dia .badge { font-size: .6rem; padding: .15rem .35rem; }
</style>
@endpush

@section('acciones')
    <div class="btn-list">
        <div class="btn-group">
            <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $esMesActual ? now()->toDateString() : $fecha]) }}" class="btn btn-outline-primary">Día</a>
            <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => $esMesActual ? now()->toDateString() : $fecha]) }}" class="btn btn-outline-primary">Semana</a>
            <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-primary">Mes</a>
        </div>
        @can('citas.crear')
            <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor?->id]) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Crear turno
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
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
                <label class="form-label">Mes</label>
                <input type="month" name="fecha" value="{{ $mes->format('Y-m') }}" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <div class="btn-list">
                    <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => $mes->subMonth()->toDateString()]) }}"
                       class="btn btn-outline-secondary" title="Mes anterior"><i class="ti ti-chevron-left"></i></a>
                    <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => now()->toDateString()]) }}"
                       class="btn {{ $esMesActual ? 'btn-primary' : 'btn-outline-primary' }}">Hoy</a>
                    <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => $mes->addMonth()->toDateString()]) }}"
                       class="btn btn-outline-secondary" title="Mes siguiente"><i class="ti ti-chevron-right"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-calendar-month me-2"></i>{{ $doctor ? $doctor->nombre_profesional : 'Todos los profesionales' }}
        </h3>
        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
            @foreach (\App\Models\Cita::COLORES_ESTADO as $estado => $color)
                <span class="badge bg-{{ $color }}-lt">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}</span>
            @endforeach
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered card-table mes-tabla mb-0">
            <thead>
                <tr>
                    @foreach (\App\Models\Horario::DIAS as $dia)
                        <th>{{ config('odontosuite.dias_semana.'.$dia) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($semanas as $semana)
                    <tr>
                        @foreach ($semana as $celda)
                            <td>
                                <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $celda['fecha']->toDateString()]) }}"
                                   class="mes-dia {{ $celda['delMes'] ? '' : 'fuera-de-mes' }} {{ $celda['esHoy'] ? 'es-hoy' : '' }}"
                                   title="Ver agenda del {{ $celda['fecha']->format('d/m/Y') }}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="numero">{{ $celda['fecha']->format('j') }}</span>
                                        @if ($celda['total'])
                                            <span class="text-secondary small">{{ $celda['total'] }}</span>
                                        @endif
                                    </div>
                                    @if ($celda['porEstado']->isNotEmpty())
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            @foreach ($celda['porEstado'] as $estado => $cantidad)
                                                <span class="badge bg-{{ \App\Models\Cita::COLORES_ESTADO[$estado] ?? 'secondary' }}-lt"
                                                      title="{{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}">{{ $cantidad }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @foreach ($celda['primeras'] as $cita)
                                        <div class="cita-linea">
                                            <span class="hora">{{ substr($cita->hora, 0, 5) }}</span>
                                            {{ $cita->paciente->nombre_completo }}
                                        </div>
                                    @endforeach
                                    @if ($celda['restantes'])
                                        <div class="cita-linea text-secondary">+{{ $celda['restantes'] }} más</div>
                                    @endif
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
