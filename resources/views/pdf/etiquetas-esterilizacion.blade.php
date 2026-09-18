<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas de Esterilización - Ciclo #{{ $ciclo->numero_ciclo }}</title>
    @include('pdf._estilos')
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        .grid-etiquetas { width: 100%; border-collapse: separate; border-spacing: 6mm; }
        .etiqueta {
            width: 50%;
            border: 1.5px dashed #0d9488;
            padding: 8px;
            background: #fafdfc;
            page-break-inside: avoid;
            vertical-align: top;
        }
        .etiqueta-header {
            border-bottom: 1px solid #0d9488;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .etiqueta-titulo {
            font-size: 10px;
            font-weight: bold;
            color: #0d9488;
            text-transform: uppercase;
        }
        .etiqueta-sub {
            font-size: 7px;
            color: #64748b;
        }
        .dato-fila {
            margin-bottom: 3px;
        }
        .dato-lbl {
            font-weight: bold;
            color: #334155;
            display: inline-block;
            width: 65px;
        }
        .dato-val {
            color: #0f172a;
        }
        .qr-box {
            text-align: center;
            vertical-align: middle;
            width: 70px;
        }
        .banner-estado {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            text-align: center;
            padding: 2px 4px;
            border-radius: 3px;
            font-size: 8px;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    @php
        $totalPaquetes = min($ciclo->paquetes_esterilizados, 12);
    @endphp

    <table class="grid-etiquetas">
        @for ($i = 1; $i <= $totalPaquetes; $i += 2)
            <tr>
                {{-- Etiqueta Izquierda --}}
                <td class="etiqueta">
                    <div class="etiqueta-header">
                        <div class="etiqueta-titulo">{{ $clinica->nombre ?? 'DENTAL PRO' }}</div>
                        <div class="etiqueta-sub">BIOSEGURIDAD Y CONTROL DE INFECCIONES</div>
                    </div>
                    <table>
                        <tr>
                            <td style="padding: 0; vertical-align: top;">
                                <div class="dato-fila"><span class="dato-lbl">AUTOCLAVE:</span> <span class="dato-val">{{ Str::limit($ciclo->autoclave_nombre, 22) }}</span></div>
                                <div class="dato-fila"><span class="dato-lbl">CICLO / PQT:</span> <span class="dato-val fw-bold">#{{ $ciclo->numero_ciclo }} &middot; [{{ $i }}/{{ $ciclo->paquetes_esterilizados }}]</span></div>
                                <div class="dato-fila"><span class="dato-lbl">PROCESO:</span> <span class="dato-val">{{ $ciclo->fecha->format('d/m/Y') }} {{ $ciclo->hora_inicio }}</span></div>
                                <div class="dato-fila"><span class="dato-lbl">VENCE:</span> <strong style="color: #0d9488; font-size: 9px;">{{ $ciclo->fecha_caducidad_paquetes->format('d/m/Y') }}</strong></div>
                                <div class="dato-fila"><span class="dato-lbl">CARGA:</span> <span class="dato-val">{{ Str::limit($ciclo->tipo_carga_nombre, 20) }}</span></div>
                                <div class="dato-fila"><span class="dato-lbl">OPERADOR:</span> <span class="dato-val">{{ Str::limit($ciclo->usuario?->name ?? 'Operador', 18) }}</span></div>
                                <div class="banner-estado">ESTÉRIL &middot; {{ $ciclo->resultado }}</div>
                            </td>
                            <td class="qr-box" style="padding: 0 0 0 6px;">
                                @if (!empty($qrDataUri))
                                    <img src="{{ $qrDataUri }}" width="65" height="65" alt="QR Verificación">
                                    <div style="font-size: 6px; color: #64748b; margin-top: 2px;">SCAN CHAIRSIDE</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>

                {{-- Etiqueta Derecha (si existe) --}}
                @if ($i + 1 <= $totalPaquetes)
                    <td class="etiqueta">
                        <div class="etiqueta-header">
                            <div class="etiqueta-titulo">{{ $clinica->nombre ?? 'DENTAL PRO' }}</div>
                            <div class="etiqueta-sub">BIOSEGURIDAD Y CONTROL DE INFECCIONES</div>
                        </div>
                        <table>
                            <tr>
                                <td style="padding: 0; vertical-align: top;">
                                    <div class="dato-fila"><span class="dato-lbl">AUTOCLAVE:</span> <span class="dato-val">{{ Str::limit($ciclo->autoclave_nombre, 22) }}</span></div>
                                    <div class="dato-fila"><span class="dato-lbl">CICLO / PQT:</span> <span class="dato-val fw-bold">#{{ $ciclo->numero_ciclo }} &middot; [{{ $i + 1 }}/{{ $ciclo->paquetes_esterilizados }}]</span></div>
                                    <div class="dato-fila"><span class="dato-lbl">PROCESO:</span> <span class="dato-val">{{ $ciclo->fecha->format('d/m/Y') }} {{ $ciclo->hora_inicio }}</span></div>
                                    <div class="dato-fila"><span class="dato-lbl">VENCE:</span> <strong style="color: #0d9488; font-size: 9px;">{{ $ciclo->fecha_caducidad_paquetes->format('d/m/Y') }}</strong></div>
                                    <div class="dato-fila"><span class="dato-lbl">CARGA:</span> <span class="dato-val">{{ Str::limit($ciclo->tipo_carga_nombre, 20) }}</span></div>
                                    <div class="dato-fila"><span class="dato-lbl">OPERADOR:</span> <span class="dato-val">{{ Str::limit($ciclo->usuario?->name ?? 'Operador', 18) }}</span></div>
                                    <div class="banner-estado">ESTÉRIL &middot; {{ $ciclo->resultado }}</div>
                                </td>
                                <td class="qr-box" style="padding: 0 0 0 6px;">
                                    @if (!empty($qrDataUri))
                                        <img src="{{ $qrDataUri }}" width="65" height="65" alt="QR Verificación">
                                        <div style="font-size: 6px; color: #64748b; margin-top: 2px;">SCAN CHAIRSIDE</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                @else
                    <td style="width: 50%;"></td>
                @endif
            </tr>
        @endfor
    </table>
</body>
</html>
