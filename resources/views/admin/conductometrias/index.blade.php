@extends('layouts.admin')

@section('pretitulo', 'Especialidades Clínicas')
@section('titulo', 'Endodoncia · '.$paciente->nombre_completo)
@section('subtitulo', 'Matriz de conductometría y registro de conductos radiculares')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha</a>
        <a href="{{ route('admin.conductometrias.create', $paciente) }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nueva conductometría
        </a>
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'endodoncia'])

@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-needle me-2"></i>Historial de tratamientos endodónticos</h3>
        <span class="badge bg-purple-lt">{{ $conductometrias->total() }} registros</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Pieza Dental</th>
                    <th>Diagnóstico Pulpar / Periapical</th>
                    <th>Conductos Registrados</th>
                    <th>Estado</th>
                    <th>Doctor</th>
                    <th class="w-1 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($conductometrias as $item)
                    <tr>
                        <td class="text-nowrap">{{ $item->fecha->format('d/m/Y') }}</td>
                        <td>
                            <span class="badge bg-azure fs-4 px-2 py-1 me-1">{{ $item->diente }}</span>
                            <span class="fw-medium text-body d-block small">{{ $item->nombre_diente }}</span>
                        </td>
                        <td>
                            <div class="fw-medium text-dark">{{ $item->diagnostico_pulpar ?: 'Sin diagnóstico pulpar' }}</div>
                            <div class="text-secondary small">{{ $item->diagnostico_periapical ?: 'Sin diagnóstico periapical' }}</div>
                        </td>
                        <td>
                            @php $conductos = $item->conductos ?? []; @endphp
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($conductos as $c)
                                    <span class="badge bg-secondary-lt" title="Ref: {{ $c['referencia'] ?? '—' }} | LT: {{ $c['longitud_trabajo'] ?? '—' }} mm | Lima: {{ $c['lima_apical'] ?? '—' }}">
                                        {{ $c['nombre'] }}: <strong>{{ $c['longitud_trabajo'] ? $c['longitud_trabajo'].' mm' : 'S/M' }}</strong> ({{ $c['lima_apical'] ?? '—' }})
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $item->color_estado }}-lt">
                                {{ \App\Models\Conductometria::ESTADOS[$item->estado] ?? $item->estado }}
                            </span>
                        </td>
                        <td class="text-nowrap text-secondary small">
                            {{ $item->doctor?->nombre_profesional ?? '—' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-list flex-nowrap justify-content-end">
                                <a href="{{ route('admin.conductometrias.pdf', $conductometria ?? $item) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Imprimir Ficha PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                                <a href="{{ route('admin.conductometrias.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.conductometrias.destroy', $item) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar la conductometría de la pieza {{ $item->diente }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Eliminar">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-secondary">
                            <i class="ti ti-needle fs-1 d-block mb-2 text-muted"></i>
                            No hay matrices de conductometría registradas para este paciente.<br>
                            <a href="{{ route('admin.conductometrias.create', $paciente) }}" class="btn btn-sm btn-primary mt-2">
                                <i class="ti ti-plus me-1"></i>Registrar primera conductometría
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($conductometrias->hasPages())
        <div class="card-footer">{{ $conductometrias->links() }}</div>
    @endif
</div>
@endsection
