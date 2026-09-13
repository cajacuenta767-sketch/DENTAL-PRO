@props(['icono' => 'ti ti-inbox', 'titulo' => 'Sin registros', 'texto' => null])

<div class="empty">
    <div class="empty-icon"><i class="{{ $icono }} fs-1"></i></div>
    <p class="empty-title">{{ $titulo }}</p>
    @if ($texto)
        <p class="empty-subtitle text-secondary">{{ $texto }}</p>
    @endif
    @if (! $slot->isEmpty())
        <div class="empty-action">{{ $slot }}</div>
    @endif
</div>
