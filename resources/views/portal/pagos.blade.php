@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Mis pagos')

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
        <x-kpi titulo="Saldo pendiente"
               :valor="($ajustes->simbolo_divisa ?? '') . ' ' . number_format((float) $saldo, 2)"
               icono="ti ti-cash"
               :color="$saldo > 0 ? 'warning' : 'success'"
               :pie="$saldo > 0 ? ($ajustes->pagos_online_activos ? 'Puedes pagar en línea desde cada recibo o en recepción.' : 'Acércate a recepción para regularizar tu saldo.') : 'No tienes saldos pendientes.'" />
    </div>
    <div class="col-sm-6 col-lg-4">
        <x-kpi titulo="Recibos emitidos" :valor="$pagos->total()" icono="ti ti-receipt" color="primary" />
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-receipt me-2 text-primary"></i>Recibos de pago</h3>
    </div>

    @if ($pagos->isEmpty())
        <div class="card-body">
            <x-vacio icono="ti ti-receipt-off" titulo="No tienes pagos registrados"
                     texto="Cada recibo que emita la clínica aparecerá aquí para que puedas descargarlo." />
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Recibo</th>
                        <th>Fecha</th>
                        <th>Doctor</th>
                        <th>Método</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagos as $pago)
                        <tr>
                            <td class="text-nowrap"><code>{{ $pago->codigo_recibo }}</code></td>
                            <td class="text-nowrap">{{ $pago->fecha_pago?->format('d/m/Y H:i') }}</td>
                            <td>{{ $pago->doctor?->nombre_profesional ?? '—' }}</td>
                            <td>{{ ucfirst(mb_strtolower((string) $pago->metodo_pago)) }}</td>
                            <td class="text-end text-nowrap">{{ $ajustes->simbolo_divisa ?? '' }} {{ number_format((float) $pago->monto_total, 2) }}</td>
                            <td class="text-end text-nowrap text-success">{{ $ajustes->simbolo_divisa ?? '' }} {{ number_format((float) $pago->monto_pagado, 2) }}</td>
                            <td class="text-end text-nowrap {{ $pago->monto_saldo > 0 ? 'text-warning fw-bold' : 'text-secondary' }}">
                                {{ $ajustes->simbolo_divisa ?? '' }} {{ number_format((float) $pago->monto_saldo, 2) }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $pago->color_estado }}">{{ ucfirst(mb_strtolower((string) $pago->estado)) }}</span>
                            </td>
                            <td>
                                <div class="btn-list flex-nowrap">
                                    @if ($ajustes->pagos_online_activos && $pago->monto_saldo > 0)
                                        <form method="POST" action="{{ route('portal.pagos.pagar', $pago) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
                                                <i class="ti ti-credit-card me-1"></i>Pagar en línea
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('portal.pagos.recibo', $pago) }}" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-primary text-nowrap">
                                        <i class="ti ti-file-type-pdf me-1"></i>Recibo
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($pagos->hasPages())
            <div class="card-footer d-flex align-items-center">
                {{ $pagos->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
