@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Odontogramas')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.show', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Ficha del paciente</a>
        @can('odontogramas.crear')
            @if ($odontogramas->isNotEmpty())
                <a href="{{ route('admin.odontogramas.create', ['paciente' => $paciente, 'tipo' => $odontogramas->first()->tipo, 'desde' => 'ultimo']) }}"
                   class="btn btn-outline-primary" title="Nuevo odontograma a partir del último registrado">
                    <i class="ti ti-copy me-1"></i>Control desde el último
                </a>
            @endif
            <a href="{{ route('admin.odontogramas.create', ['paciente' => $paciente, 'tipo' => 'INFANTIL']) }}" class="btn btn-outline-primary">
                <i class="ti ti-mood-kid me-1"></i>Nuevo infantil
            </a>
            <a href="{{ route('admin.odontogramas.create', $paciente) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nuevo Odontograma
            </a>
        @endcan
    </div>
@endsection

@push('head')
<style>
    /* Piezas que cambiaron respecto al odontograma anterior: anillo naranja continuo. */
    .pieza-cambiada .pieza-anillo { opacity: 1; stroke: #f76707; stroke-dasharray: 0; stroke-width: 2.5; }
    .os-comparacion .odontograma-arcada-contenedor { max-width: 100%; }
</style>
@endpush

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'odontograma'])

@forelse ($odontogramas as $odontograma)
    <div class="card mb-3">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">
                    {{ $odontograma->fecha->format('d/m/Y') }}
                    <span class="badge bg-{{ $odontograma->tipo === 'INFANTIL' ? 'pink' : 'azure' }}-lt ms-2">{{ $odontograma->tipo }}</span>
                </h3>
                <div class="text-secondary small">
                    {{ $odontograma->doctor?->nombre_profesional ?? 'Sin doctor asignado' }}
                    @if ($odontograma->cita) · cita {{ $odontograma->cita->token }} @endif
                </div>
            </div>
            <div class="btn-list ms-auto flex-nowrap">
                @php($resumenCapas = $odontograma->resumenPorCapa())
                <span class="badge bg-red-lt align-self-center" title="Piezas con hallazgo por tratar">
                    {{ $resumenCapas['evaluacion'] }} en evaluación
                </span>
                <span class="badge bg-blue-lt align-self-center" title="Piezas ya tratadas">
                    {{ $resumenCapas['ejecucion'] }} en ejecución
                </span>
                @can('presupuestos.crear')
                    <a href="{{ route('admin.presupuestos.create', ['paciente_id' => $paciente->id, 'odontograma_id' => $odontograma->id]) }}"
                       class="btn btn-sm btn-outline-success" title="Generar presupuesto con estos hallazgos">
                        <i class="ti ti-file-invoice"></i>
                    </a>
                @endcan
                @can('odontogramas.editar')
                    <a href="{{ route('admin.odontogramas.edit', $odontograma) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                        <i class="ti ti-edit"></i>
                    </a>
                @endcan
                @can('odontogramas.eliminar')
                    <form method="POST" action="{{ route('admin.odontogramas.destroy', $odontograma) }}"
                          data-confirmar="¿Eliminar este odontograma?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-start">
                <div class="col-lg-7">
                    @include('componentes.odontograma-arcada', [
                        'tipo' => $odontograma->tipo,
                        'piezas' => $odontograma->piezas ?? [],
                        'editable' => false,
                    ])
                </div>
                <div class="col-lg-5">
                    @php($urgentes = $odontograma->piezas_urgentes)
                    @if ($urgentes)
                        <div class="alert alert-danger py-2 mb-2">
                            <i class="ti ti-alert-triangle me-1"></i>Urgentes: piezas {{ implode(', ', $urgentes) }}
                        </div>
                    @endif
                    <div class="text-secondary small text-uppercase mb-1">Resumen de diagnóstico</div>
                    <pre class="small mb-2" style="white-space: pre-wrap;">{{ $odontograma->resumenTexto() }}</pre>
                    <details>
                        <summary class="small text-secondary">Ver cuadrícula clásica</summary>
                        <div class="mt-2">
                            @include('componentes.odontograma', [
                                'tipo' => $odontograma->tipo,
                                'piezas' => $odontograma->piezas ?? [],
                                'editable' => false,
                            ])
                        </div>
                    </details>
                </div>
            </div>

            @if ($odontograma->observaciones)
                <div class="mt-3 border-top pt-3">
                    <div class="text-secondary small text-uppercase">Observaciones</div>
                    <div>{{ $odontograma->observaciones }}</div>
                </div>
            @endif

            @php($anterior = $anteriores[$odontograma->id] ?? null)
            @if ($anterior)
                @php($cambios = $odontograma->cambiosRespectoA($anterior->piezas ?? []))
                @php($piezasCambiadas = collect($cambios)->pluck('pieza')->unique()->values())
                <div class="mt-3 border-top pt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                            data-bs-target="#comparacion-{{ $odontograma->id }}" aria-expanded="false"
                            aria-controls="comparacion-{{ $odontograma->id }}">
                        <i class="ti ti-git-compare me-1"></i>Comparar con el anterior ({{ $anterior->fecha->format('d/m/Y') }})
                        @if ($piezasCambiadas->isNotEmpty())
                            <span class="badge bg-orange-lt ms-1">{{ $piezasCambiadas->count() }} {{ $piezasCambiadas->count() === 1 ? 'pieza' : 'piezas' }}</span>
                        @endif
                    </button>

                    <div class="collapse mt-3 os-comparacion" id="comparacion-{{ $odontograma->id }}"
                         data-comparacion data-cambios='@json($piezasCambiadas)'>
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="text-secondary small text-uppercase mb-1">Anterior · {{ $anterior->fecha->format('d/m/Y') }}</div>
                                @include('componentes.odontograma-arcada', [
                                    'tipo' => $anterior->tipo,
                                    'piezas' => $anterior->piezas ?? [],
                                    'editable' => false,
                                ])
                            </div>
                            <div class="col-lg-5" data-comparacion-actual>
                                <div class="text-secondary small text-uppercase mb-1">Actual · {{ $odontograma->fecha->format('d/m/Y') }}</div>
                                @include('componentes.odontograma-arcada', [
                                    'tipo' => $odontograma->tipo,
                                    'piezas' => $odontograma->piezas ?? [],
                                    'editable' => false,
                                ])
                            </div>
                            <div class="col-lg-2">
                                <div class="text-secondary small text-uppercase mb-1">Cambios</div>
                                <ul class="list-unstyled mb-0 small">
                                    @forelse ($cambios as $cambio)
                                        @php($donde = $cambio['cara'] ? " ({$cambio['cara']})" : '')
                                        <li class="mb-1">
                                            <strong>Pieza {{ $cambio['pieza'] }}</strong>:
                                            @if ($cambio['antes'] === 'sano')
                                                <span class="badge bg-red-lt">Nuevo</span>
                                                {{ \App\Models\Odontograma::ESTADOS[$cambio['despues']]['etiqueta'] ?? $cambio['despues'] }}{{ $donde }}
                                            @elseif ($cambio['despues'] === 'sano')
                                                <span class="badge bg-green-lt">Resuelto</span>
                                                {{ \App\Models\Odontograma::ESTADOS[$cambio['antes']]['etiqueta'] ?? $cambio['antes'] }}{{ $donde }}
                                            @else
                                                <span class="badge bg-azure-lt">Cambió</span>
                                                {{ \App\Models\Odontograma::ESTADOS[$cambio['antes']]['etiqueta'] ?? $cambio['antes'] }}
                                                →
                                                {{ \App\Models\Odontograma::ESTADOS[$cambio['despues']]['etiqueta'] ?? $cambio['despues'] }}{{ $donde }}
                                            @endif
                                        </li>
                                    @empty
                                        <li class="text-secondary">Sin cambios.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="card-body">
        <x-vacio icono="ti ti-dental-off" titulo="Sin odontogramas"
                 texto="Registra el primer odontograma de {{ $paciente->nombres }}.">
            @can('odontogramas.crear')
                <a href="{{ route('admin.odontogramas.create', $paciente) }}" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>Nuevo odontograma
                </a>
            @endcan
        </x-vacio>
    </div></div>
@endforelse

@if ($odontogramas->hasPages())
    {{ $odontogramas->links() }}
@endif
@endsection

@push('scripts')
<script>
(() => {
    // Resalta en la arcada "actual" las piezas que cambiaron respecto al anterior.
    // El SVG se monta solo en DOMContentLoaded (marca data-montada), así que
    // esperamos a esa marca antes de aplicar la clase.
    document.querySelectorAll('[data-comparacion]').forEach((bloque) => {
        let cambios = [];
        try { cambios = JSON.parse(bloque.dataset.cambios || '[]'); } catch { /* vacío */ }
        if (!Array.isArray(cambios) || cambios.length === 0) return;

        const arcada = bloque.querySelector('[data-comparacion-actual] [data-arcada]');
        if (!arcada) return;

        const resaltar = () => {
            cambios.forEach((numero) => {
                arcada.querySelectorAll(`[data-pieza="${numero}"]`).forEach((nodo) => nodo.classList.add('pieza-cambiada'));
            });
        };

        if (arcada.dataset.montada) {
            resaltar();
            return;
        }

        const observador = new MutationObserver(() => {
            if (!arcada.dataset.montada) return;
            observador.disconnect();
            resaltar();
        });
        observador.observe(arcada, { attributes: true, attributeFilter: ['data-montada'] });

        // Respaldo por si el atributo se fija antes de observar o los assets tardan.
        let intentos = 0;
        const reloj = setInterval(() => {
            intentos++;
            if (arcada.dataset.montada || intentos > 100) {
                clearInterval(reloj);
                if (arcada.dataset.montada) { observador.disconnect(); resaltar(); }
            }
        }, 100);
    });
})();
</script>
@endpush
