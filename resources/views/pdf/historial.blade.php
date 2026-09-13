<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>Historia clínica</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">HISTORIA CLÍNICA ODONTOLÓGICA</div>
<div class="subtitulo-doc">Consulta del {{ $historial->fecha->format('d/m/Y') }}</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="etiqueta-campo">Paciente</div>
                <strong>{{ $historial->paciente->nombre_completo }}</strong><br>
                {{ $historial->paciente->tipo_documento }} {{ $historial->paciente->numero_documento }}<br>
                Edad: {{ $historial->paciente->edad !== null ? $historial->paciente->edad.' años' : 'No registra' }} ·
                Grupo: {{ $historial->paciente->grupo_sanguineo ?: 'No registra' }}
            </td>
            <td style="width:50%;">
                <div class="etiqueta-campo">Profesional</div>
                <strong>{{ $historial->doctor->nombre_profesional }}</strong><br>
                {{ $historial->doctor->especialidad->nombre }}
                @if ($historial->doctor->colegiatura)<br>Colegiatura: {{ $historial->doctor->colegiatura }}@endif
                @if ($historial->cita)<br>Cita: {{ $historial->cita->token }}@endif
            </td>
        </tr>
    </table>
</div>

<div class="bloque caja">
    <div class="etiqueta-campo">Antecedentes relevantes</div>
    Alergias: {{ $historial->paciente->alergias ?: 'Sin registro' }}<br>
    Enfermedades: {{ $historial->paciente->enfermedades ?: 'Sin registro' }}<br>
    Medicación: {{ $historial->paciente->medicamentos ?: 'Sin registro' }}
</div>

@foreach ([
    'Motivo de consulta' => $historial->motivo_consulta,
    'Síntomas' => $historial->sintomas,
    'Diagnóstico' => $historial->diagnostico,
    'Tratamiento realizado' => $historial->tratamiento_realizado,
    'Prescripción / receta' => $historial->prescripcion_receta,
    'Observaciones e indicaciones' => $historial->observaciones,
] as $titulo => $valor)
    <div class="bloque">
        <div class="etiqueta-campo">{{ $titulo }}</div>
        <div class="caja">{!! nl2br(e($valor ?: 'Sin registro')) !!}</div>
    </div>
@endforeach

<table style="margin-top: 28px;">
    <tr>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">{{ $historial->doctor->nombre_profesional }}</span>
        </td>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">{{ $historial->paciente->nombre_completo }}</span>
        </td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · Documento clínico confidencial</div>
</body>
</html>
