<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha Endodóntica - Pieza {{ $conductometria->diente }}</title>
    @include('pdf._estilos')
</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">REGISTRO CLÍNICO DE ENDODONCIA Y CONDUCTOMETRÍA</div>
<div class="subtitulo-doc">
    Paciente: {{ $conductometria->paciente->nombre_completo }} ·
    Doc: {{ $conductometria->paciente->tipo_documento }} {{ $conductometria->paciente->numero_documento }} ·
    Fecha: {{ $conductometria->fecha->format('d/m/Y') }}
</div>

<table class="tarjetas bloque">
    <tr>
        <td>
            <div class="etiqueta">Pieza Dental</div>
            <div class="valor">Pieza {{ $conductometria->diente }}</div>
            <small>{{ $conductometria->nombre_diente }}</small>
        </td>
        <td>
            <div class="etiqueta">Estado</div>
            <div class="valor">{{ \App\Models\Conductometria::ESTADOS[$conductometria->estado] ?? $conductometria->estado }}</div>
        </td>
        <td>
            <div class="etiqueta">Doctor Responsable</div>
            <div class="valor" style="font-size:13px;">{{ $conductometria->doctor?->nombre_profesional ?? '—' }}</div>
        </td>
    </tr>
</table>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width: 50%;">
                <div class="etiqueta-campo">Diagnóstico Pulpar</div>
                <strong>{{ $conductometria->diagnostico_pulpar ?: 'No especificado' }}</strong>
            </td>
            <td style="width: 50%;">
                <div class="etiqueta-campo">Diagnóstico Periapical</div>
                <strong>{{ $conductometria->diagnostico_periapical ?: 'No especificado' }}</strong>
            </td>
        </tr>
    </table>
</div>

<div class="bloque">
    <div class="titulo-doc" style="font-size:12px; margin-bottom: 6px;">MATRIZ DE CONDUCTOMETRÍA</div>
    <table>
        <thead>
            <tr>
                <th>Conducto</th>
                <th>Referencia Anatómica</th>
                <th class="cen">Longitud Aparente</th>
                <th class="cen">Longitud de Trabajo (WL)</th>
                <th class="cen">Lima Apical (MAF)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($conductometria->conductos ?? [] as $c)
                <tr>
                    <td><strong>{{ $c['nombre'] ?? '—' }}</strong></td>
                    <td>{{ $c['referencia'] ?? '—' }}</td>
                    <td class="cen">{{ !empty($c['longitud_aparente']) ? $c['longitud_aparente'].' mm' : '—' }}</td>
                    <td class="cen" style="font-weight: bold; color: #1e40af;">{{ !empty($c['longitud_trabajo']) ? $c['longitud_trabajo'].' mm' : '—' }}</td>
                    <td class="cen">{{ $c['lima_apical'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="cen apagado">Sin conductos registrados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="bloque caja">
    <div class="titulo-doc" style="font-size:11px; margin-bottom: 4px;">PROTOCOLO QUÍMICO Y DE OBTURACIÓN</div>
    <table>
        <tr>
            <td style="width: 50%;"><div class="etiqueta-campo">Solución Irrigante:</div> {{ $conductometria->solucion_irrigante ?: '—' }}</td>
            <td style="width: 50%;"><div class="etiqueta-campo">Medicación Intraconducto:</div> {{ $conductometria->medicacion_intraconducto ?: '—' }}</td>
        </tr>
        <tr>
            <td><div class="etiqueta-campo">Cemento Sellador:</div> {{ $conductometria->cemento_sellador ?: '—' }}</td>
            <td><div class="etiqueta-campo">Técnica de Obturación:</div> {{ $conductometria->tecnica_obturacion ?: '—' }}</td>
        </tr>
    </table>
    @if ($conductometria->observaciones)
        <div class="etiqueta-campo" style="margin-top: 8px;">Observaciones:</div>
        <p style="font-size: 10px; margin: 0;">{!! nl2br(e($conductometria->observaciones)) !!}</p>
    @endif
</div>

<table class="bloque" style="margin-top: 40px;">
    <tr>
        <td class="cen" style="width:50%; border-top: 1px solid #1f2937; padding-top: 6px;">Firma del Odontólogo / Especialista</td>
        <td style="width:10%;"></td>
        <td class="cen" style="width:40%; border-top: 1px solid #1f2937; padding-top: 6px;">Sello Profesional</td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · Ficha de Endodoncia generada por OdontoSuite</div>
</body>
</html>
