@php
    $estados = \App\Models\Odontograma::ESTADOS;
    $caras = $pieza['caras'] ?? array_fill_keys(\App\Models\Odontograma::CARAS, 'sano');
    $estadoPieza = $pieza['estado'] ?? 'sano';
    $color = fn ($clave) => $estados[$clave]['color'] ?? '#ffffff';
@endphp

<div class="pieza {{ $estadoPieza === 'ausente' ? 'pieza-ausente' : '' }}"
     data-pieza="{{ $numero }}"
     data-estado="{{ $estadoPieza }}">
    @if ($arcada === 'superior')
        <div class="pieza-numero">{{ $numero }}</div>
    @endif

    {{-- Cinco caras: vestibular, lingual, mesial, distal y oclusal al centro. --}}
    <svg class="pieza-svg" viewBox="0 0 40 40" width="40" height="40" role="img"
         aria-label="Pieza dental {{ $numero }}">
        <polygon class="pieza-cara" data-cara="vestibular" points="0,0 40,0 30,10 10,10"
                 fill="{{ $color($caras['vestibular'] ?? 'sano') }}"></polygon>
        <polygon class="pieza-cara" data-cara="distal" points="40,0 40,40 30,30 30,10"
                 fill="{{ $color($caras['distal'] ?? 'sano') }}"></polygon>
        <polygon class="pieza-cara" data-cara="lingual" points="0,40 10,30 30,30 40,40"
                 fill="{{ $color($caras['lingual'] ?? 'sano') }}"></polygon>
        <polygon class="pieza-cara" data-cara="mesial" points="0,0 10,10 10,30 0,40"
                 fill="{{ $color($caras['mesial'] ?? 'sano') }}"></polygon>
        <rect class="pieza-cara" data-cara="oclusal" x="10" y="10" width="20" height="20"
              fill="{{ $color($caras['oclusal'] ?? 'sano') }}"></rect>
    </svg>

    @if ($arcada === 'inferior')
        <div class="pieza-numero">{{ $numero }}</div>
    @endif
</div>
