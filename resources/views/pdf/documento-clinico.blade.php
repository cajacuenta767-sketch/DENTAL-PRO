<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $documento->folio }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">{{ mb_strtoupper($documento->tipo_legible) }} · {{ $documento->folio }}</div>
<div class="subtitulo-doc">
    Emitido el {{ $documento->fecha_emision->format('d/m/Y') }}
    @if ($documento->vence_el) · Vigente hasta el {{ $documento->vence_el->format('d/m/Y') }} @endif
    @if ($documento->estado === 'ANULADO') · <strong>DOCUMENTO ANULADO</strong> @endif
</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="etiqueta-campo">Paciente</div>
                <strong>{{ $documento->paciente->nombre_completo }}</strong><br>
                {{ $documento->paciente->tipo_documento }} {{ $documento->paciente->numero_documento }}<br>
                Edad: {{ $documento->paciente->edad !== null ? $documento->paciente->edad.' años' : 'No registra' }}
                @if ($documento->paciente->aseguradora)
                    <br>{{ $documento->paciente->aseguradora->nombre }}
                    @if ($documento->paciente->numero_afiliado) · Afiliado {{ $documento->paciente->numero_afiliado }} @endif
                @endif
            </td>
            <td style="width:50%;">
                <div class="etiqueta-campo">Profesional</div>
                <strong>{{ $documento->doctor->nombre_profesional }}</strong><br>
                {{ $documento->doctor->especialidad->nombre }}
                @if ($documento->doctor->colegiatura)<br>Colegiatura: {{ $documento->doctor->colegiatura }}@endif
            </td>
        </tr>
    </table>
</div>

<div class="bloque">
    <div class="etiqueta-campo">{{ $documento->titulo }}</div>
    <div class="caja" style="min-height: 180px;">{!! nl2br(e($documento->contenido)) !!}</div>
</div>

@if ($documento->indicaciones)
    <div class="bloque">
        <div class="etiqueta-campo">Indicaciones</div>
        <div class="caja">{!! nl2br(e($documento->indicaciones)) !!}</div>
    </div>
@endif

<table style="margin-top: 40px;">
    <tr>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <strong>{{ $documento->doctor->nombre_profesional }}</strong><br>
            <span class="apagado">{{ $documento->doctor->especialidad->nombre }}</span>
            @if ($documento->doctor->colegiatura)
                <br><span class="apagado">Colegiatura {{ $documento->doctor->colegiatura }}</span>
            @endif
        </td>
        <td class="cen" style="width:50%;">
            @if ($documento->esta_firmado && $documento->firma_data_uri)
                <div class="etiqueta-campo">Firma del paciente</div>
                <img src="{{ $documento->firma_data_uri }}" alt="Firma del paciente" style="max-height:70px; max-width:240px;"><br>
                ______________________________<br>
                <strong>{{ $documento->paciente->nombre_completo }}</strong><br>
                <span class="apagado">Firmado el {{ $documento->firmado_en->format('d/m/Y H:i') }}</span>
            @else
                ______________________________<br>
                <span class="apagado">Recibí conforme · {{ $documento->paciente->nombre_completo }}</span>
            @endif
        </td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · {{ $documento->folio }} · Documento clínico confidencial</div>
</body>
</html>
