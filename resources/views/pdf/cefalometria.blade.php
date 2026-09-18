<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Informe Cefalométrico - {{ $cefalometria->paciente->nombre_completo }}</title>
    @include('pdf._estilos')
</head>
<body>
@include('pdf._encabezado')
@php $medidas = $cefalometria->medidas ?? []; @endphp

<div class="titulo-doc">INFORME DE ANÁLISIS CEFALOMÉTRICO (ORTODONCIA)</div>
<div class="subtitulo-doc">
    Paciente: {{ $cefalometria->paciente->nombre_completo }} ·
    Doc: {{ $cefalometria->paciente->tipo_documento }} {{ $cefalometria->paciente->numero_documento }} ·
    Fecha: {{ $cefalometria->fecha->format('d/m/Y') }} · Tipo: {{ $cefalometria->tipo_analisis }}
</div>

<table class="tarjetas bloque">
    <tr>
        <td>
            <div class="etiqueta">Diagnóstico Esquelético</div>
            <div class="valor">{{ \App\Models\TrazadoCefalometrico::DIAGNOSTICOS[$cefalometria->diagnostico_esqueletico] ?? $cefalometria->diagnostico_esqueletico }}</div>
            <small>ANB: {{ $medidas['ANB'] ?? '—' }}°</small>
        </td>
        <td>
            <div class="etiqueta">Biotipo / Crecimiento</div>
            <div class="valor">{{ \App\Models\TrazadoCefalometrico::PATRONES[$cefalometria->patron_crecimiento] ?? $cefalometria->patron_crecimiento }}</div>
            <small>GoGn-SN: {{ $medidas['GoGn_SN'] ?? '—' }}°</small>
        </td>
        <td>
            <div class="etiqueta">Doctor Especialista</div>
            <div class="valor" style="font-size:13px;">{{ $cefalometria->doctor?->nombre_profesional ?? '—' }}</div>
        </td>
    </tr>
</table>

<div class="bloque">
    <div class="titulo-doc" style="font-size:12px; margin-bottom: 6px;">TABLA DE MEDIDAS CEFALOMÉTRICAS (STEINER)</div>
    <table>
        <thead>
            <tr>
                <th>Parámetro Cefalométrico</th>
                <th class="cen">Norma</th>
                <th class="cen">Medido</th>
                <th>Diagnóstico Clínico</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Ángulo SNA</strong> (Posición anteroposterior maxilar)</td>
                <td class="cen">82.0° ± 2°</td>
                <td class="cen fw-bold">{{ $medidas['SNA'] ?? '—' }}°</td>
                <td>{{ ($medidas['SNA'] ?? 0) > 84 ? 'Prognatismo maxilar' : (($medidas['SNA'] ?? 0) < 80 ? 'Retrognatismo maxilar' : 'Normoposición maxilar') }}</td>
            </tr>
            <tr>
                <td><strong>Ángulo SNB</strong> (Posición anteroposterior mandibular)</td>
                <td class="cen">80.0° ± 2°</td>
                <td class="cen fw-bold">{{ $medidas['SNB'] ?? '—' }}°</td>
                <td>{{ ($medidas['SNB'] ?? 0) > 82 ? 'Prognatismo mandibular' : (($medidas['SNB'] ?? 0) < 78 ? 'Retrognatismo mandibular' : 'Normoposición mandibular') }}</td>
            </tr>
            <tr style="background-color: #f1f5f9;">
                <td><strong>Ángulo ANB</strong> (Relación sagital maxilomandibular)</td>
                <td class="cen"><strong>2.0° ± 2°</strong></td>
                <td class="cen" style="font-weight: bold; color: #1e40af;">{{ $medidas['ANB'] ?? '—' }}°</td>
                <td><strong>{{ ($medidas['ANB'] ?? 0) > 4 ? 'Clase II Esquelética' : (($medidas['ANB'] ?? 0) < 0 ? 'Clase III Esquelética' : 'Clase I Esquelética') }}</strong></td>
            </tr>
            <tr>
                <td><strong>GoGn-SN</strong> (Plano mandibular a base craneal)</td>
                <td class="cen">32.0° ± 3°</td>
                <td class="cen fw-bold">{{ $medidas['GoGn_SN'] ?? '—' }}°</td>
                <td>{{ ($medidas['GoGn_SN'] ?? 0) > 35 ? 'Dolicofacial (Crecimiento Vertical)' : (($medidas['GoGn_SN'] ?? 0) < 29 ? 'Braquifacial (Crecimiento Horizontal)' : 'Mesofacial (Normodivergente)') }}</td>
            </tr>
            <tr>
                <td><strong>1-NA</strong> (Incisivo Superior a línea NA)</td>
                <td class="cen">22.0° / 4.0 mm</td>
                <td class="cen">{{ $medidas['UI_NA_deg'] ?? '—' }}° / {{ $medidas['UI_NA_mm'] ?? '—' }} mm</td>
                <td>Inclinación y posición sagital del incisivo superior</td>
            </tr>
            <tr>
                <td><strong>1-NB</strong> (Incisivo Inferior a línea NB)</td>
                <td class="cen">25.0° / 4.0 mm</td>
                <td class="cen">{{ $medidas['LI_NB_deg'] ?? '—' }}° / {{ $medidas['LI_NB_mm'] ?? '—' }} mm</td>
                <td>Inclinación y posición sagital del incisivo inferior</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="bloque caja">
    <div class="etiqueta-campo">Interpretación Clínica Diagnóstica:</div>
    <p style="font-size: 10px; margin: 0 0 8px 0;">{!! nl2br(e($cefalometria->interpretacion ?: 'Sin interpretación.')) !!}</p>
    <div class="etiqueta-campo">Plan de Tratamiento Ortodóntico:</div>
    <p style="font-size: 10px; margin: 0;">{!! nl2br(e($cefalometria->plan_tratamiento ?: 'Sin plan registrado.')) !!}</p>
</div>

<table class="bloque" style="margin-top: 40px;">
    <tr>
        <td class="cen" style="width:50%; border-top: 1px solid #1f2937; padding-top: 6px;">Firma del Ortodoncista</td>
        <td style="width:10%;"></td>
        <td class="cen" style="width:40%; border-top: 1px solid #1f2937; padding-top: 6px;">Sello Profesional</td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · Informe de Cefalometría generado por OdontoSuite</div>
</body>
</html>
