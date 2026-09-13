@props(['nombre', 'etiqueta', 'requerido' => false, 'ayuda' => null])

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label class="form-label {{ $requerido ? 'required' : '' }}" for="{{ $nombre }}">{{ $etiqueta }}</label>
    {{ $slot }}
    @error($nombre)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($ayuda)
        <small class="form-hint">{{ $ayuda }}</small>
    @endif
</div>
