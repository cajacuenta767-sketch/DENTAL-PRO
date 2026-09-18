@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Mis presupuestos')

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-file-invoice me-2 text-primary"></i>Presupuestos de tratamiento</h3>
        <div class="card-actions text-secondary small">
            {{ $presupuestos->total() }} {{ Str::plural('presupuesto', $presupuestos->total()) }}
        </div>
    </div>

    @if ($presupuestos->isEmpty())
        <div class="card-body">
            <x-vacio icono="ti ti-file-invoice" titulo="No tienes presupuestos"
                     texto="Cuando tu doctor te presente un plan de tratamiento, lo verás aquí." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Fecha</th>
                        <th>Doctor</th>
                        <th class="text-center">Ítems</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                        <th style="min-width: 10rem;">Avance</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($presupuestos as $p)
                        <tr>
                            <td class="text-nowrap"><code>{{ $p->codigo }}</code></td>
                            <td class="text-nowrap">{{ $p->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $p->doctor?->nombre_profesional ?? '—' }}</td>
                            <td class="text-center">{{ $p->detalles_count }}</td>
                            <td class="text-end text-nowrap fw-bold">
                                {{ $ajustes->simbolo_divisa ?? '' }} {{ number_format((float) $p->total, 2) }}
                            </td>
                            <td><span class="badge bg-{{ $p->color_estado }}">{{ $p->estado_legible }}</span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress progress-sm flex-fill">
                                        <div class="progress-bar bg-{{ $p->color_estado }}" style="width: {{ $p->avance }}%"
                                             role="progressbar" aria-valuenow="{{ $p->avance }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <span class="small text-secondary">{{ $p->avance }}%</span>
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('portal.presupuestos.pdf', $p) }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-primary text-nowrap">
                                    <i class="ti ti-file-type-pdf me-1"></i>Ver PDF
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($presupuestos->hasPages())
            <div class="card-footer d-flex align-items-center">
                {{ $presupuestos->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
