@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', 'Caja y Pagos')
@section('subtitulo', 'Control de cobros y recibos')

@section('acciones')
    @can('pagos.crear')
        <a href="{{ route('admin.pagos.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Registrar Nuevo Pago
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Total Recaudado" :valor="number_format($totales['recaudado'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Ingresos en Efectivo" :valor="number_format($totales['efectivo'], 2).' '.$ajustes->divisa"
               icono="ti ti-cash" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="QR / Tarjeta / Banco" :valor="number_format($totales['digital'], 2).' '.$ajustes->divisa"
               icono="ti ti-credit-card" color="indigo" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Saldos por Cobrar" :valor="number_format($totales['saldos'], 2).' '.$ajustes->divisa"
               icono="ti ti-alert-circle" color="warning" />
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Buscar por N° recibo, paciente, documento…">
            </div>
            @if (($sucursales ?? collect())->count() > 1 && ! $sucursalActiva)
                <div class="col-md-2">
                    <select name="sucursal_id" class="form-select">
                        <option value="">— Todas las sedes —</option>
                        @foreach ($sucursales as $sede)
                            <option value="{{ $sede->id }}" @selected(request('sucursal_id') == $sede->id)>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <select name="metodo" class="form-select">
                    <option value="">— Todos los métodos —</option>
                    @foreach (\App\Models\Pago::METODOS as $metodo)
                        <option value="{{ $metodo }}" @selected(request('metodo') === $metodo)>{{ $metodo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select">
                    <option value="">— Todos los estados —</option>
                    @foreach (\App\Models\Pago::ESTADOS as $estado)
                        <option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="fecha" value="{{ request('fecha') }}" class="form-control">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-filter"></i></button>
                <a href="{{ route('admin.pagos.index') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Recibo</th>
                    <th>Paciente</th>
                    <th>Doctor / Responsable</th>
                    @if (($sucursales ?? collect())->count() > 1)
                        <th>Sede</th>
                    @endif
                    <th>Fecha</th>
                    <th class="text-center">Método</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Saldo</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pagos as $pago)
                    <tr class="{{ $pago->estado === 'ANULADO' ? 'opacity-75' : '' }}">
                        <td class="font-monospace small">{{ $pago->codigo_recibo }}</td>
                        <td>
                            <div class="fw-medium">{{ $pago->paciente->nombre_completo }}</div>
                            <div class="text-secondary small">Doc. {{ $pago->paciente->numero_documento }}</div>
                        </td>
                        <td>
                            <div>{{ $pago->doctor?->nombre_profesional ?? '—' }}</div>
                            <div class="text-secondary small">Cajero: {{ $pago->cajero?->nombre ?? '—' }}</div>
                        </td>
                        @if (($sucursales ?? collect())->count() > 1)
                            <td>
                                @if ($pago->sucursal)
                                    <span class="badge" style="background-color: {{ $pago->sucursal->color }}20; color: {{ $pago->sucursal->color }}">{{ $pago->sucursal->nombre }}</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-secondary">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                        <td class="text-center"><span class="badge bg-azure-lt">{{ $pago->metodo_pago }}</span></td>
                        <td class="text-end">{{ number_format($pago->monto_total, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($pago->monto_pagado, 2) }}</td>
                        <td class="text-end {{ $pago->monto_saldo > 0 ? 'text-danger fw-medium' : 'text-secondary' }}">
                            {{ $pago->monto_saldo > 0 ? number_format($pago->monto_saldo, 2) : '—' }}
                        </td>
                        <td class="text-center"><span class="badge bg-{{ $pago->color_estado }}">{{ $pago->estado }}</span></td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.pagos.show', $pago) }}" class="btn btn-sm btn-outline-secondary" title="Ver recibo">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="{{ route('admin.pagos.recibo', $pago) }}" target="_blank"
                                   class="btn btn-sm btn-outline-danger" title="Comprobante PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                                @can('pagos.editar')
                                    @if ($pago->estado !== 'ANULADO')
                                        <a href="{{ route('admin.pagos.edit', $pago) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($sucursales ?? collect())->count() > 1 ? 11 : 10 }}">
                            <x-vacio icono="ti ti-receipt-off" titulo="Sin recibos"
                                     texto="Ningún cobro coincide con los filtros aplicados." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($pagos->hasPages())
        <div class="card-footer">{{ $pagos->links() }}</div>
    @endif
</div>
@endsection
