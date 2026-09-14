@php
    $cuadrantes = \App\Models\Odontograma::PIEZAS_ADULTO;
    $arcadas = [
        'Arcada superior (18 → 28)' => array_merge($cuadrantes['superior_derecho'], $cuadrantes['superior_izquierdo']),
        'Arcada inferior (48 → 38)' => array_merge($cuadrantes['inferior_derecho'], $cuadrantes['inferior_izquierdo']),
    ];
    $filas = [
        'Vestibular' => ['dv', 'v', 'mv'],
        'Lingual / Palatino' => ['dl', 'l', 'ml'],
    ];
    $piezas = $periodontograma->piezas ?? [];
    $hayRecesion = collect($piezas)->contains(fn ($p) => collect($p['recesion'] ?? [])->contains(fn ($v) => $v !== null && $v !== ''));
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Periodontograma</title>
@include('pdf._estilos')
<style>
    .pd { table-layout: fixed; }
    .pd th, .pd td { padding: 3px 2px; text-align: center; font-size: 8px; border: 1px solid #e2e8f0; }
    .pd th.eti { text-align: left; width: 72px; font-size: 7.5px; text-transform: uppercase; color: #475569; background: #f8fafc; }
    .pd thead th.num { font-size: 9px; font-weight: bold; background: #f1f5f9; }
    .pd .sitio { display: inline-block; width: 30%; }
    .pd .bolsa { background: #fff3bf; }
    .pd .profunda { background: #ffc9c9; }
    .pd .ausente { background: #f1f5f9; color: #94a3b8; }
    .pd .sang { color: #d63939; font-weight: bold; }
    .pd .placa { color: #b7791f; font-weight: bold; }
    .pd td.sep, .pd th.sep { border-left: 2px solid #94a3b8; }
    .leyenda { font-size: 8px; color: #6b7280; margin: 4px 0 10px; }
    .leyenda .muestra { display: inline-block; width: 9px; height: 9px; border: 1px solid #cbd5e1; vertical-align: middle; margin-right: 2px; }
    .diag { border-left: 4px solid #0d9488; background: #f0fdfa; padding: 8px; margin-top: 10px; }
</style>
</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">PERIODONTOGRAMA</div>
<div class="subtitulo-doc">Sondaje del {{ $periodontograma->fecha->format('d/m/Y') }}</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="etiqueta-campo">Paciente</div>
                <strong>{{ $periodontograma->paciente->nombre_completo }}</strong><br>
                {{ $periodontograma->paciente->tipo_documento }} {{ $periodontograma->paciente->numero_documento }}<br>
                Edad: {{ $periodontograma->paciente->edad !== null ? $periodontograma->paciente->edad.' años' : 'No registra' }}
            </td>
            <td style="width:50%;">
                <div class="etiqueta-campo">Profesional</div>
                <strong>{{ $periodontograma->doctor?->nombre_profesional ?? 'Sin doctor asignado' }}</strong><br>
                {{ $periodontograma->doctor?->especialidad?->nombre }}
                @if ($periodontograma->doctor?->colegiatura)<br>Colegiatura: {{ $periodontograma->doctor->colegiatura }}@endif
                @if ($periodontograma->cita)<br>Cita: {{ $periodontograma->cita->token }}@endif
            </td>
        </tr>
    </table>
</div>

<div class="leyenda">
    <span class="muestra" style="background:#fff3bf;"></span> 4-5 mm bolsa &nbsp;
    <span class="muestra" style="background:#ffc9c9;"></span> ≥ 6 mm bolsa profunda &nbsp;
    <span class="sang">•</span> sangrado al sondaje &nbsp;
    <span class="placa">○</span> placa &nbsp;
    <span class="muestra" style="background:#f1f5f9;"></span> pieza ausente &nbsp;
    · Sitios por pieza: disto · centro · mesio
</div>

@foreach ($arcadas as $titulo => $numeros)
    <div class="bloque">
        <div class="etiqueta-campo">{{ $titulo }}</div>
        <table class="pd">
            <thead>
                <tr>
                    <th class="eti">Pieza</th>
                    @foreach ($numeros as $n)
                        <th class="num {{ $loop->index === 8 ? 'sep' : '' }}">{{ $n }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $etiqueta => $sitios)
                    <tr>
                        <th class="eti">{{ $etiqueta }}</th>
                        @foreach ($numeros as $n)
                            @php($p = $piezas[(string) $n] ?? \App\Models\Periodontograma::piezaEnBlanco())
                            <td class="{{ ! empty($p['ausente']) ? 'ausente' : '' }} {{ $loop->index === 8 ? 'sep' : '' }}">
                                @if (! empty($p['ausente']))
                                    —
                                @else
                                    @foreach ($sitios as $s)
                                        @php($v = $p['sondaje'][$s] ?? null)
                                        <span class="sitio {{ $v !== null && $v !== '' ? ((int) $v >= 6 ? 'profunda' : ((int) $v >= 4 ? 'bolsa' : '')) : '' }}">{{ $v !== null && $v !== '' ? $v : '·' }}@if (! empty($p['sangrado'][$s]))<span class="sang">•</span>@endif @if (! empty($p['placa'][$s]))<span class="placa">○</span>@endif</span>
                                    @endforeach
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if ($hayRecesion)
                    @foreach ($filas as $etiqueta => $sitios)
                        <tr>
                            <th class="eti">Recesión {{ $loop->first ? 'V' : 'L' }}</th>
                            @foreach ($numeros as $n)
                                @php($p = $piezas[(string) $n] ?? \App\Models\Periodontograma::piezaEnBlanco())
                                <td class="{{ ! empty($p['ausente']) ? 'ausente' : '' }} {{ $loop->index === 8 ? 'sep' : '' }}">
                                    @if (! empty($p['ausente']))
                                        —
                                    @else
                                        @foreach ($sitios as $s)
                                            @php($v = $p['recesion'][$s] ?? null)
                                            <span class="sitio">{{ $v !== null && $v !== '' ? $v : '·' }}</span>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endif
                <tr>
                    <th class="eti">Movilidad / Furca</th>
                    @foreach ($numeros as $n)
                        @php($p = $piezas[(string) $n] ?? \App\Models\Periodontograma::piezaEnBlanco())
                        <td class="{{ ! empty($p['ausente']) ? 'ausente' : '' }} {{ $loop->index === 8 ? 'sep' : '' }}">
                            @if (! empty($p['ausente']))
                                Ausente
                            @else
                                {{ (int) ($p['movilidad'] ?? 0) }} / {{ (int) ($p['furca'] ?? 0) }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
@endforeach

<div class="bloque">
    <div class="etiqueta-campo">Índices</div>
    <table class="tarjetas">
        <tr>
            <td><div class="etiqueta">Sangrado al sondaje</div><div class="valor">{{ number_format($indices['sangrado'], 1) }} %</div></td>
            <td><div class="etiqueta">Índice de placa</div><div class="valor">{{ number_format($indices['placa'], 1) }} %</div></td>
            <td><div class="etiqueta">Profundidad media</div><div class="valor">{{ number_format($indices['profundidad_media'], 2) }} mm</div></td>
            <td><div class="etiqueta">Sitios evaluados</div><div class="valor">{{ $indices['sitios'] }}</div></td>
        </tr>
        <tr>
            <td><div class="etiqueta">Sitios ≥ 4 mm</div><div class="valor">{{ $indices['bolsas'] }}</div></td>
            <td><div class="etiqueta">Sitios ≥ 6 mm</div><div class="valor">{{ $indices['bolsas_profundas'] }}</div></td>
            <td><div class="etiqueta">Piezas con movilidad</div><div class="valor">{{ $indices['movilidad'] }}</div></td>
            <td><div class="etiqueta">Piezas ausentes</div><div class="valor">{{ $indices['ausentes'] }}</div></td>
        </tr>
    </table>
</div>

<div class="diag">
    <div class="etiqueta-campo" style="margin-top:0;">Diagnóstico orientativo</div>
    <strong>{{ $diagnostico }}</strong>
    <div class="apagado" style="font-size:8px; margin-top:2px;">Guía a partir de los índices; no sustituye el criterio clínico del profesional.</div>
</div>

@php($notas = collect($piezas)->filter(fn ($p) => ! empty($p['nota'])))
@if ($notas->isNotEmpty())
    <div class="bloque" style="margin-top:10px;">
        <div class="etiqueta-campo">Notas por pieza</div>
        <div class="caja">
            @foreach ($notas as $n => $p)
                <div><strong>Pieza {{ $n }}:</strong> {{ $p['nota'] }}</div>
            @endforeach
        </div>
    </div>
@endif

<div class="bloque" style="margin-top:10px;">
    <div class="etiqueta-campo">Observaciones</div>
    <div class="caja">{!! nl2br(e($periodontograma->observaciones ?: 'Sin registro')) !!}</div>
</div>

<table style="margin-top: 24px;">
    <tr>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">{{ $periodontograma->doctor?->nombre_profesional ?? 'Profesional' }}</span>
        </td>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">{{ $periodontograma->paciente->nombre_completo }}</span>
        </td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · Periodontograma de {{ $periodontograma->paciente->nombre_completo }} · {{ $periodontograma->fecha->format('d/m/Y') }}</div>
</body>
</html>
