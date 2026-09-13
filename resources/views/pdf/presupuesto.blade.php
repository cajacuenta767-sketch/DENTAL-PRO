<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><title>{{ $presupuesto->codigo }}</title>@include('pdf._estilos')</head>
<body>
@include('pdf._encabezado')

<div class="titulo-doc">PRESUPUESTO Y PLAN DE TRATAMIENTO · {{ $presupuesto->codigo }}</div>
<div class="subtitulo-doc">
    Emitido el {{ $presupuesto->fecha->format('d/m/Y') }} ·
    Válido hasta el {{ $presupuesto->vence_el->format('d/m/Y') }} ·
    Estado: {{ $presupuesto->estado_legible }}
</div>

<div class="bloque caja">
    <table>
        <tr>
            <td style="width:50%;">
                <div class="etiqueta-campo">Paciente</div>
                <strong>{{ $presupuesto->paciente->nombre_completo }}</strong><br>
                {{ $presupuesto->paciente->tipo_documento }} {{ $presupuesto->paciente->numero_documento }}
                @if ($presupuesto->paciente->telefono)<br>Tel. {{ $presupuesto->paciente->telefono }}@endif
            </td>
            <td style="width:50%;">
                <div class="etiqueta-campo">Cobertura</div>
                <strong>{{ $presupuesto->paciente->aseguradora?->nombre ?? 'Particular' }}</strong>
                @if ($presupuesto->paciente->aseguradora)
                    <br>Cubre el {{ number_format($presupuesto->paciente->aseguradora->porcentaje_cobertura, 0) }}%
                    @if ($presupuesto->paciente->numero_afiliado)
                        <br>Afiliado {{ $presupuesto->paciente->numero_afiliado }}
                    @endif
                @endif
                <br><span class="apagado">Profesional: {{ $presupuesto->doctor?->nombre_profesional ?? 'Por asignar' }}</span>
            </td>
        </tr>
    </table>
</div>

<table class="bloque">
    <thead>
        <tr>
            <th style="width:4%;">#</th>
            <th>Tratamiento</th>
            <th class="cen" style="width:8%;">Pieza</th>
            <th class="cen" style="width:10%;">Cara</th>
            <th class="cen" style="width:7%;">Cant.</th>
            <th class="der" style="width:12%;">Precio</th>
            <th class="der" style="width:12%;">Subtotal</th>
            <th class="cen" style="width:12%;">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($presupuesto->detalles as $detalle)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $detalle->descripcion }}</td>
                <td class="cen">{{ $detalle->pieza_dental ?: '—' }}</td>
                <td class="cen">{{ $detalle->cara ?: '—' }}</td>
                <td class="cen">{{ $detalle->cantidad }}</td>
                <td class="der">{{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="der">{{ number_format($detalle->subtotal, 2) }}</td>
                <td class="cen">{{ $detalle->estado }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <th colspan="6" class="der">SUBTOTAL</th>
            <th class="der">{{ number_format($presupuesto->subtotal, 2) }}</th>
            <th></th>
        </tr>
        <tr>
            <th colspan="6" class="der">DESCUENTO</th>
            <th class="der">−{{ number_format($presupuesto->descuento, 2) }}</th>
            <th></th>
        </tr>
        <tr>
            <th colspan="6" class="der">COBERTURA DEL SEGURO</th>
            <th class="der">−{{ number_format($presupuesto->cobertura_seguro, 2) }}</th>
            <th></th>
        </tr>
        <tr>
            <th colspan="6" class="der">TOTAL A PAGAR</th>
            <th class="der">{{ number_format($presupuesto->total, 2) }} {{ $clinica->divisa }}</th>
            <th></th>
        </tr>
    </tfoot>
</table>

@if ($presupuesto->notas)
    <div class="bloque caja">
        <div class="etiqueta-campo">Notas</div>
        {!! nl2br(e($presupuesto->notas)) !!}
    </div>
@endif

<div class="apagado" style="font-size: 8px;">
    Este presupuesto es una estimación basada en el diagnóstico actual. Los importes pueden variar si durante el
    tratamiento se detectan condiciones no visibles al momento de la evaluación. Precios expresados en {{ $clinica->divisa }}.
</div>

<table style="margin-top: 34px;">
    <tr>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">{{ $presupuesto->doctor?->nombre_profesional ?? 'Profesional tratante' }}</span>
        </td>
        <td class="cen" style="width:50%;">
            ______________________________<br>
            <span class="apagado">Acepto el plan · {{ $presupuesto->paciente->nombre_completo }}</span>
        </td>
    </tr>
</table>

<div class="pie">{{ $clinica->nombre }} · {{ $presupuesto->codigo }}</div>
</body>
</html>
