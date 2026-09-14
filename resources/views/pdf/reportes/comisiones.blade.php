<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $titulo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf.reportes._cabecera')

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Cobrado a nombre de doctores</div><div class="valor">{{ number_format($comCobrado, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Comisiones a liquidar</div><div class="valor">{{ number_format($comTotal, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Recibos con doctor</div><div class="valor">{{ $comRecibos }}</div></td>
        <td><div class="etiqueta">Doctores con cobros</div><div class="valor">{{ $comFilas->count() }}</div></td>
    </tr>
</table>

<div class="titulo-doc" style="font-size:11px;">Comisiones por doctor</div>
<div class="subtitulo-doc">Comisión = cobrado × porcentaje definido en la ficha del doctor. Solo cuentan los recibos vigentes cobrados en el período.</div>
<table>
    <thead><tr><th>Doctor</th><th>Especialidad</th><th class="cen">Recibos</th>
        <th class="der">Cobrado</th><th class="cen">% comisión</th><th class="der">Comisión</th></tr></thead>
    <tbody>
        @forelse ($comFilas as $fila)
            <tr>
                <td>{{ $fila['doctor']->nombre_profesional }}</td>
                <td>{{ $fila['doctor']->especialidad?->nombre ?? '—' }}</td>
                <td class="cen">{{ $fila['recibos'] }}</td>
                <td class="der">{{ number_format($fila['cobrado'], 2) }}</td>
                <td class="cen">{{ number_format($fila['porcentaje'], 2) }} %</td>
                <td class="der">{{ number_format($fila['comision'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="cen apagado">Sin recibos cobrados a nombre de un doctor en el período.</td></tr>
        @endforelse
    </tbody>
    @if ($comFilas->isNotEmpty())
        <tfoot>
            <tr>
                <th colspan="2">Totales</th>
                <th class="cen">{{ $comRecibos }}</th>
                <th class="der">{{ number_format($comCobrado, 2) }}</th>
                <th></th>
                <th class="der">{{ number_format($comTotal, 2) }} {{ $clinica->divisa }}</th>
            </tr>
        </tfoot>
    @endif
</table>

<div class="pie">{{ $clinica->nombre }} · Reporte generado por OdontoSuite</div>
</body>
</html>
