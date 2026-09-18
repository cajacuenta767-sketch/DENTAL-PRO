@extends('layouts.admin')

@section('pretitulo', 'Cirugía e Implantología')
@section('titulo', 'Registro General de Implantes Dentales')
@section('subtitulo', 'Trazabilidad de lotes, aditamentos protésicos y pasaportes implantológicos digitales')

@section('acciones')
    <a href="{{ route('admin.pacientes.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-users me-1"></i>Ir a Pacientes
    </a>
@endsection

@section('contenido')
@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form action="{{ route('admin.implantes.index') }}" method="GET" class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Buscar por paciente, lote o modelo</label>
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="buscar" value="{{ request('buscar') }}" class="form-control" placeholder="P. ej.: Straumann, LOT1234, Pérez...">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Marca comercial</label>
                <input type="text" name="marca" value="{{ request('marca') }}" class="form-control" placeholder="Nobel, Straumann, Neodent...">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Estado clínico</label>
                <select name="estado" class="form-select">
                    <option value="">-- Todos los estados --</option>
                    @foreach ($estados as $k => $label)
                        <option value="{{ $k }}" {{ request('estado') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-1">
                <button type="submit" class="btn btn-secondary w-50">Filtrar</button>
                <a href="{{ route('admin.implantes.index') }}" class="btn btn-link w-50">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-needle me-2"></i>Implantes Registrados en el Sistema</h3>
        <span class="badge bg-secondary-lt">{{ $implantes->total() }} registros</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Pieza FDI</th>
                    <th>Paciente</th>
                    <th>Marca y Modelo</th>
                    <th>Lote / Serie</th>
                    <th>Dimensiones y Conexión</th>
                    <th>Estabilidad</th>
                    <th>Fecha Colocación</th>
                    <th>Estado</th>
                    <th class="w-1 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($implantes as $imp)
                    <tr>
                        <td>
                            <span class="badge bg-teal fs-4 px-2 py-1">{{ $imp->posicion_fdi }}</span>
                        </td>
                        <td>
                            <a href="{{ route('admin.pacientes.show', $imp->paciente) }}" class="fw-bold text-dark text-decoration-none">
                                {{ $imp->paciente->nombre_completo }}
                            </a>
                            <div class="small text-secondary">Doc: {{ $imp->paciente->numero_documento }}</div>
                        </td>
                        <td>
                            <div class="fw-medium text-dark">{{ $imp->marca }}</div>
                            <div class="small text-secondary">{{ $imp->modelo }}</div>
                        </td>
                        <td>
                            <div class="small"><span class="fw-bold">LOT:</span> <code>{{ $imp->numero_lote }}</code></div>
                            @if ($imp->numero_serie)
                                <div class="small text-secondary">SN: {{ $imp->numero_serie }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="small fw-medium">&Oslash; {{ $imp->diametro_mm }} mm &times; {{ $imp->longitud_mm }} mm</div>
                            <div class="small text-secondary">{{ $imp->conexion_nombre }}</div>
                        </td>
                        <td>
                            <div class="small">Torque: <strong>{{ $imp->torque_insercion_ncm ? $imp->torque_insercion_ncm.' Ncm' : '—' }}</strong></div>
                            <div class="small">ISQ: <strong>{{ $imp->isq_estabilidad ?: '—' }}</strong></div>
                        </td>
                        <td>
                            <div>{{ $imp->fecha_colocacion->format('d/m/Y') }}</div>
                            <div class="small text-secondary">{{ $imp->doctor?->nombre_profesional ?? 'Dr. Cirujano' }}</div>
                        </td>
                        <td>
                            {!! $imp->estado_badge !!}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.implantes.pasaporte', $imp) }}" target="_blank" class="btn btn-sm btn-outline-teal" title="Pasaporte de Implante Digital">
                                <i class="ti ti-id me-1"></i>Pasaporte
                            </a>
                            <a href="{{ route('admin.implantes.paciente', $imp->paciente) }}" class="btn btn-sm btn-outline-secondary" title="Ficha de implantes del paciente">
                                <i class="ti ti-folder"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-secondary">
                            <i class="ti ti-needle-off fs-1 d-block mb-2 text-muted"></i>
                            No se encontraron registros de implantes con los filtros ingresados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($implantes->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $implantes->links() }}
        </div>
    @endif
</div>
@endsection
