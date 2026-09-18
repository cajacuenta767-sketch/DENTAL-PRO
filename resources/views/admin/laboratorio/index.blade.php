@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Laboratorio Dental y Prótesis')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.laboratorio.catalogo') }}" class="btn btn-outline-secondary">
            <i class="ti ti-building-warehouse me-1"></i>Laboratorios asociados
        </a>
        <div class="btn-group">
            <a href="{{ route('admin.laboratorio.index', array_merge(request()->query(), ['vista' => 'kanban'])) }}"
               class="btn {{ $vista === 'kanban' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="ti ti-layout-kanban me-1"></i>Kanban
            </a>
            <a href="{{ route('admin.laboratorio.index', array_merge(request()->query(), ['vista' => 'tabla'])) }}"
               class="btn {{ $vista === 'tabla' ? 'btn-primary' : 'btn-outline-primary' }}">
                <i class="ti ti-table me-1"></i>Tabla
            </a>
        </div>
        @can('laboratorio.crear')
            <a href="{{ route('admin.laboratorio.create') }}" class="btn btn-success">
                <i class="ti ti-plus me-1"></i>Nueva orden
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Tarjetas KPI --}}
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">Total órdenes</div>
                <div class="h2 mb-0">{{ $resumen['total'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm border-primary">
            <div class="card-body">
                <div class="subheader text-primary">Activas</div>
                <div class="h2 mb-0 text-primary">{{ $resumen['activas'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader text-info">En proceso lab</div>
                <div class="h2 mb-0 text-info">{{ $resumen['en_proceso'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader text-warning">Prueba clínica</div>
                <div class="h2 mb-0 text-warning">{{ $resumen['prueba'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader text-success">Listas en clínica</div>
                <div class="h2 mb-0 text-success">{{ $resumen['terminadas'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm {{ $resumen['vencidas'] > 0 ? 'bg-danger-lt border-danger' : '' }}">
            <div class="card-body">
                <div class="subheader {{ $resumen['vencidas'] > 0 ? 'text-danger' : '' }}">Vencidas</div>
                <div class="h2 mb-0 {{ $resumen['vencidas'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $resumen['vencidas'] }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Barra de filtros --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.laboratorio.index') }}" class="row g-2 align-items-center">
            <input type="hidden" name="vista" value="{{ $vista }}">
            <div class="col-md-3">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Folio, paciente, trabajo..." value="{{ request('q') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="laboratorio_id" class="form-select form-select-sm">
                    <option value="">— Todos los laboratorios —</option>
                    @foreach ($laboratorios as $lab)
                        <option value="{{ $lab->id }}" @selected(request('laboratorio_id') == $lab->id)>{{ $lab->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="doctor_id" class="form-select form-select-sm">
                    <option value="">— Todos los doctores —</option>
                    @foreach ($doctores as $doc)
                        <option value="{{ $doc->id }}" @selected(request('doctor_id') == $doc->id)>{{ $doc->nombre_profesional }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos los estados —</option>
                    @foreach (array_keys(\App\Models\OrdenLaboratorio::ESTADOS) as $k)
                        <option value="{{ $k }}" @selected(request('estado') === $k)>{{ \App\Models\OrdenLaboratorio::ESTADOS[$k] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Filtrar"><i class="ti ti-filter"></i></button>
                @if (request()->hasAny(['q', 'laboratorio_id', 'doctor_id', 'estado']))
                    <a href="{{ route('admin.laboratorio.index', ['vista' => $vista]) }}" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="ti ti-x"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

@if ($vista === 'kanban')
    {{-- Vista Tablero Kanban --}}
    <div class="row g-2 row-deck" style="overflow-x: auto; flex-wrap: nowrap; min-height: 520px; padding-bottom: 1rem;">
        @foreach (array_keys(\App\Models\OrdenLaboratorio::ESTADOS) as $claveEstado)
            @php
                $itemsColumna = $porEstado[$claveEstado] ?? collect();
                $nombreEstado = \App\Models\OrdenLaboratorio::ESTADOS[$claveEstado];
                $colorEstado = \App\Models\OrdenLaboratorio::COLORES_ESTADO[$claveEstado] ?? 'secondary';
            @endphp
            <div class="col-12 col-md-4 col-xl-2" style="min-width: 260px; max-width: 320px;">
                <div class="card h-100 border-top border-top-wide border-{{ $colorEstado }} bg-body-tertiary">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-transparent">
                        <h4 class="card-title mb-0 small text-uppercase fw-bold text-{{ $colorEstado }}">
                            {{ $nombreEstado }}
                        </h4>
                        <span class="badge bg-{{ $colorEstado }}-lt rounded-pill">{{ $itemsColumna->count() }}</span>
                    </div>
                    <div class="card-body p-2 d-flex flex-column gap-2" style="max-height: 70vh; overflow-y: auto;">
                        @forelse ($itemsColumna as $orden)
                            <div class="card shadow-sm border {{ $orden->esta_vencida ? 'border-danger' : '' }}" style="background: var(--tblr-card-bg, #fff);">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <a href="{{ route('admin.laboratorio.show', $orden) }}" class="fw-bold font-monospace text-reset text-decoration-none">
                                            {{ $orden->folio }}
                                        </a>
                                        @if ($orden->color_guia)
                                            <span class="badge bg-purple-lt small" title="Color VITA">{{ $orden->color_guia }}</span>
                                        @endif
                                    </div>
                                    <div class="fw-semibold text-truncate mb-1" title="{{ $orden->tipo_trabajo }}">
                                        {{ $orden->tipo_trabajo }}
                                    </div>
                                    <div class="small text-secondary mb-1">
                                        <i class="ti ti-user me-1"></i>
                                        <a href="{{ route('admin.pacientes.show', $orden->paciente) }}" class="text-reset text-decoration-none">
                                            {{ $orden->paciente->nombre_completo }}
                                        </a>
                                    </div>
                                    <div class="small text-secondary mb-2">
                                        <i class="ti ti-building me-1"></i>{{ $orden->laboratorio?->nombre ?: 'Sin lab asignado' }}
                                    </div>

                                    @if (!empty($orden->dientes_array))
                                        <div class="mb-2">
                                            @foreach (array_slice($orden->dientes_array, 0, 4) as $d)
                                                <span class="badge bg-secondary-lt font-monospace px-1">{{ $d }}</span>
                                            @endforeach
                                            @if (count($orden->dientes_array) > 4)
                                                <span class="badge bg-secondary-lt px-1">+{{ count($orden->dientes_array) - 4 }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                                        <div>
                                            @if ($orden->esta_vencida)
                                                <span class="text-danger fw-bold" title="Venció el {{ $orden->fecha_prometida->format('d/m/Y') }}">
                                                    <i class="ti ti-alert-circle me-1"></i>{{ $orden->fecha_prometida->format('d/m') }}
                                                </span>
                                            @else
                                                <span class="text-secondary" title="Fecha prometida">
                                                    <i class="ti ti-calendar me-1"></i>{{ $orden->fecha_prometida->format('d/m') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.laboratorio.pdf', $orden) }}" class="btn btn-sm btn-icon btn-ghost-secondary" title="Descargar ticket PDF">
                                                <i class="ti ti-printer"></i>
                                            </a>
                                            <a href="{{ route('admin.laboratorio.show', $orden) }}" class="btn btn-sm btn-icon btn-ghost-primary" title="Ver detalle">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-secondary py-4 small">
                                <i class="ti ti-inbox fs-2 opacity-50 d-block mb-1"></i>
                                Sin órdenes
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    {{-- Vista Tabla --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter table-hover card-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Paciente</th>
                        <th>Trabajo</th>
                        <th>Piezas</th>
                        <th>Color</th>
                        <th>Laboratorio</th>
                        <th>Doctor</th>
                        <th>Prometido</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ordenes as $orden)
                        <tr class="{{ $orden->esta_vencida ? 'table-danger-subtle' : '' }}">
                            <td>
                                <a href="{{ route('admin.laboratorio.show', $orden) }}" class="font-monospace fw-bold">
                                    {{ $orden->folio }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('admin.pacientes.show', $orden->paciente) }}" class="text-reset">
                                    {{ $orden->paciente->nombre_completo }}
                                </a>
                            </td>
                            <td class="fw-medium">{{ $orden->tipo_trabajo }}</td>
                            <td>
                                @if (!empty($orden->dientes_array))
                                    @foreach ($orden->dientes_array as $d)
                                        <span class="badge bg-secondary-lt font-monospace px-1">{{ $d }}</span>
                                    @endforeach
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($orden->color_guia)
                                    <span class="badge bg-purple-lt">{{ $orden->color_guia }}</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td>{{ $orden->laboratorio?->nombre ?: '—' }}</td>
                            <td>{{ $orden->doctor->nombre_profesional }}</td>
                            <td>
                                @if ($orden->esta_vencida)
                                    <span class="text-danger fw-bold"><i class="ti ti-alert-triangle me-1"></i>{{ $orden->fecha_prometida->format('d/m/Y') }}</span>
                                @else
                                    {{ $orden->fecha_prometida->format('d/m/Y') }}
                                    <small class="text-secondary d-block">({{ $orden->dias_restantes }}d)</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $orden->color_badge }}">
                                    {{ $orden->estado_legible }}
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-icon btn-ghost-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="{{ route('admin.laboratorio.show', $orden) }}">
                                            <i class="ti ti-eye me-2"></i>Ver detalle
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.laboratorio.edit', $orden) }}">
                                            <i class="ti ti-edit me-2"></i>Editar
                                        </a>
                                        <a class="dropdown-item" href="{{ route('admin.laboratorio.pdf', $orden) }}">
                                            <i class="ti ti-printer me-2"></i>Imprimir ticket PDF
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <div class="dropdown-header">Cambiar estado</div>
                                        @foreach (\App\Models\OrdenLaboratorio::ESTADOS as $k => $nombreEst)
                                            @if ($k !== $orden->estado)
                                                <form action="{{ route('admin.laboratorio.estado', $orden) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="estado" value="{{ $k }}">
                                                    <button type="submit" class="dropdown-item">
                                                        <span class="status-dot status-{{ \App\Models\OrdenLaboratorio::COLORES_ESTADO[$k] ?? 'secondary' }} me-2"></span>Pasar a {{ $nombreEst }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4 text-secondary">
                                No se encontraron órdenes de laboratorio con los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
