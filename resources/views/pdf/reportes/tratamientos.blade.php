<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $titulo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf.reportes._cabecera')

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Total facturado</div><div class="valor">{{ number_format($traFacturado, 2) }} {{ $clinica->divisa }}</div></td>
        <td><div class="etiqueta">Unidades vendidas</div><div class="valor">{{ $traUnidades }}</div></td>
        <td><div class="etiqueta">Conceptos distintos</div><div class="valor">{{ $traLineas->count() }}</div></td>
        <td><div class="etiqueta">Especialidades con venta</div><div class="valor">{{ $traPorEspecialidad->count() }}</div></td>
    </tr>
</table>

<div class="titulo-doc" style="font-size:11px;">Ranking de tratamientos facturados</div>
<table class="bloque">
    <thead><tr><th style="width:3%;">#</th><th>Concepto</th><th class="cen">Unidades</th>
        <th class="der">Facturado</th><th class="der">% del total</th></tr></thead>
    <tbody>
        @forelse ($traLineas as $linea)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $linea->descripcion }}</td>
                <td class="cen">{{ $linea->unidades }}</td>
                <td class="der">{{ number_format($linea->facturado, 2) }}</td>
                <td class="der">{{ $traFacturado > 0 ? round($linea->facturado / $traFacturado * 100, 1) : 0 }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" class="cen apagado">Sin facturación en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Facturación por especialidad</div>
<table>
    <thead><tr><th>Especialidad</th><th class="der">Facturado</th></tr></thead>
    <tbody>
        @forelse ($traPorEspecialidad as $fila)
            <tr><td>{{ $fila->nombre }}</td><td class="der">{{ number_format($fila->facturado, 2) }}</td></tr>
        @empty
            <tr><td colspan="2" class="cen apagado">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="pie">{{ $clinica->nombre }} · Reporte generado por OdontoSuite</div>
</body>
</html>
