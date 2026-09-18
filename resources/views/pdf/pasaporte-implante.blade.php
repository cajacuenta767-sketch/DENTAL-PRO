<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pasaporte de Implante - {{ $implante->paciente->nombre_completo }}</title>
    <style>
        @page { size: a5 landscape; margin: 8mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1e293b; line-height: 1.35; margin: 0; }
        .marco { border: 2px solid #0d9488; padding: 10px; border-radius: 6px; position: relative; background: #ffffff; }
        .encabezado-tabla { width: 100%; border-bottom: 2px solid #0d9488; padding-bottom: 6px; margin-bottom: 8px; }
        .titulo-doc { font-size: 15px; font-weight: bold; color: #0d9488; letter-spacing: .03em; margin: 0; }
        .subtitulo-doc { font-size: 8px; color: #64748b; text-transform: uppercase; margin-top: 2px; }
        .clinica-nom { font-size: 11px; font-weight: bold; text-align: right; color: #0f172a; }
        .clinica-meta { font-size: 7.5px; color: #64748b; text-align: right; }
        
        .seccion-titulo { background: #f0fdfa; border-left: 3px solid #0d9488; padding: 3px 6px; font-size: 9px; font-weight: bold; color: #115e59; text-transform: uppercase; margin: 6px 0 4px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.datos td { padding: 3px 5px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        .lbl { font-weight: bold; color: #475569; width: 30%; }
        .val { color: #0f172a; }
        
        .caja-fdi {
            background: #0d9488;
            color: #ffffff;
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .qr-box {
            text-align: center;
            padding: 4px;
        }
        .pie-garantia {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px dashed #cbd5e1;
            font-size: 7px;
            color: #64748b;
            text-align: justify;
        }
        .firma {
            border-top: 1px solid #94a3b8;
            margin-top: 25px;
            text-align: center;
            font-size: 7.5px;
            color: #334155;
            padding-top: 3px;
        }
    </style>
</head>
<body>
    <div class="marco">
        <table class="encabezado-tabla">
            <tr>
                <td style="width: 60%; vertical-align: middle;">
                    <div class="titulo-doc">PASAPORTE IMPLANTOLÓGICO DIGITAL</div>
                    <div class="subtitulo-doc">INTERNATIONAL IMPLANT PASSPORT &amp; TRACEABILITY CERTIFICATE</div>
                </td>
                <td style="width: 40%; vertical-align: middle;">
                    <div class="clinica-nom">{{ $clinica->nombre ?? 'DENTAL PRO' }}</div>
                    <div class="clinica-meta">{{ $clinica->direccion ?? 'Clínica Odontológica Especializada' }}</div>
                    <div class="clinica-meta">Tel: {{ $clinica->telefono ?? '—' }} &middot; {{ $clinica->email ?? '' }}</div>
                </td>
            </tr>
        </table>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                {{-- Columna Izquierda: Datos del Paciente e Implante --}}
                <td style="width: 72%; vertical-align: top; padding-right: 10px;">
                    <div class="seccion-titulo">1. Datos del Paciente</div>
                    <table class="datos">
                        <tr>
                            <td class="lbl">Nombre y Apellidos:</td>
                            <td class="val"><strong>{{ $implante->paciente->nombre_completo }}</strong></td>
                            <td class="lbl">Documento:</td>
                            <td class="val">{{ $implante->paciente->numero_documento }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Fecha de Nacimiento:</td>
                            <td class="val">{{ $implante->paciente->fecha_nacimiento ? $implante->paciente->fecha_nacimiento->format('d/m/Y') : '—' }}</td>
                            <td class="lbl">Historia Clínica:</td>
                            <td class="val">HC-{{ str_pad($implante->paciente->id, 5, '0', STR_PAD_LEFT) }}</td>
                        </tr>
                    </table>

                    <div class="seccion-titulo">2. Identificación del Implante (Trazabilidad Sanitaria)</div>
                    <table class="datos">
                        <tr>
                            <td class="lbl">Marca / Fabricante:</td>
                            <td class="val"><strong>{{ $implante->marca }}</strong></td>
                            <td class="lbl">Modelo / Plataforma:</td>
                            <td class="val">{{ $implante->modelo }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Número de Lote (LOT):</td>
                            <td class="val"><strong style="color: #0d9488;">{{ $implante->numero_lote }}</strong></td>
                            <td class="lbl">Número de Serie (SN):</td>
                            <td class="val">{{ $implante->numero_serie ?: 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Dimensiones:</td>
                            <td class="val">&Oslash; {{ $implante->diametro_mm }} mm &times; {{ $implante->longitud_mm }} mm</td>
                            <td class="lbl">Tipo de Conexión:</td>
                            <td class="val">{{ $implante->conexion_nombre }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Torque de Inserción:</td>
                            <td class="val">{{ $implante->torque_insercion_ncm ? $implante->torque_insercion_ncm.' Ncm' : '—' }}</td>
                            <td class="lbl">Estabilidad Primaria (ISQ):</td>
                            <td class="val">{{ $implante->isq_estabilidad ? $implante->isq_estabilidad.' ISQ' : '—' }}</td>
                        </tr>
                        @if ($implante->injerto_oseo || $implante->membrana)
                        <tr>
                            <td class="lbl">Biomateriales / Injerto:</td>
                            <td class="val" colspan="3">{{ $implante->injerto_oseo ?? 'Sin injerto' }} &middot; {{ $implante->membrana ? 'Membrana: '.$implante->membrana : 'Sin membrana' }}</td>
                        </tr>
                        @endif
                    </table>

                    <div class="seccion-titulo">3. Procedimiento y Fechas</div>
                    <table class="datos">
                        <tr>
                            <td class="lbl">Fecha Colocación:</td>
                            <td class="val"><strong>{{ $implante->fecha_colocacion->format('d/m/Y') }}</strong></td>
                            <td class="lbl">Fecha Rehabilitación:</td>
                            <td class="val">{{ $implante->fecha_rehabilitacion ? $implante->fecha_rehabilitacion->format('d/m/Y') : 'En fase de osteointegración' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Cirujano Implantólogo:</td>
                            <td class="val">{{ $implante->doctor?->nombre_profesional ?? 'Dr. Especialista' }}</td>
                            <td class="lbl">Estado Actual:</td>
                            <td class="val"><strong>{{ $implante->estado_nombre }}</strong></td>
                        </tr>
                    </table>
                </td>

                {{-- Columna Derecha: Pieza FDI, QR y Sello --}}
                <td style="width: 28%; vertical-align: top; text-align: center; border-left: 1px solid #e2e8f0; padding-left: 8px;">
                    <div style="font-size: 7.5px; font-weight: bold; color: #475569; margin-bottom: 2px;">POSICIÓN DENTAL (FDI)</div>
                    <div class="caja-fdi">
                        PIEZA {{ $implante->posicion_fdi }}
                    </div>

                    <div class="qr-box">
                        @if (!empty($qrDataUri))
                            <img src="{{ $qrDataUri }}" width="90" height="90" alt="QR Pasaporte">
                        @endif
                        <div style="font-size: 6.5px; color: #64748b; margin-top: 3px;">VALIDACIÓN DIGITAL</div>
                    </div>

                    <div class="firma">
                        <div>{{ $implante->doctor?->nombre_profesional ?? 'Cirujano Responsable' }}</div>
                        <div style="font-size: 6.5px; color: #64748b;">Firma y Sello Profesional</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="pie-garantia">
            <strong>CERTIFICADO DE TRAZABILIDAD SANITARIA:</strong> Este documento certifica la colocación de un implante osteointegrado de titanio o circonio de grado médico conforme a directivas sanitarias internacionales (ISO 13485 / MDR). Conserve este pasaporte para futuras intervenciones protésicas o de mantenimiento periodontal en cualquier clínica dental del mundo.
        </div>
    </div>
</body>
</html>
