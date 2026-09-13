<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $pago->codigo_recibo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">COMPROBANTE DE PAGO · {{ $pago->codigo_recibo }}</div>
<div class="subtitulo-doc">
    Estado: {{ $pago->estado }} · Método: {{ $pago->metodo_pago }} ·
    Fecha: {{ $pago->fecha_pago->format('d/m/Y H:i') }}
</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="etiqueta-campo">Paciente</div>
                <strong>{{ $pago->paciente->nombre_completo }}</strong><br>
                {{ $pago->paciente->tipo_documento }} {{ $pago->paciente->numero_documento }}
                @if ($pago->paciente->telefono)<br>Tel. {{ $pago->paciente->telefono }}@endif
            </td>
            <td style="width:50%;">
                <div class="etiqueta-campo">Atendido por</div>
                <strong>{{ $pago->doctor?->nombre_profesional ?? 'No asignado' }}</strong><br>
                Cajero: {{ $pago->cajero?->nombre ?? '—' }}
                @if ($pago->cita)<br>Cita: {{ $pago->cita->token }}@endif
            </td>
        </tr>
    </table>
</div>

<table class="bloque">
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="cen" style="width: 12%;">Cantidad</th>
            <th class="der" style="width: 18%;">Precio unit.</th>
            <th class="der" style="width: 18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($pago->detalles as $detalle)
            <tr>
                <td>{{ $detalle->descripcion }}</td>
                <td class="cen">{{ $detalle->cantidad }}</td>
                <td class="der">{{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="der">{{ number_format($detalle->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="3" class="der">TOTAL</th>
            <th class="der">{{ number_format($pago->monto_total, 2) }} {{ $clinica->divisa }}</th>
        </tr>
        <tr>
            <th colspan="3" class="der">PAGADO</th>
            <th class="der">{{ number_format($pago->monto_pagado, 2) }} {{ $clinica->divisa }}</th>
        </tr>
        <tr>
            <th colspan="3" class="der">SALDO PENDIENTE</th>
            <th class="der">{{ number_format($pago->monto_saldo, 2) }} {{ $clinica->divisa }}</th>
        </tr>
    </tfoot>
</table>

@if ($pago->notas)
    <div class="bloque caja">
        <div class="etiqueta-campo">Notas</div>
        {!! nl2br(e($pago->notas)) !!}
    </div>
@endif

@if ($clinica->terminos_recibo)
    <div class="apagado" style="font-size: 8px;">{{ $clinica->terminos_recibo }}</div>
@endif

<div class="pie">{{ $clinica->nombre }} · Comprobante generado por OdontoSuite</div>
</body>
</html>
