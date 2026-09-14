@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Mis citas')

@if ($ajustes->portal_reservas_activas)
    @section('acciones')
        <a href="{{ route('portal.reservar') }}" class="btn btn-primary">
            <i class="ti ti-calendar-plus me-1"></i>Reservar cita
        </a>
    @endsection
@endif

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-calendar-event me-2 text-primary"></i>Historial de citas</h3>
        <div class="card-actions text-secondary small">
            {{ $citas->total() }} {{ Str::plural('cita', $citas->total()) }}
        </div>
    </div>

    @if ($citas->isEmpty())
        <div class="card-body">
            <x-vacio icono="ti ti-calendar-off" titulo="Aún no tienes citas registradas"
                     texto="Cuando la clínica agende una cita para ti, la verás aquí.">
                @if ($ajustes->portal_reservas_activas)
                    <a href="{{ route('portal.reservar') }}" class="btn btn-primary">
                        <i class="ti ti-calendar-plus me-1"></i>Reservar mi primera cita
                    </a>
                @endif
            </x-vacio>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Doctor</th>
                        <th>Tratamiento</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($citas as $cita)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $cita->fecha_hora }}</div>
                                @if ($cita->es_futura)
                                    <small class="text-secondary">{{ $cita->inicio->locale('es')->diffForHumans() }}</small>
                                @endif
                            </td>
                            <td>
                                {{ $cita->doctor?->nombre_profesional ?? 'Por asignar' }}
                                @if ($cita->doctor?->especialidad)
                                    <div class="text-secondary small">{{ $cita->doctor->especialidad->nombre }}</div>
                                @endif
                            </td>
                            <td>{{ $cita->tratamiento?->nombre ?? '—' }}</td>
                            <td><span class="badge bg-{{ $cita->color_estado }}">{{ $cita->estado_legible }}</span></td>
                            <td class="text-nowrap">
                                @if ($cita->cancelable_por_paciente)
                                    <form method="POST" action="{{ route('portal.citas.cancelar', $cita) }}"
                                          data-confirmar="¿Seguro que deseas cancelar la cita del {{ $cita->fecha_hora }}?">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="ti ti-x me-1"></i>Cancelar
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($citas->hasPages())
            <div class="card-footer d-flex align-items-center">
                {{ $citas->links() }}
            </div>
        @endif
    @endif
</div>

<p class="text-secondary small mt-3 mb-0">
    <i class="ti ti-info-circle me-1"></i>
    Puedes cancelar una cita desde el portal solo con la anticipación mínima que establece la clínica.
    Si necesitas reprogramar, comunícate directamente con recepción.
</p>
@endsection
