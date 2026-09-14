@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', $documento->tipo_legible)
@section('subtitulo', $documento->numero_control)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.facturacion.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        <a href="{{ route('admin.facturacion.pdf', $documento) }}" target="_blank" class="btn btn-outline-danger">
            <i class="ti ti-file-type-pdf me-1"></i>PDF
        </a>
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-receipt-tax me-2"></i>Detalle del documento</h3>
                <span class="badge bg-{{ $documento->color_estado }} ms-auto">{{ $documento->estado }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($documento->contenido['lineas'] ?? [] as $linea)
                            <tr>
                                <td>{{ $linea['descripcion'] }}</td>
                                <td class="text-center">{{ $linea['cantidad'] }}</td>
                                <td class="text-end">{{ number_format($linea['precio_unitario'], 2) }}</td>
                                <td class="text-end">{{ number_format($linea['subtotal'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="3" class="text-end">Base gravada</th><th class="text-end">{{ number_format($documento->subtotal, 2) }}</th></tr>
                        <tr><th colspan="3" class="text-end">IVA ({{ number_format($documento->tasa_iva, 0) }}%)</th><th class="text-end">{{ number_format($documento->iva, 2) }}</th></tr>
                        <tr><th colspan="3" class="text-end">Total</th><th class="text-end">{{ number_format($documento->total, 2) }} {{ $ajustes->divisa }}</th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if ($documento->documentoReferencia)
            <div class="card mt-3 border-orange">
                <div class="card-body d-flex align-items-center gap-2">
                    <i class="ti ti-arrow-back-up text-orange fs-2"></i>
                    <div>
                        <div class="text-secondary small text-uppercase">Corrige a</div>
                        <a href="{{ route('admin.facturacion.show', $documento->documentoReferencia) }}" class="text-brand font-monospace">
                            {{ $documento->documentoReferencia->numero_control }}
                        </a>
                        <span class="text-secondary small ms-2">{{ $documento->documentoReferencia->tipo_legible }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if ($documento->notas->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-receipt-refund me-2"></i>Notas emitidas sobre este documento</h3>
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($documento->notas as $nota)
                        <a href="{{ route('admin.facturacion.show', $nota) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <span class="badge bg-orange-lt">{{ $nota->tipo_legible }}</span>
                            <span class="font-monospace small flex-fill">{{ $nota->numero_control }}</span>
                            <span class="text-secondary small">{{ $nota->fecha_emision->format('d/m/Y') }}</span>
                            <span class="fw-medium">{{ number_format($nota->total, 2) }}</span>
                            <span class="badge bg-{{ $nota->color_estado }}">{{ $nota->estado }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($documento->motivo_anulacion)
            <div class="card mt-3 border-danger">
                <div class="card-header"><h3 class="card-title text-danger">Motivo de anulación</h3></div>
                <div class="card-body">{{ $documento->motivo_anulacion }}</div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Identificación tributaria</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Número de control</div>
                        <div class="datagrid-content font-monospace" style="font-size: .75rem;">{{ $documento->numero_control }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Código de generación</div>
                        <div class="datagrid-content font-monospace" style="font-size: .7rem;">{{ $documento->codigo_generacion }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Sello de recepción</div>
                        <div class="datagrid-content font-monospace" style="font-size: .7rem; word-break: break-all;">
                            {{ $documento->sello_recepcion ?: '—' }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Serie y correlativo</div>
                        <div class="datagrid-content">{{ $documento->serie }} · {{ $documento->correlativo }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Emisión</div>
                        <div class="datagrid-content">{{ $documento->fecha_emision->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Receptor</div>
                        <div class="datagrid-content">
                            {{ $documento->receptor_nombre }}
                            @if ($documento->receptor_documento)
                                <div class="text-secondary small">{{ $documento->receptor_documento }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Recibo de origen</div>
                        <div class="datagrid-content">
                            @can('pagos.ver')
                                <a href="{{ route('admin.pagos.show', $documento->pago) }}" class="text-brand">
                                    {{ $documento->pago->codigo_recibo }}
                                </a>
                            @else
                                {{ $documento->pago->codigo_recibo }}
                            @endcan
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Emitido por</div>
                        <div class="datagrid-content">{{ $documento->emisor?->nombre ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @can('facturacion.anular')
            @if ($documento->estado !== 'ANULADO' && ! $documento->es_nota)
                <div class="card mt-3 border-danger">
                    <div class="card-header"><h3 class="card-title text-danger">Anular documento</h3></div>
                    <form method="POST" action="{{ route('admin.facturacion.anular', $documento) }}">
                        @csrf @method('PATCH')
                        <div class="card-body">
                            <p class="text-secondary small">
                                El documento se conserva con su correlativo para la trazabilidad fiscal,
                                pero deja de sumar en los totales facturados.
                            </p>
                            <x-campo nombre="motivo" etiqueta="Motivo de la anulación" requerido>
                                <textarea id="motivo" name="motivo" class="form-control" rows="2" required></textarea>
                            </x-campo>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-danger w-100"
                                    onclick="return confirm('¿Anular el documento {{ $documento->numero_control }}?')">
                                <i class="ti ti-ban me-1"></i>Anular documento
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </div>
</div>
@endsection
