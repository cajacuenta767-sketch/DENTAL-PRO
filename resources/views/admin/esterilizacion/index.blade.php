@extends('layouts.admin')

@section('pretitulo', 'Bioseguridad y Esterilización')
@section('titulo', 'Central de Esterilización (Autoclave)')
@section('subtitulo', 'Control de trazabilidad de instrumental quirúrgico e indicadores de esterilización')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.inventario.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Inventario</a>
        @can('inventario.crear')
            <a href="{{ route('admin.esterilizacion.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nuevo Ciclo de Autoclave
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar"><i class="ti ti-refresh"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium">Ciclos Realizados Hoy</div>
                        <div class="text-secondary">{{ $ciclosHoy }} ciclos registrados</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar"><i class="ti ti-shield-check"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium">Ciclos Aprobados Hoy</div>
                        <div class="text-secondary">{{ $aprobadosHoy }} conformes</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-azure text-white avatar"><i class="ti ti-packages"></i></span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium">Paquetes Estériles Vigentes</div>
                        <div class="text-secondary">{{ $paquetesVigentes }} paquetes listos para cirugía</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route('admin.esterilizacion.index') }}" method="GET" class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Fecha del ciclo</label>
                <input type="date" name="fecha" value="{{ request('fecha') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Resultado</label>
                <select name="resultado" class="form-select">
                    <option value="">-- Todos los resultados --</option>
                    @foreach ($resultados as $k => $label)
                        <option value="{{ $k }}" {{ request('resultado') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-secondary w-50"><i class="ti ti-search me-1"></i>Filtrar</button>
                <a href="{{ route('admin.esterilizacion.index') }}" class="btn btn-link w-50">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-history me-2"></i>Historial de Ciclos de Autoclave</h3>
        <span class="badge bg-secondary-lt">{{ $ciclos->total() }} registros</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Ciclo / Autoclave</th>
                    <th>Fecha y Hora</th>
                    <th>Parámetros Físicos</th>
                    <th>Controles Biológico/Químico</th>
                    <th>Carga / Paquetes</th>
                    <th>Caducidad</th>
                    <th>Resultado</th>
                    <th class="w-1 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ciclos as $c)
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">Ciclo #{{ $c->numero_ciclo }}</div>
                            <div class="small text-secondary">{{ $c->autoclave_nombre }}</div>
                        </td>
                        <td>
                            <div>{{ $c->fecha->format('d/m/Y') }}</div>
                            <div class="small text-secondary">{{ $c->hora_inicio ?? '—' }} a {{ $c->hora_fin ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="small"><i class="ti ti-temperature me-1 text-danger"></i><strong>{{ $c->temperatura }} °C</strong></div>
                            <div class="small"><i class="ti ti-gauge me-1 text-primary"></i><strong>{{ $c->presion }} bar</strong> ({{ $c->tiempo_esterilizacion }} min)</div>
                        </td>
                        <td>
                            <div class="small">Químico: <span class="badge {{ $c->indicador_quimico === 'CONFORME' ? 'bg-success-lt' : 'bg-danger-lt' }}">{{ $c->indicador_quimico }}</span></div>
                            <div class="small mt-1">Biológico: <span class="badge {{ $c->indicador_biologico === 'NEGATIVO' ? 'bg-success-lt' : ($c->indicador_biologico === 'PENDIENTE' ? 'bg-warning-lt' : 'bg-danger-lt') }}">{{ $c->indicador_biologico }}</span></div>
                        </td>
                        <td>
                            <div class="small fw-medium">{{ $c->tipo_carga_nombre }}</div>
                            <div class="small text-secondary"><i class="ti ti-package me-1"></i>{{ $c->paquetes_esterilizados }} paquetes</div>
                        </td>
                        <td>
                            @php
                                $diasRestantes = now()->startOfDay()->diffInDays($c->fecha_caducidad_paquetes, false);
                            @endphp
                            <div class="{{ $diasRestantes < 0 ? 'text-danger fw-bold' : ($diasRestantes < 5 ? 'text-warning fw-bold' : 'text-body') }}">
                                {{ $c->fecha_caducidad_paquetes->format('d/m/Y') }}
                            </div>
                            <div class="small text-secondary">
                                @if ($diasRestantes < 0)
                                    Vencido hace {{ abs($diasRestantes) }} d
                                @else
                                    Vigente ({{ $diasRestantes }} d)
                                @endif
                            </div>
                        </td>
                        <td>
                            {!! $c->estado_badge !!}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.esterilizacion.etiquetas', $c) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Imprimir etiquetas para sobres">
                                <i class="ti ti-printer me-1"></i>Etiquetas QR
                            </a>
                            <a href="{{ route('admin.esterilizacion.verificar', $c->qr_token) }}" class="btn btn-sm btn-outline-secondary" title="Verificar autenticidad">
                                <i class="ti ti-qrcode"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-secondary">
                            <i class="ti ti-shield-x fs-1 d-block mb-2 text-muted"></i>
                            No hay ciclos de esterilización registrados con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($ciclos->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $ciclos->links() }}
        </div>
    @endif
</div>
@endsection
