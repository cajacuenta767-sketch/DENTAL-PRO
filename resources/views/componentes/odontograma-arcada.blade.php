@props(['tipo' => 'ADULTO', 'piezas' => [], 'editable' => true, 'tresD' => false])

{{--
    Arcada dentaria vista desde arriba. El SVG lo dibuja resources/js/odontograma.js:
    en modo lectura se monta solo (data-piezas), en el formulario lo monta el
    script de la página y comparte el mapa con la cuadrícula.
--}}
<div class="odontograma-arcada-contenedor {{ $tresD ? 'odontograma-3d' : '' }}"
     data-arcada
     data-tipo="{{ $tipo }}"
     data-editable="{{ $editable ? '1' : '0' }}"
     @unless ($editable)
         data-piezas="{{ json_encode($piezas ?? []) }}"
         data-estados="{{ json_encode(\App\Models\Odontograma::ESTADOS) }}"
     @endunless>
    @if ($editable)
        <div class="text-secondary small text-center py-5">Cargando la arcada…</div>
    @else
        {{-- Respaldo sin JavaScript (o con assets sin recompilar): la cuadrícula ya pintada. --}}
        @include('componentes.odontograma', ['tipo' => $tipo, 'piezas' => $piezas ?? [], 'editable' => false])
    @endif
</div>
