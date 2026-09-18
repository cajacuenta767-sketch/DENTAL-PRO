<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Laboratorio - {{ $orden->folio }}</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
            size: a5 portrait;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #206bc4;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .clinic-name {
            font-size: 16px;
            font-weight: bold;
            color: #206bc4;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            color: #333;
        }
        .folio-box {
            font-family: monospace;
            font-size: 13px;
            font-weight: bold;
            color: #206bc4;
            text-align: right;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #495057;
            background-color: #f1f3f5;
            padding: 4px 8px;
            margin-top: 10px;
            margin-bottom: 6px;
            border-left: 3px solid #206bc4;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.data-table td {
            padding: 3px 4px;
            vertical-align: top;
        }
        .label {
            color: #6c757d;
            font-size: 10px;
            text-transform: uppercase;
            width: 30%;
        }
        .value {
            font-weight: 600;
            color: #212529;
        }
        .teeth-box {
            display: inline-block;
            background: #e7f5ff;
            color: #1971c2;
            padding: 3px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-weight: bold;
            font-size: 12px;
            margin-right: 4px;
        }
        .shade-box {
            display: inline-block;
            background: #f3f0ff;
            color: #6741d9;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 12px;
        }
        .instructions-box {
            border: 1px dashed #adb5bd;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            min-height: 80px;
            font-family: monospace;
            font-size: 10px;
            white-space: pre-wrap;
        }
        .signatures {
            margin-top: 30px;
            width: 100%;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            padding: 0 20px;
        }
        .sign-line {
            border-top: 1px solid #495057;
            margin-top: 35px;
            padding-top: 4px;
            font-size: 10px;
        }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #dee2e6;
            padding-top: 6px;
            font-size: 9px;
            color: #868e96;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="clinic-name">{{ $clinica->nombre_clinica ?? config('app.name', 'OdontoSuite') }}</div>
                    <div style="color: #6c757d; font-size: 9px;">
                        {{ $clinica->direccion ?? '' }} {{ $clinica->telefono ? '· Tel: ' . $clinica->telefono : '' }}
                    </div>
                </td>
                <td>
                    <div class="doc-title">ORDEN DE LABORATORIO</div>
                    <div class="folio-box">{{ $orden->folio }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Datos del Odontólogo y Laboratorio</div>
    <table class="data-table">
        <tr>
            <td class="label">Odontólogo:</td>
            <td class="value">{{ $orden->doctor->nombre_profesional }} (Mat: {{ $orden->doctor->matricula ?: '—' }})</td>
        </tr>
        <tr>
            <td class="label">Laboratorio Dental:</td>
            <td class="value">{{ $orden->laboratorio?->nombre ?: 'Sin asignar' }} {{ $orden->laboratorio?->contacto ? '(' . $orden->laboratorio->contacto . ')' : '' }}</td>
        </tr>
        <tr>
            <td class="label">Paciente:</td>
            <td class="value">{{ $orden->paciente->nombre_completo }} (Doc: {{ $orden->paciente->numero_documento }})</td>
        </tr>
    </table>

    <div class="section-title">Especificaciones Técnicas Protésicas</div>
    <table class="data-table">
        <tr>
            <td class="label">Tipo de Trabajo:</td>
            <td class="value" style="font-size: 12px; color: #206bc4;">{{ $orden->tipo_trabajo }}</td>
        </tr>
        <tr>
            <td class="label">Pieza(s) Dental(es):</td>
            <td class="value">
                @if (!empty($orden->dientes_array))
                    @foreach ($orden->dientes_array as $pieza)
                        <span class="teeth-box">{{ $pieza }}</span>
                    @endforeach
                @else
                    <span>Arcada completa / Sin especificar</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Guía de Color VITA:</td>
            <td class="value">
                @if ($orden->color_guia)
                    <span class="shade-box">{{ $orden->color_guia }}</span>
                @else
                    <span>A determinar / Sin especificar</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">Fechas y Cronograma de Entrega</div>
    <table class="data-table">
        <tr>
            <td class="label">Fecha de Envío:</td>
            <td class="value">{{ $orden->fecha_envio->format('d/m/Y') }}</td>
            <td class="label">Fecha Prometida:</td>
            <td class="value" style="color: #d63939; font-size: 11px;">{{ $orden->fecha_prometida->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="section-title">Instrucciones Clínicas y Detalles para el Protesista</div>
    <div class="instructions-box">{{ $orden->notas_tecnicas ?: 'Sin indicaciones técnicas particulares adicionales.' }}</div>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-line">
                    <strong>Firma Odontólogo</strong><br>
                    {{ $orden->doctor->nombre_profesional }}
                </div>
            </td>
            <td>
                <div class="sign-line">
                    <strong>Recepción Laboratorio</strong><br>
                    Fecha y Firma del Protesista
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento técnico confidencial generado automáticamente por OdontoSuite Dental Pro · Emisión: {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
