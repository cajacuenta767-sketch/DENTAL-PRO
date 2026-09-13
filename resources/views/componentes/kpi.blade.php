@props([
    'titulo',
    'valor',
    'icono' => 'ti ti-chart-bar',
    'color' => 'primary',
    'pie' => null,
])

<div class="card os-kpi" style="border-left-color: var(--tblr-{{ $color }})">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="os-kpi-icon bg-{{ $color }}-lt text-{{ $color }}">
            <i class="{{ $icono }}"></i>
        </div>
        <div class="flex-fill">
            <div class="text-secondary small">{{ $titulo }}</div>
            <div class="h1 mb-0">{{ $valor }}</div>
            @if ($pie)
                <div class="small text-secondary mt-1">{!! $pie !!}</div>
            @endif
        </div>
    </div>
</div>
