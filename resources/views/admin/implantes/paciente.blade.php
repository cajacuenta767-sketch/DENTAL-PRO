@extends('layouts.admin')

@section('pretitulo', 'Especialidades Clínicas')
@section('titulo', 'Implantología · '.$paciente->nombre_completo)
@section('subtitulo', 'Registro de implantes dentales, osteointegración y pasaporte digital')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha</a>
        @can('odontogramas.crear')
            <a href="{{ route('admin.implantes.create', $paciente) }}" class="btn btn-teal">
                <i class="ti ti-plus me-1"></i>Nuevo Implante
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'implantes'])

@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-needle me-2"></i>Implantes Dentales del Paciente</h3>
        <span class="badge bg-teal-lt">{{ $implantes->count() }} implantes registrados</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Pieza FDI</th>
                    <th>Marca y Modelo</th>
                    <th>Lote y Serie</th>
                    <th>Dimensiones & Conexión</th>
                    <th>Torque / ISQ</th>
                    <th>Regeneración Ósea</th>
                    <th>Fecha Colocación / Rehab</th>
                    <th>Estado</th>
                    <th class="w-1 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($implantes as $imp)
                    <tr>
                        <td>
                            <span class="badge bg-teal fs-3 px-2 py-1">{{ $imp->posicion_fdi }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $imp->marca }}</div>
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
                            <div class="small">{{ $imp->injerto_oseo ? 'Injerto: '.$imp->injerto_oseo : 'Sin injerto' }}</div>
                            <div class="small text-secondary">{{ $imp->membrana ? 'Membrana: '.$imp->membrana : 'Sin membrana' }}</div>
                        </td>
                        <td>
                            <div>Colocación: <strong>{{ $imp->fecha_colocacion->format('d/m/Y') }}</strong></div>
                            <div class="small text-secondary">Rehab: {{ $imp->fecha_rehabilitacion ? $imp->fecha_rehabilitacion->format('d/m/Y') : 'Pendiente' }}</div>
                            <div class="small text-muted">{{ $imp->doctor?->nombre_profesional }}</div>
                        </td>
                        <td>
                            {!! $imp->estado_badge !!}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.implantes.pasaporte', $imp) }}" target="_blank" class="btn btn-sm btn-outline-teal" title="Descargar Pasaporte Digital de Implante">
                                <i class="ti ti-id me-1"></i>Pasaporte PDF
                            </a>
                            @can('odontogramas.editar')
                                <a href="{{ route('admin.implantes.edit', $imp) }}" class="btn btn-sm btn-outline-secondary" title="Editar">
                                    <i class="ti ti-edit"></i>
                                </a>
                            @endcan
                            @can('odontogramas.eliminar')
                                <form action="{{ route('admin.implantes.destroy', $imp) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro del implante pieza {{ $imp->posicion_fdi }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-secondary">
                            <i class="ti ti-needle-off fs-1 d-block mb-2 text-muted"></i>
                            El paciente no tiene implantes registrados aún.
                            <div class="mt-2">
                                <a href="{{ route('admin.implantes.create', $paciente) }}" class="btn btn-teal btn-sm">
                                    <i class="ti ti-plus me-1"></i>Registrar primer implante
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
