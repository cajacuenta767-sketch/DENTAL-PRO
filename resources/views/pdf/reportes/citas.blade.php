<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $titulo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf.reportes._cabecera')

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Agendadas</div><div class="valor">{{ $citTotal }}</div></td>
        <td><div class="etiqueta">Completadas</div><div class="valor">{{ $citCompletadas }}</div></td>
        <td><div class="etiqueta">Canceladas</div><div class="valor">{{ $citCanceladas }}</div></td>
        <td><div class="etiqueta">Tasa de asistencia</div><div class="valor">{{ $citTasaAsistencia }}%</div></td>
    </tr>
</table>

<div class="titulo-doc" style="font-size:11px;">Productividad por doctor</div>
<table class="bloque">
    <thead><tr><th>Doctor</th><th>Especialidad</th><th class="cen">Agendadas</th>
        <th class="cen">Completadas</th><th class="cen">Efectividad</th></tr></thead>
    <tbody>
        @forelse ($citPorDoctor as $fila)
            <tr>
                <td>{{ $fila->doctor?->nombre_profesional ?? '—' }}</td>
                <td>{{ $fila->doctor?->especialidad?->nombre ?? '—' }}</td>
                <td class="cen">{{ $fila->total }}</td>
                <td class="cen">{{ $fila->completadas }}</td>
                <td class="cen">{{ $fila->total > 0 ? round($fila->completadas / $fila->total * 100, 1) : 0 }}%</td>
            </tr>
        @empty
            <tr><td colspan="5" class="cen apagado">Sin citas en el período.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Evolución mensual</div>
<table class="bloque">
    <thead><tr><th>Período</th><th class="cen">Agendadas</th><th class="cen">Completadas</th></tr></thead>
    <tbody>
        @forelse ($citPorMes as $fila)
            <tr>
                <td>{{ $fila->periodo }}</td>
                <td class="cen">{{ $fila->agendadas }}</td>
                <td class="cen">{{ $fila->completadas }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="cen apagado">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Reparto por estado</div>
<table>
    <thead><tr><th>Estado</th><th class="cen">Citas</th></tr></thead>
    <tbody>
        @foreach ($citPorEstado as $fila)
            <tr><td>{{ str_replace('_', ' ', $fila->estado) }}</td><td class="cen">{{ $fila->total }}</td></tr>
        @endforeach
    </tbody>
</table>

<div class="pie">{{ $clinica->nombre }} · Reporte generado por OdontoSuite</div>
</body>
</html>
