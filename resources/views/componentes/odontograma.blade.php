@props(['tipo' => 'ADULTO', 'piezas' => [], 'editable' => true])

@php
    $cuadrantes = \App\Models\Odontograma::cuadrantes($tipo);
    $estados = \App\Models\Odontograma::ESTADOS;
@endphp

<div class="odontograma" data-odontograma data-editable="{{ $editable ? '1' : '0' }}">
    {{-- Arcada superior --}}
    <div class="odontograma-arcada">
        @foreach (['superior_derecho', 'superior_izquierdo'] as $cuadrante)
            <div class="odontograma-cuadrante">
                @foreach ($cuadrantes[$cuadrante] as $numero)
                    @include('componentes.pieza-dental', [
                        'numero' => $numero,
                        'pieza' => $piezas[(string) $numero] ?? null,
                        'arcada' => 'superior',
                    ])
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Arcada inferior --}}
    <div class="odontograma-arcada">
        @foreach (['inferior_derecho', 'inferior_izquierdo'] as $cuadrante)
            <div class="odontograma-cuadrante">
                @foreach ($cuadrantes[$cuadrante] as $numero)
                    @include('componentes.pieza-dental', [
                        'numero' => $numero,
                        'pieza' => $piezas[(string) $numero] ?? null,
                        'arcada' => 'inferior',
                    ])
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Leyenda --}}
    <div class="odontograma-leyenda text-center">
        @foreach ($estados as $clave => $estado)
            <span class="leyenda-item">
                <span class="leyenda-muestra" style="background-color: {{ $estado['color'] }}"></span>
                {{ $estado['etiqueta'] }}
            </span>
        @endforeach
    </div>
</div>
