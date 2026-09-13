@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', 'Facturación Electrónica')

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Documentos emitidos" :valor="$totales['emitidos']" icono="ti ti-receipt-tax" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Total facturado" :valor="number_format($totales['facturado'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="IVA generado" :valor="number_format($totales['iva'], 2).' '.$ajustes->divisa"
               icono="ti ti-percentage" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Anulados" :valor="$totales['anulados']" icono="ti ti-ban"
               :color="$totales['anulados'] > 0 ? 'danger' : 'secondary'" />
    </div>
</div>

@if ($pendientes->isNotEmpty())
    @can('facturacion.emitir')
        <div class="card mb-3 border-azure">
            <div class="card-header">
                <h3 class="card-title text-azure"><i class="ti ti-file-plus me-2"></i>Recibos sin documento fiscal</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Recibo</th><th>Paciente</th><th>Fecha</th><th class="text-end">Total</th><th class="w-1"></th></tr></thead>
                    <tbody>
                        @foreach ($pendientes as $pago)
                            <tr>
                                <td class="font-monospace small">{{ $pago->codigo_recibo }}</td>
                                <td>{{ $pago->paciente->nombre_completo }}</td>
                                <td class="text-secondary">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                                <td class="text-end fw-medium">{{ number_format($pago->monto_total, 2) }}</td>
                                <td>
                                    <a href="{{ route('admin.facturacion.create', ['pago_id' => $pago->id]) }}"
                                       class="btn btn-sm btn-primary">
                                        <i class="ti ti-file-invoice me-1"></i>Emitir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
@endif

<div class="card">
    <div class="card-header"><h3 class="card-title">Documentos tributarios electrónicos</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="N° de control, receptor o documento">
            </div>
            <div class="col-md-2">
                <select name="tipo" class="form-select">
                    <option value="">— Todos los tipos —</option>
                    @foreach (\App\Models\DocumentoFiscal::TIPOS as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('tipo') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select">
                    <option value="">— Todos —</option>
                    <option value="EMITIDO" @selected(request('estado') === 'EMITIDO')>Emitidos</option>
                    <option value="ANULADO" @selected(request('estado') === 'ANULADO')>Anulados</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary"><i class="ti ti-filter"></i></button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>N° de control</th>
                    <th>Tipo</th>
                    <th>Receptor</th>
                    <th>Emisión</th>
                    <th class="text-end">Gravado</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documentos as $documento)
                    <tr class="{{ $documento->estado === 'ANULADO' ? 'opacity-75' : '' }}">
                        <td>
                            <div class="font-monospace" style="font-size: .72rem;">{{ $documento->numero_control }}</div>
                            <div class="text-secondary small">Recibo {{ $documento->pago->codigo_recibo }}</div>
                        </td>
                        <td><span class="badge bg-azure-lt">{{ $documento->tipo }}</span></td>
                        <td>
                            <div class="fw-medium">{{ $documento->receptor_nombre }}</div>
                            @if ($documento->receptor_documento)
                                <div class="text-secondary small">{{ $documento->receptor_documento }}</div>
                            @endif
                        </td>
                        <td class="text-secondary">{{ $documento->fecha_emision->format('d/m/Y H:i') }}</td>
                        <td class="text-end">{{ number_format($documento->subtotal, 2) }}</td>
                        <td class="text-end text-secondary">{{ number_format($documento->iva, 2) }}</td>
                        <td class="text-end fw-medium">{{ number_format($documento->total, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $documento->color_estado }}">{{ $documento->estado }}</span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.facturacion.show', $documento) }}" class="btn btn-sm btn-outline-secondary" title="Ver">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="{{ route('admin.facturacion.pdf', $documento) }}" target="_blank"
                                   class="btn btn-sm btn-outline-danger" title="PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <x-vacio icono="ti ti-receipt-off" titulo="Sin documentos emitidos"
                                     texto="Emite el primer documento fiscal desde un recibo de caja." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($documentos->hasPages())
        <div class="card-footer">{{ $documentos->links() }}</div>
    @endif
</div>
@endsection
