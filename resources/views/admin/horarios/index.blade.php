@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Horarios de Atención')

@section('acciones')
    @can('horarios.crear')
        <a href="{{ route('admin.horarios.create', ['doctor_id' => request('doctor_id')]) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nuevo Horario
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card">
    <div class="card-header"><h3 class="card-title">Turnos registrados</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="doctor_id" class="form-select">
                    <option value="">— Todos los doctores —</option>
                    @foreach ($doctores as $doctor)
                        <option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>
                            {{ $doctor->nombre_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if (($sucursales ?? collect())->count() > 1 && ! $sucursalActiva)
                <div class="col-md-3">
                    <select name="sucursal_id" class="form-select">
                        <option value="">— Todas las sedes —</option>
                        @foreach ($sucursales as $sede)
                            <option value="{{ $sede->id }}" @selected(request('sucursal_id') == $sede->id)>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <select name="dia_semana" class="form-select">
                    <option value="">— Todos los días —</option>
                    @foreach ($dias as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('dia_semana') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary w-100"><i class="ti ti-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Doctor</th>
                    <th>Especialidad</th>
                    @if ($sedes->count() > 1)
                        <th>Sede</th>
                    @endif
                    <th>Día</th>
                    <th>Turno</th>
                    <th>Horario</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($horarios as $horario)
                    <tr>
                        <td class="fw-medium">{{ $horario->doctor->nombre_profesional }}</td>
                        <td>
                            <span class="badge" style="background-color: {{ $horario->doctor->especialidad->color }}20; color: {{ $horario->doctor->especialidad->color }}">
                                {{ $horario->doctor->especialidad->nombre }}
                            </span>
                        </td>
                        @if ($sedes->count() > 1)
                            <td>
                                @if ($sede = $sedes->get($horario->sucursal_id))
                                    <span class="badge" style="background-color: {{ $sede->color }}20; color: {{ $sede->color }}">{{ $sede->nombre }}</span>
                                @else
                                    <span class="text-secondary small">Cualquier sede</span>
                                @endif
                            </td>
                        @endif
                        <td>{{ config("odontosuite.dias_semana.{$horario->dia_semana}", $horario->dia_semana) }}</td>
                        <td><span class="badge bg-azure-lt">{{ $horario->turno }}</span></td>
                        <td><i class="ti ti-clock me-1 text-secondary"></i>{{ $horario->rango }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $horario->activo ? 'success' : 'secondary' }}-lt">
                                {{ $horario->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('horarios.editar')
                                    <a href="{{ route('admin.horarios.edit', $horario) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('horarios.eliminar')
                                    <form method="POST" action="{{ route('admin.horarios.destroy', $horario) }}"
                                          data-confirmar="¿Eliminar este turno?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $sedes->count() > 1 ? 8 : 7 }}">
                            <x-vacio icono="ti ti-clock-off" titulo="Sin horarios"
                                     texto="Define la disponibilidad semanal de cada doctor para habilitar la agenda." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($horarios->hasPages())
        <div class="card-footer">{{ $horarios->links() }}</div>
    @endif
</div>
@endsection
