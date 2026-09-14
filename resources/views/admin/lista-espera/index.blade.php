@extends('layouts.admin')

@section('pretitulo', 'Administración')
@section('titulo', 'Lista de Espera')

@section('acciones')
    @can('lista_espera.crear')
        <a href="{{ route('admin.lista-espera.create') }}" class="btn btn-primary">
            <i class="ti ti-user-plus me-1"></i>Añadir a la lista
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Esperando turno" :valor="$totales['esperando']" icono="ti ti-hourglass" color="warning" pie="pacientes en espera" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Contactados" :valor="$totales['contactados']" icono="ti ti-phone-call" color="azure" pie="pendientes de agendar" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Prioridad alta" :valor="$totales['altaPrioridad']" icono="ti ti-alert-triangle"
               :color="$totales['altaPrioridad'] > 0 ? 'danger' : 'secondary'" pie="entradas abiertas" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Agendados este mes" :valor="$totales['agendadosMes']" icono="ti ti-calendar-check" color="success" pie="cupos asignados" />
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
                       placeholder="Paciente o documento...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">— Abiertas —</option>
                    @foreach (\App\Models\ListaEspera::ESTADOS as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('estado') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Doctor</label>
                <select name="doctor_id" class="form-select">
                    <option value="">— Todos los doctores —</option>
                    @foreach ($doctores as $doctor)
                        <option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>{{ $doctor->nombre_profesional }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Prioridad</label>
                <select name="prioridad" class="form-select">
                    <option value="">— Todas —</option>
                    @foreach (\App\Models\ListaEspera::PRIORIDADES as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('prioridad') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill"><i class="ti ti-search me-1"></i>Filtrar</button>
                <a href="{{ route('admin.lista-espera.index') }}" class="btn btn-outline-secondary" title="Limpiar">
                    <i class="ti ti-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-list-numbers me-2"></i>Pacientes en espera de turno</h3>
        <span class="badge bg-secondary-lt ms-auto">{{ $entradas->total() }} registros</span>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Paciente</th>
                    <th>Doctor / Especialidad</th>
                    <th>Tratamiento</th>
                    <th>Disponibilidad</th>
                    <th class="text-center">Prioridad</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entradas as $entrada)
                    @php $abierta = in_array($entrada->estado, ['ESPERANDO', 'CONTACTADO'], true); @endphp
                    <tr class="{{ $entrada->estado === 'CANCELADO' ? 'opacity-75' : '' }}">
                        <td class="text-secondary">{{ $loop->iteration + ($entradas->currentPage() - 1) * $entradas->perPage() }}</td>
                        <td>
                            <div class="fw-medium">{{ $entrada->paciente->nombre_completo }}</div>
                            <div class="text-secondary small">
                                <i class="ti ti-id me-1"></i>{{ $entrada->paciente->numero_documento }}
                                @if ($entrada->paciente->telefono)
                                    <span class="ms-2"><i class="ti ti-phone me-1"></i>{{ $entrada->paciente->telefono }}</span>
                                @endif
                            </div>
                            <div class="text-secondary small">En lista desde el {{ $entrada->created_at->format('d/m/Y') }}</div>
                        </td>
                        <td>
                            <div>{{ $entrada->doctor?->nombre_profesional ?? 'Cualquier doctor' }}</div>
                            <div class="text-secondary small">
                                {{ $entrada->especialidad?->nombre ?? $entrada->doctor?->especialidad?->nombre ?? '—' }}
                            </div>
                        </td>
                        <td>
                            {{ $entrada->tratamiento?->nombre ?? '—' }}
                            @if ($entrada->notas)
                                <div class="text-secondary small text-truncate" style="max-width: 16rem;" title="{{ $entrada->notas }}">
                                    <i class="ti ti-note me-1"></i>{{ $entrada->notas }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div>
                                @if ($entrada->fecha_desde || $entrada->fecha_hasta)
                                    {{ $entrada->fecha_desde?->format('d/m/Y') ?? '…' }} → {{ $entrada->fecha_hasta?->format('d/m/Y') ?? '…' }}
                                @else
                                    <span class="text-secondary">Sin restricción</span>
                                @endif
                            </div>
                            <div class="text-secondary small">
                                <i class="ti ti-sun me-1"></i>{{ \App\Models\ListaEspera::TURNOS[$entrada->preferencia_turno] ?? $entrada->preferencia_turno }}
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $entrada->color_prioridad }}-lt">
                                {{ \App\Models\ListaEspera::PRIORIDADES[$entrada->prioridad] ?? $entrada->prioridad }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $entrada->color_estado }}-lt">{{ $entrada->estado_legible }}</span>
                            @if ($entrada->estado === 'AGENDADO' && $entrada->cita)
                                <div class="mt-1">
                                    <a href="{{ route('admin.citas.show', $entrada->cita) }}" class="small text-brand">
                                        <i class="ti ti-calendar-event me-1"></i>{{ $entrada->cita->fecha->format('d/m/Y') }} {{ substr($entrada->cita->hora, 0, 5) }}
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('lista_espera.editar')
                                    @if ($entrada->estado === 'ESPERANDO')
                                        <form method="POST" action="{{ route('admin.lista-espera.estado', $entrada) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="estado" value="CONTACTADO">
                                            <button class="btn btn-sm btn-outline-azure" title="Marcar como contactado">
                                                <i class="ti ti-phone-call"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                                @can('citas.crear')
                                    @if ($abierta)
                                        <a href="{{ route('admin.citas.create', ['paciente_id' => $entrada->paciente_id, 'doctor_id' => $entrada->doctor_id, 'tratamiento_id' => $entrada->tratamiento_id, 'lista_espera_id' => $entrada->id]) }}"
                                           class="btn btn-sm btn-outline-success" title="Agendar cita">
                                            <i class="ti ti-calendar-plus"></i>
                                        </a>
                                    @endif
                                @endcan
                                @can('lista_espera.editar')
                                    @if ($abierta)
                                        <form method="POST" action="{{ route('admin.lista-espera.estado', $entrada) }}"
                                              data-confirmar="¿Cancelar la entrada de {{ $entrada->paciente->nombre_completo }} en la lista de espera?">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="estado" value="CANCELADO">
                                            <button class="btn btn-sm btn-outline-warning" title="Cancelar">
                                                <i class="ti ti-ban"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.lista-espera.edit', $entrada) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('lista_espera.eliminar')
                                    <form method="POST" action="{{ route('admin.lista-espera.destroy', $entrada) }}"
                                          data-confirmar="¿Eliminar definitivamente esta entrada de la lista de espera?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-vacio icono="ti ti-hourglass-off" titulo="Sin pacientes en espera"
                                     texto="Ninguna entrada coincide con los filtros aplicados.">
                                @can('lista_espera.crear')
                                    <a href="{{ route('admin.lista-espera.create') }}" class="btn btn-primary">
                                        <i class="ti ti-user-plus me-1"></i>Añadir a la lista
                                    </a>
                                @endcan
                            </x-vacio>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($entradas->hasPages())
        <div class="card-footer">{{ $entradas->links() }}</div>
    @endif
</div>
@endsection
