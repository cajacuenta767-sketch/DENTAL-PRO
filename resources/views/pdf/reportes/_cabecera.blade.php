@include('pdf._encabezado')
<div class="titulo-doc">{{ $titulo }}</div>
<div class="subtitulo-doc">
    Período: {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
</div>
