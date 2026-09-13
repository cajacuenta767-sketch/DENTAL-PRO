<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $documento->numero_control }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">{{ mb_strtoupper($documento->tipo_legible) }}</div>
<div class="subtitulo-doc">
    Documento Tributario Electrónico
    @if ($documento->estado === 'ANULADO') · <strong>ANULADO</strong> @endif
</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:55%;">
                <div class="etiqueta-campo">Identificación del documento</div>
                <strong>N° de control:</strong> {{ $documento->numero_control }}<br>
                <strong>Código de generación:</strong> {{ $documento->codigo_generacion }}<br>
                <strong>Serie / correlativo:</strong> {{ $documento->serie }} · {{ $documento->correlativo }}<br>
                <strong>Fecha de emisión:</strong> {{ $documento->fecha_emision->format('d/m/Y H:i') }}
            </td>
            <td style="width:45%;">
                <div class="etiqueta-campo">Receptor</div>
                <strong>{{ $documento->receptor_nombre }}</strong><br>
                @if ($documento->receptor_documento)Documento: {{ $documento->receptor_documento }}<br>@endif
                @if ($documento->receptor_direccion){{ $documento->receptor_direccion }}<br>@endif
                @if ($documento->receptor_email){{ $documento->receptor_email }}@endif
            </td>
        </tr>
    </table>
</div>

<table class="bloque">
    <thead>
        <tr>
            <th>Descripción</th>
            <th class="cen" style="width:10%;">Cantidad</th>
            <th class="der" style="width:18%;">Precio unit.</th>
            <th class="der" style="width:18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($documento->contenido['lineas'] ?? [] as $linea)
            <tr>
                <td>{{ $linea['descripcion'] }}</td>
                <td class="cen">{{ $linea['cantidad'] }}</td>
                <td class="der">{{ number_format($linea['precio_unitario'], 2) }}</td>
                <td class="der">{{ number_format($linea['subtotal'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="der">BASE GRAVADA</th>
            <th class="der">{{ number_format($documento->subtotal, 2) }}</th>
        </tr>
        <tr>
            <th colspan="3" class="der">IVA ({{ number_format($documento->tasa_iva, 0) }}%)</th>
            <th class="der">{{ number_format($documento->iva, 2) }}</th>
        </tr>
        <tr>
            <th colspan="3" class="der">TOTAL A PAGAR</th>
            <th class="der">{{ number_format($documento->total, 2) }} {{ $clinica->divisa }}</th>
        </tr>
    </tfoot>
</table>

<div class="bloque caja">
    <div class="etiqueta-campo">Sello de recepción</div>
    <span style="font-size: 8px; word-break: break-all;">{{ $documento->sello_recepcion ?: 'Pendiente' }}</span>
</div>

@if ($documento->motivo_anulacion)
    <div class="bloque caja">
        <div class="etiqueta-campo">Motivo de anulación</div>
        {{ $documento->motivo_anulacion }}
    </div>
@endif

<div class="apagado" style="font-size: 8px;">
    Representación gráfica del documento tributario electrónico. Emitido por {{ $clinica->nombre }}
    @if ($clinica->nit) · NIT {{ $clinica->nit }} @endif
    · Recibo de origen {{ $documento->pago->codigo_recibo }}.
</div>

<div class="pie">{{ $clinica->nombre }} · {{ $documento->numero_control }}</div>
</body>
</html>
