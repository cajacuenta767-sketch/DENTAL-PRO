@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', 'Cierre de caja · '.$cierre->fecha->format('d/m/Y'))
@section('subtitulo', ($cierre->sucursal?->nombre ?? 'Caja general').' · cerrada el '.($cierre->cerrado_en?->format('d/m/Y H:i') ?? '—'))

@push('head')
<style>
    .cierre-cifra { font-size: 1.5rem; font-weight: 700; }
    .cierre-cifra.positiva { color: var(--tblr-success); }
    .cierre-cifra.negativa { color: var(--tblr-danger); }
</style>
@endpush

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.caja.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        <a href="{{ route('admin.caja.pdf', $cierre) }}" target="_blank" class="btn btn-outline-danger">
            <i class="ti ti-file-type-pdf me-1"></i>PDF
        </a>
    </div>
@endsection

@section('contenido')
@php
    $totales = $cierre->totales ?? [];
    $claseDiferencia = $cierre->diferencia < 0 ? 'negativa' : ($cierre->diferencia > 0 ? 'positiva' : '');
@endphp
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Total cobrado" :valor="number_format($cierre->total_cobrado, 2).' '.$ajustes->divisa" icono="ti ti-coin" color="success"
               :pie="$cierre->recibos.' recibo(s)'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Efectivo esperado" :valor="number_format($cierre->efectivo_esperado, 2).' '.$ajustes->divisa" icono="ti ti-cash" color="azure"
               :pie="'Fondo inicial '.number_format($cierre->fondo_inicial, 2)" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Efectivo contado" :valor="number_format($cierre->efectivo_contado, 2).' '.$ajustes->divisa" icono="ti ti-cash-banknote" color="indigo" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Diferencia" :valor="($cierre->diferencia > 0 ? '+' : '').number_format($cierre->diferencia, 2).' '.$ajustes->divisa"
               :icono="$cierre->diferencia == 0 ? 'ti ti-check' : 'ti ti-alert-triangle'"
               :color="$cierre->diferencia == 0 ? 'success' : ($cierre->diferencia < 0 ? 'danger' : 'warning')"
               :pie="$cierre->diferencia == 0 ? 'Caja cuadrada' : ($cierre->diferencia < 0 ? 'Faltante' : 'Sobrante')" />
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-credit-card me-2"></i>Cobrado por método</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Método</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($totales['por_metodo'] ?? [] as $metodo => $monto)
                            <tr>
                                <td><span class="badge bg-azure-lt">{{ $metodo }}</span></td>
                                <td class="text-end {{ $monto > 0 ? 'fw-medium' : 'text-secondary' }}">{{ number_format($monto, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-secondary py-4">Sin desglose guardado.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><th>Total</th><th class="text-end">{{ number_format($cierre->total_cobrado, 2) }} {{ $ajustes->divisa }}</th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user-dollar me-2"></i>Cobrado por cajero</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Cajero</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($totales['por_cajero'] ?? [] as $cajero => $monto)
                            <tr><td>{{ $cajero }}</td><td class="text-end fw-medium">{{ number_format($monto, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-secondary py-4">Sin cobros en el día.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-info-circle me-2"></i>Datos del cierre</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha de caja</div>
                        <div class="datagrid-content">{{ $cierre->fecha->format('d/m/Y') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Sucursal</div>
                        <div class="datagrid-content">{{ $cierre->sucursal?->nombre ?? 'General' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Cerrada por</div>
                        <div class="datagrid-content">{{ $cierre->usuario?->nombre ?? '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Cerrada el</div>
                        <div class="datagrid-content">{{ $cierre->cerrado_en?->format('d/m/Y H:i') ?? '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Estado</div>
                        <div class="datagrid-content"><span class="badge bg-{{ $cierre->estado === 'CERRADO' ? 'success' : 'warning' }}">{{ $cierre->estado }}</span></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Recibos anulados</div>
                        <div class="datagrid-content">{{ $totales['anulados'] ?? 0 }}</div>
                    </div>
                </div>
                @if ($cierre->observaciones)
                    <div class="mt-3">
                        <div class="text-secondary small text-uppercase">Observaciones</div>
                        <div>{!! nl2br(e($cierre->observaciones)) !!}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
