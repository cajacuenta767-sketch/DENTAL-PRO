<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $titulo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf.reportes._cabecera')

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Total recaudado</div><div class="valor">{{ number_format($finTotal, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Saldos por cobrar</div><div class="valor">{{ number_format($finSaldos, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Efectivo</div><div class="valor">{{ number_format($finEfectivo, 2) }}</div></td>
        <td><div class="etiqueta">Recibos emitidos</div><div class="valor">{{ $finRecibos }}</div></td>
    </tr>
</table>

<div class="titulo-doc" style="font-size:11px;">Recaudación por método de pago</div>
<table class="bloque">
    <thead><tr><th>Método</th><th class="cen">Recibos</th><th class="der">Total</th></tr></thead>
    <tbody>
        @forelse ($finPorMetodo as $fila)
            <tr>
                <td>{{ $fila->metodo_pago }}</td>
                <td class="cen">{{ $fila->recibos }}</td>
                <td class="der">{{ number_format($fila->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="cen apagado">Sin movimientos en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Detalle de recibos</div>
<table>
    <thead>
        <tr><th>Recibo</th><th>Fecha</th><th>Paciente</th><th>Doctor</th>
            <th class="cen">Método</th><th class="cen">Estado</th>
            <th class="der">Total</th><th class="der">Pagado</th><th class="der">Saldo</th></tr>
    </thead>
    <tbody>
        @forelse ($finListado as $pago)
            <tr>
                <td>{{ $pago->codigo_recibo }}</td>
                <td>{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                <td>{{ $pago->paciente->nombre_completo }}</td>
                <td>{{ $pago->doctor?->nombre_profesional ?? '—' }}</td>
                <td class="cen">{{ $pago->metodo_pago }}</td>
                <td class="cen">{{ $pago->estado }}</td>
                <td class="der">{{ number_format($pago->monto_total, 2) }}</td>
                <td class="der">{{ number_format($pago->monto_pagado, 2) }}</td>
                <td class="der">{{ number_format($pago->monto_saldo, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="cen apagado">Sin recibos en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">{{ $clinica->nombre }} · Reporte generado por OdontoSuite</div>
</body>
</html>
