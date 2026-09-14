@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Panorámicas y Estudios')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i>Ficha del paciente
        </a>
        @can('imagenologia.crear')
            <a href="{{ route('admin.estudios.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary">
                <i class="ti ti-upload me-1"></i>Cargar Estudio
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'panoramicas'])

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-secondary small text-uppercase me-2">Tipo de estudio:</span>
            <a href="{{ route('admin.estudios.paciente', $paciente) }}"
               class="btn btn-sm {{ request('tipo') ? 'btn-outline-secondary' : 'btn-primary' }}">Todos</a>
            @foreach (\App\Models\EstudioImagen::TIPOS as $clave => $etiqueta)
                <a href="{{ route('admin.estudios.paciente', [$paciente, 'tipo' => $clave]) }}"
                   class="btn btn-sm {{ request('tipo') === $clave ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $etiqueta }}
                </a>
            @endforeach
        </form>
    </div>
</div>

@if ($estudios->isEmpty())
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-photo-off" titulo="Sin estudios cargados"
                 texto="Sube la primera radiografía o fotografía clínica de {{ $paciente->nombres }}.">
            @can('imagenologia.crear')
                <a href="{{ route('admin.estudios.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-primary">
                    <i class="ti ti-upload me-1"></i>Cargar estudio
                </a>
            @endcan
        </x-vacio>
    </div></div>
@else
    @php($visualizables = $estudios->filter->es_visualizable)
    @if ($visualizables->count() >= 2)
        <div class="card mb-3" data-comparar-barra>
            <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                <i class="ti ti-git-compare text-secondary"></i>
                <span class="small text-secondary">
                    Marca dos imágenes para compararlas lado a lado.
                    <span class="fw-medium" data-comparar-contador>0 de 2 seleccionadas</span>
                </span>
                <a class="btn btn-sm btn-primary ms-auto disabled" aria-disabled="true" data-comparar-enlace
                   data-base="{{ route('admin.estudios.comparar') }}">
                    <i class="ti ti-git-compare me-1"></i>Comparar
                </a>
            </div>
        </div>
    @endif

    <div class="row row-cards">
        @foreach ($estudios as $estudio)
            <div class="col-md-6 col-xl-4">
                @if ($estudio->es_visualizable && $visualizables->count() >= 2)
                    <label class="form-check mb-1 small">
                        <input type="checkbox" class="form-check-input" value="{{ $estudio->id }}" data-comparar-check>
                        <span class="form-check-label">Seleccionar para comparar</span>
                    </label>
                @endif
                <x-tarjeta-estudio :estudio="$estudio" />
            </div>
        @endforeach
    </div>
@endif

@include('componentes.visor-estudio')
@endsection

@push('scripts')
<script>
(() => {
    const enlace = document.querySelector('[data-comparar-enlace]');
    const contador = document.querySelector('[data-comparar-contador]');
    const casillas = Array.from(document.querySelectorAll('[data-comparar-check]'));
    if (!enlace || casillas.length === 0) return;

    function refrescar() {
        const marcadas = casillas.filter((c) => c.checked);
        const listo = marcadas.length === 2;

        // Solo se comparan dos estudios: al marcar el segundo se bloquea el resto.
        casillas.forEach((c) => { c.disabled = listo && !c.checked; });

        contador.textContent = `${marcadas.length} de 2 seleccionadas`;
        enlace.classList.toggle('disabled', !listo);
        enlace.setAttribute('aria-disabled', String(!listo));
        enlace.href = listo ? `${enlace.dataset.base}?a=${marcadas[0].value}&b=${marcadas[1].value}` : '#';
    }

    casillas.forEach((c) => c.addEventListener('change', refrescar));
    refrescar();
})();
</script>
@endpush
