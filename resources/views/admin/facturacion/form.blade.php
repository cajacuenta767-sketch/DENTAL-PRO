@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', 'Emitir Documento Fiscal')
@section('subtitulo', 'Recibo '.$pago->codigo_recibo)

@section('contenido')
<form method="POST" action="{{ route('admin.facturacion.store') }}">
    @csrf
    <input type="hidden" name="pago_id" value="{{ $pago->id }}">

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-user me-2"></i>Datos del receptor</h3></div>
                <div class="card-body">
                    <x-campo nombre="tipo" etiqueta="Tipo de documento" requerido
                             ayuda="Factura para consumidor final; crédito fiscal para contribuyentes.">
                        <select id="tipo" name="tipo" class="form-select" required>
                            @foreach (\App\Models\DocumentoFiscal::TIPOS as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('tipo', 'FACTURA') === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="receptor_nombre" etiqueta="Nombre o razón social" requerido>
                        <input type="text" id="receptor_nombre" name="receptor_nombre" class="form-control"
                               value="{{ old('receptor_nombre', $pago->paciente->nombre_completo) }}" required>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="receptor_documento" etiqueta="NIT / documento">
                                <input type="text" id="receptor_documento" name="receptor_documento" class="form-control"
                                       value="{{ old('receptor_documento', $pago->paciente->numero_documento) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="receptor_email" etiqueta="Correo para envío">
                                <input type="email" id="receptor_email" name="receptor_email" class="form-control"
                                       value="{{ old('receptor_email', $pago->paciente->email) }}">
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="receptor_direccion" etiqueta="Dirección">
                        <input type="text" id="receptor_direccion" name="receptor_direccion" class="form-control"
                               value="{{ old('receptor_direccion', $pago->paciente->direccion) }}">
                    </x-campo>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-list me-2"></i>Detalle a facturar</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead><tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                            @foreach ($pago->detalles as $detalle)
                                <tr>
                                    <td>{{ $detalle->descripcion }}</td>
                                    <td class="text-center">{{ $detalle->cantidad }}</td>
                                    <td class="text-end">{{ number_format($detalle->precio_unitario, 2) }}</td>
                                    <td class="text-end">{{ number_format($detalle->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resumen tributario</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Base gravada</span>
                        <span class="fw-medium">{{ number_format($desglose['subtotal'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">IVA ({{ number_format($ajustes->facturacion_tasa_iva, 0) }}%)</span>
                        <span class="fw-medium">{{ number_format($desglose['iva'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2">
                        <span class="fw-medium">Total del documento</span>
                        <span class="h2 mb-0 text-brand">{{ number_format($pago->monto_total, 2) }}</span>
                    </div>
                    <div class="text-secondary small text-end">{{ $ajustes->divisa }}</div>

                    <div class="alert alert-info mt-3 mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        El total del recibo ya incluye el IVA; el sistema desglosa la base imponible automáticamente.
                        Serie <strong>{{ $ajustes->facturacion_serie }}</strong>.
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.facturacion.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-file-invoice me-1"></i>Emitir documento
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
