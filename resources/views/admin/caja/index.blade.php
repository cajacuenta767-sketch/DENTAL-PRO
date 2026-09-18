@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', 'Cierre de Caja')
@section('subtitulo', 'Arqueo diario del efectivo por sucursal')

@push('head')
<style>
    .caja-diferencia-positiva { color: var(--tblr-success); }
    .caja-diferencia-negativa { color: var(--tblr-danger); }
    .caja-kpi-nota { font-size: .75rem; }
</style>
@endpush

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.egresos.index') }}" class="btn btn-outline-danger">
            <i class="ti ti-receipt-refund me-1"></i>Egresos de caja chica
        </a>
        @can('caja.cerrar')
            @if ($cierreHoy)
                <a href="{{ route('admin.caja.show', $cierreHoy) }}" class="btn btn-outline-success">
                    <i class="ti ti-lock me-1"></i>Ver cierre de hoy
                </a>
            @else
                <a href="{{ route('admin.caja.arqueo') }}" class="btn btn-primary">
                    <i class="ti ti-cash-register me-1"></i>Arquear y cerrar hoy
                </a>
            @endif
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Cobrado hoy" :valor="number_format($resumenHoy['total'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="success" :pie="$hoy->format('d/m/Y')" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Efectivo esperado en caja" :valor="number_format($efectivoEsperadoHoy, 2).' '.$ajustes->divisa"
               icono="ti ti-cash" color="azure" pie="Fondo inicial + efectivo del día" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Recibos de hoy" :valor="$resumenHoy['recibos']" icono="ti ti-receipt" color="indigo"
               :pie="$resumenHoy['anulados'] ? $resumenHoy['anulados'].' anulado(s)' : null" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Estado de la caja de hoy" :valor="$cierreHoy ? 'Cerrada' : 'Abierta'"
               :icono="$cierreHoy ? 'ti ti-lock' : 'ti ti-lock-open'" :color="$cierreHoy ? 'secondary' : 'warning'"
               :pie="$cierreHoy ? 'Cerrada a las '.$cierreHoy->cerrado_en?->format('H:i') : 'Pendiente de arqueo'" />
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
            </div>
            @if (! $sucursalActiva && $sucursales->count() > 1)
                <div class="col-md-3">
                    <label class="form-label">Sucursal</label>
                    <select name="sucursal_id" class="form-select">
                        <option value="">— Todas las sucursales —</option>
                        @foreach ($sucursales as $sucursal)
                            <option value="{{ $sucursal->id }}" @selected((int) request('sucursal_id') === $sucursal->id)>{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-auto d-flex gap-2 align-items-end">
                <button class="btn btn-primary"><i class="ti ti-filter me-1"></i>Filtrar</button>
                <a href="{{ route('admin.caja.index') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Sucursal / Turno</th>
                    <th>Cerrado por</th>
                    <th class="text-center">Recibos</th>
                    <th class="text-end">Cobrado</th>
                    <th class="text-end">Egresos</th>
                    <th class="text-end">Efectivo esperado</th>
                    <th class="text-end">Contado</th>
                    <th class="text-end">Diferencia</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cierres as $cierre)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $cierre->fecha->format('d/m/Y') }}</div>
                            <div class="text-secondary small">Cerrada {{ $cierre->cerrado_en?->format('H:i') ?? '—' }}</div>
                        </td>
                        <td>
                            <div>{{ $cierre->sucursal?->nombre ?? 'General' }}</div>
                            <span class="badge bg-purple-lt small">{{ $cierre->turno_legible ?? $cierre->turno }}</span>
                        </td>
                        <td class="text-secondary">{{ $cierre->usuario?->nombre ?? '—' }}</td>
                        <td class="text-center">{{ $cierre->recibos }}</td>
                        <td class="text-end text-success fw-medium">+{{ number_format($cierre->total_cobrado, 2) }}</td>
                        <td class="text-end text-danger fw-medium">-{{ number_format((float)$cierre->total_egresos, 2) }}</td>
                        <td class="text-end">{{ number_format($cierre->efectivo_esperado, 2) }}</td>
                        <td class="text-end">{{ number_format($cierre->efectivo_contado, 2) }}</td>
                        <td class="text-end fw-medium {{ $cierre->diferencia < 0 ? 'caja-diferencia-negativa' : ($cierre->diferencia > 0 ? 'caja-diferencia-positiva' : 'text-secondary') }}">
                            {{ $cierre->diferencia > 0 ? '+' : '' }}{{ number_format($cierre->diferencia, 2) }}
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.caja.show', $cierre) }}" class="btn btn-sm btn-outline-secondary" title="Ver cierre">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="{{ route('admin.caja.pdf', $cierre) }}" target="_blank" class="btn btn-sm btn-outline-danger" title="PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-5">Todavía no hay cierres de caja registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($cierres->hasPages())
        <div class="card-footer">{{ $cierres->links() }}</div>
    @endif
</div>
@endsection
