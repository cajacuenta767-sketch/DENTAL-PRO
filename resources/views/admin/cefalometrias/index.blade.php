@extends('layouts.admin')

@section('pretitulo', 'Especialidades Clínicas')
@section('titulo', 'Ortodoncia · '.$paciente->nombre_completo)
@section('subtitulo', 'Trazados cefalométricos, análisis de Steiner y diagnóstico esquelético')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha</a>
        <a href="{{ route('admin.cefalometrias.create', $paciente) }}" class="btn btn-primary">
            <i class="ti ti-scan me-1"></i>Nuevo trazado cefalométrico
        </a>
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'ortodoncia'])

@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-scan me-2"></i>Estudios Cefalométricos de Ortodoncia</h3>
        <span class="badge bg-indigo-lt">{{ $cefalometrias->total() }} trazados</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Análisis</th>
                    <th>Clase Esquelética (ANB)</th>
                    <th>Patrón Facial (GoGn-SN)</th>
                    <th>Ángulos Steiner</th>
                    <th>Doctor</th>
                    <th class="w-1 text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cefalometrias as $item)
                    @php $medidas = $item->medidas ?? []; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $item->fecha->format('d/m/Y') }}</td>
                        <td><span class="badge bg-purple-lt">{{ $item->tipo_analisis }}</span></td>
                        <td>
                            @if ($item->diagnostico_esqueletico === 'CLASE_I')
                                <span class="badge bg-success-lt fs-5 px-2">Clase I</span>
                            @elseif ($item->diagnostico_esqueletico === 'CLASE_II')
                                <span class="badge bg-warning-lt fs-5 px-2">Clase II</span>
                            @elseif ($item->diagnostico_esqueletico === 'CLASE_III')
                                <span class="badge bg-danger-lt fs-5 px-2">Clase III</span>
                            @else
                                <span class="badge bg-secondary-lt">{{ $item->diagnostico_esqueletico ?: 'Sin clasificar' }}</span>
                            @endif
                            <small class="d-block text-secondary mt-1">ANB: {{ $medidas['ANB'] ?? '—' }}°</small>
                        </td>
                        <td>
                            <span class="badge bg-azure-lt">{{ \App\Models\TrazadoCefalometrico::PATRONES[$item->patron_crecimiento] ?? ($item->patron_crecimiento ?: 'No determinado') }}</span>
                            <small class="d-block text-secondary mt-1">GoGn-SN: {{ $medidas['GoGn_SN'] ?? '—' }}°</small>
                        </td>
                        <td>
                            <div class="small font-monospace">
                                SNA: <strong>{{ $medidas['SNA'] ?? '—' }}°</strong> ·
                                SNB: <strong>{{ $medidas['SNB'] ?? '—' }}°</strong>
                            </div>
                        </td>
                        <td class="text-secondary small text-nowrap">
                            {{ $item->doctor?->nombre_profesional ?? '—' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="btn-list flex-nowrap justify-content-end">
                                <a href="{{ route('admin.cefalometrias.show', $item) }}" class="btn btn-sm btn-outline-info" title="Ver estudio">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="{{ route('admin.cefalometrias.pdf', $item) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                                <a href="{{ route('admin.cefalometrias.edit', $item) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="ti ti-edit"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.cefalometrias.destroy', $item) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este trazado cefalométrico?')">
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
                            <i class="ti ti-scan fs-1 d-block mb-2 text-muted"></i>
                            No hay trazados cefalométricos registrados para este paciente.<br>
                            <a href="{{ route('admin.cefalometrias.create', $paciente) }}" class="btn btn-sm btn-primary mt-2">
                                <i class="ti ti-plus me-1"></i>Realizar primer trazado cefalométrico
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($cefalometrias->hasPages())
        <div class="card-footer">{{ $cefalometrias->links() }}</div>
    @endif
</div>
@endsection
