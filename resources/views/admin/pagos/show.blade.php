@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', 'Recibo '.$pago->codigo_recibo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pagos.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        <a href="{{ route('admin.pagos.recibo', $pago) }}" target="_blank" class="btn btn-outline-danger">
            <i class="ti ti-file-type-pdf me-1"></i>PDF
        </a>
        <form method="POST" action="{{ route('admin.pagos.enviar', $pago) }}">
            @csrf
            <button class="btn btn-outline-azure"><i class="ti ti-mail me-1"></i>Enviar al paciente</button>
        </form>
        @can('pagos.editar')
            @if ($pago->estado !== 'ANULADO')
                <a href="{{ route('admin.pagos.edit', $pago) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Editar</a>
            @endif
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-receipt me-2"></i>Detalle del recibo</h3>
                <span class="badge bg-{{ $pago->color_estado }} ms-auto">{{ $pago->estado }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Precio unit.</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pago->detalles as $detalle)
                            <tr>
                                <td>
                                    <div>{{ $detalle->descripcion }}</div>
                                    @if ($detalle->tratamiento)
                                        <div class="text-secondary small">Catálogo: {{ $detalle->tratamiento->nombre }}</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detalle->cantidad }}</td>
                                <td class="text-end">{{ number_format($detalle->precio_unitario, 2) }}</td>
                                <td class="text-end fw-medium">{{ number_format($detalle->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-end">Total</th>
                            <th class="text-end">{{ number_format($pago->monto_total, 2) }} {{ $ajustes->divisa }}</th>
                        </tr>
                        <tr>
                            <th colspan="3" class="text-end text-success">Pagado</th>
                            <th class="text-end text-success">{{ number_format($pago->monto_pagado, 2) }} {{ $ajustes->divisa }}</th>
                        </tr>
                        <tr>
                            <th colspan="3" class="text-end text-danger">Saldo pendiente</th>
                            <th class="text-end text-danger">{{ number_format($pago->monto_saldo, 2) }} {{ $ajustes->divisa }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($pago->notas)
                <div class="card-body border-top">
                    <div class="text-secondary small text-uppercase">Notas</div>
                    <div style="white-space: pre-line;">{{ $pago->notas }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Información</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Paciente</div>
                        <div class="datagrid-content">
                            <a href="{{ route('admin.pacientes.show', $pago->paciente) }}" class="text-brand">
                                {{ $pago->paciente->nombre_completo }}
                            </a>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Documento</div>
                        <div class="datagrid-content">{{ $pago->paciente->numero_documento }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Doctor</div>
                        <div class="datagrid-content">{{ $pago->doctor?->nombre_profesional ?? '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Cita asociada</div>
                        <div class="datagrid-content">
                            @if ($pago->cita)
                                <a href="{{ route('admin.citas.show', $pago->cita) }}" class="text-brand">{{ $pago->cita->token }}</a>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Método de pago</div>
                        <div class="datagrid-content"><span class="badge bg-azure-lt">{{ $pago->metodo_pago }}</span></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha</div>
                        <div class="datagrid-content">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Cajero</div>
                        <div class="datagrid-content">{{ $pago->cajero?->nombre ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @can('pagos.anular')
            @if ($pago->estado !== 'ANULADO')
                <div class="card mt-3 border-danger">
                    <div class="card-header"><h3 class="card-title text-danger">Anular recibo</h3></div>
                    <form method="POST" action="{{ route('admin.pagos.anular', $pago) }}">
                        @csrf @method('PATCH')
                        <div class="card-body">
                            <p class="text-secondary small">
                                El recibo se conserva para auditoría pero deja de sumar en los totales de caja y reportes.
                            </p>
                            <x-campo nombre="motivo" etiqueta="Motivo de la anulación" requerido>
                                <textarea id="motivo" name="motivo" class="form-control" rows="2" required></textarea>
                            </x-campo>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-danger w-100"
                                    onclick="return confirm('¿Anular el recibo {{ $pago->codigo_recibo }}?')">
                                <i class="ti ti-ban me-1"></i>Anular recibo
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </div>
</div>
@endsection
