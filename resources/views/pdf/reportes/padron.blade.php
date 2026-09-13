<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $titulo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf.reportes._cabecera')

<table class="tarjetas bloque">
    <tr>
        <td><div class="etiqueta">Pacientes en padrón</div><div class="valor">{{ $pacTotal }}</div></td>
        <td><div class="etiqueta">Nuevos en el período</div><div class="valor">{{ $pacNuevos }}</div></td>
        <td><div class="etiqueta">Activos</div><div class="valor">{{ $pacActivos }}</div></td>
        <td><div class="etiqueta">Con saldo pendiente</div><div class="valor">{{ $pacConSaldo->count() }}</div></td>
    </tr>
</table>

<div class="titulo-doc" style="font-size:11px;">Pacientes con saldo pendiente</div>
<table class="bloque">
    <thead><tr><th>Paciente</th><th>Documento</th><th>Teléfono</th><th>Correo</th><th class="der">Saldo</th></tr></thead>
    <tbody>
        @forelse ($pacConSaldo as $paciente)
            <tr>
                <td>{{ $paciente->nombre_completo }}</td>
                <td>{{ $paciente->tipo_documento }} {{ $paciente->numero_documento }}</td>
                <td>{{ $paciente->telefono ?: '—' }}</td>
                <td>{{ $paciente->email ?: '—' }}</td>
                <td class="der">{{ number_format($paciente->saldo_total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="cen apagado">Ningún paciente tiene saldo pendiente.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Pacientes más frecuentes</div>
<table class="bloque">
    <thead><tr><th>Paciente</th><th class="cen">Citas en el período</th><th class="cen">Edad</th></tr></thead>
    <tbody>
        @forelse ($pacFrecuentes as $paciente)
            <tr>
                <td>{{ $paciente->nombre_completo }}</td>
                <td class="cen">{{ $paciente->citas_count }}</td>
                <td class="cen">{{ $paciente->edad !== null ? $paciente->edad : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="cen apagado">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="titulo-doc" style="font-size:11px;">Distribución por género</div>
<table>
    <thead><tr><th>Género</th><th class="cen">Pacientes</th></tr></thead>
    <tbody>
        @foreach ($pacPorGenero as $fila)
            <tr>
                <td>{{ ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'][$fila->genero] ?? $fila->genero }}</td>
                <td class="cen">{{ $fila->total }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="pie">{{ $clinica->nombre }} · Reporte generado por OdontoSuite</div>
</body>
</html>
