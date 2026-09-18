<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Cierre de caja {{ $cierre->fecha->format('d/m/Y') }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')
@php $totales = $cierre->totales ?? []; @endphp

<div class="titulo-doc">CIERRE DE CAJA · {{ $cierre->fecha->format('d/m/Y') }}</div>
<div class="subtitulo-doc">
    Sucursal: {{ $cierre->sucursal?->nombre ?? 'General' }} ·
    Turno: {{ \App\Models\CierreCaja::TURNOS[$cierre->turno] ?? $cierre->turno }} ·
    Cerrada por: {{ $cierre->usuario?->nombre ?? '—' }} ·
    Cerrada el: {{ $cierre->cerrado_en?->format('d/m/Y H:i') ?? '—' }} · Estado: {{ $cierre->estado }}
</div>

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Total cobrado</div><div class="valor">{{ number_format($cierre->total_cobrado, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Total egresos</div><div class="valor" style="color:#d63939;">-{{ number_format($cierre->total_egresos ?? 0, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Efectivo esperado</div><div class="valor">{{ number_format($cierre->efectivo_esperado, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Efectivo contado</div><div class="valor">{{ number_format($cierre->efectivo_contado, 2) }} {{ $clinica->divisa }}</div></td>
        <td>
            <div class="etiqueta">Diferencia</div>
            <div class="valor" style="color: {{ $cierre->diferencia < 0 ? '#d63939' : ($cierre->diferencia > 0 ? '#f59f00' : '#2fb344') }};">
                {{ $cierre->diferencia > 0 ? '+' : '' }}{{ number_format($cierre->diferencia, 2) }} {{ $clinica->divisa }}
            </div>
        </td>
    </tr>
</table>

<table class="bloque">
    <tr>
        <td style="width:50%; vertical-align: top; padding-left: 0;">
            <div class="titulo-doc" style="font-size:11px;">Cobrado por método de pago</div>
            <table>
                <thead><tr><th>Método</th><th class="der">Total</th></tr></thead>
                <tbody>
                    @foreach ($totales['por_metodo'] ?? [] as $metodo => $monto)
                        <tr><td>{{ $metodo }}</td><td class="der">{{ number_format($monto, 2) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot><tr><th>Total</th><th class="der">{{ number_format($cierre->total_cobrado, 2) }}</th></tr></tfoot>
            </table>
        </td>
        <td style="width:50%; vertical-align: top; padding-right: 0;">
            <div class="titulo-doc" style="font-size:11px;">Cobrado por cajero</div>
            <table>
                <thead><tr><th>Cajero</th><th class="der">Total</th></tr></thead>
                <tbody>
                    @forelse ($totales['por_cajero'] ?? [] as $cajero => $monto)
                        <tr><td>{{ $cajero }}</td><td class="der">{{ number_format($monto, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="cen apagado">Sin cobros en el día.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

@if ($cierre->egresos && $cierre->egresos->isNotEmpty())
<div class="bloque">
    <div class="titulo-doc" style="font-size:11px;">Egresos de caja chica vinculados</div>
    <table>
        <thead>
            <tr>
                <th>Hora</th>
                <th>Concepto</th>
                <th>Categoría</th>
                <th>Método</th>
                <th class="der">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cierre->egresos as $egreso)
                <tr>
                    <td>{{ $egreso->fecha->format('H:i') }}</td>
                    <td>{{ $egreso->concepto }}</td>
                    <td>{{ \App\Models\EgresoCaja::CATEGORIAS[$egreso->categoria] ?? $egreso->categoria }}</td>
                    <td>{{ $egreso->metodo_pago }}</td>
                    <td class="der">-{{ number_format($egreso->monto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="der">Total egresos de caja</th>
                <th class="der">-{{ number_format($cierre->total_egresos, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
@endif

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:20%;"><div class="etiqueta-campo">Fondo inicial</div>{{ number_format($cierre->fondo_inicial, 2) }}</td>
            <td style="width:20%;"><div class="etiqueta-campo">Efectivo cobrado</div>{{ number_format($totales['efectivo'] ?? 0, 2) }}</td>
            <td style="width:20%;"><div class="etiqueta-campo">Efectivo egresos</div>{{ number_format($cierre->efectivo_egresos ?? 0, 2) }}</td>
            <td style="width:20%;"><div class="etiqueta-campo">Recibos vigentes</div>{{ $cierre->recibos }}</td>
            <td style="width:20%;"><div class="etiqueta-campo">Recibos anulados</div>{{ $totales['anulados'] ?? 0 }}</td>
        </tr>
    </table>
    @if ($cierre->observaciones)
        <div class="etiqueta-campo">Observaciones</div>
        {!! nl2br(e($cierre->observaciones)) !!}
    @endif
</div>

<table class="bloque" style="margin-top: 30px;">
    <tr>
        <td class="cen" style="width:50%; border-top: 1px solid #1f2937; padding-top: 6px;">Cajero responsable</td>
        <td style="width:4%;"></td>
        <td class="cen" style="width:46%; border-top: 1px solid #1f2937; padding-top: 6px;">Supervisor</td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · Cierre de caja generado por OdontoSuite</div>
</body>
</html>
