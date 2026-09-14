@extends('layouts.admin')

@section('pretitulo', 'Imagenología')
@section('titulo', 'Comparar estudios')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.estudios.paciente', $paciente) }}" class="btn btn-link">
            <i class="ti ti-arrow-left me-1"></i>Estudios del paciente
        </a>
    </div>
@endsection

@push('head')
<style>
    .os-comparar-marco {
        position: relative;
        height: 60vh;
        min-height: 320px;
        overflow: hidden;
        background: #111;
        border-radius: .5rem;
        cursor: grab;
        user-select: none;
        touch-action: none;
    }
    .os-comparar-marco.arrastrando { cursor: grabbing; }
    .os-comparar-capa {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        transform-origin: center center;
        will-change: transform;
        pointer-events: none;
    }
    .os-comparar-capa img { max-width: 100%; max-height: 100%; display: block; }
    .os-comparar-capa[data-superpuesta] { display: none; }
    .os-comparar-marco.con-superposicion .os-comparar-capa[data-superpuesta] { display: flex; }
    .os-comparar-etiqueta {
        position: absolute; top: .5rem; left: .5rem; z-index: 2;
        background: rgba(0,0,0,.6); color: #fff; padding: .15rem .5rem; border-radius: .25rem; font-size: .75rem;
    }
</style>
@endpush

@section('contenido')
@php
    $opciones = $estudios->map(fn ($e) => [
        'id' => $e->id,
        'titulo' => $e->titulo,
        'tipo' => $e->tipo_legible,
        'fecha' => $e->fecha_estudio->format('d/m/Y'),
        'url' => $e->url,
    ])->values();
@endphp

<div class="card mb-3">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
        <button type="button" class="btn btn-sm btn-outline-primary" data-comparar-sincronizar aria-pressed="false">
            <i class="ti ti-link me-1"></i>Sincronizar zoom
        </button>
        <button type="button" class="btn btn-sm" data-comparar-reiniciar>
            <i class="ti ti-maximize me-1"></i>Ajustar ambos
        </button>
        <div class="vr d-none d-md-block"></div>
        <label class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" data-comparar-superponer>
            <span class="form-check-label">Superponer B sobre A</span>
        </label>
        <label class="d-flex align-items-center gap-2 mb-0 small text-secondary">
            Opacidad
            <input type="range" class="form-range" min="0" max="100" value="50" style="width: 8rem;" data-comparar-opacidad disabled>
            <span data-comparar-opacidad-valor>50 %</span>
        </label>
    </div>
</div>

<div class="row g-3" data-comparador data-estudios='@json($opciones)'>
    @foreach (['a' => $a, 'b' => $b] as $lado => $estudio)
        <div class="col-lg-6">
            <div class="card h-100" data-panel="{{ $lado }}">
                <div class="card-header py-2">
                    <div class="d-flex align-items-center gap-2 w-100">
                        <span class="badge bg-{{ $lado === 'a' ? 'azure' : 'orange' }}">{{ strtoupper($lado) }}</span>
                        <select class="form-select form-select-sm" data-selector aria-label="Estudio {{ strtoupper($lado) }}">
                            @foreach ($estudios as $opcion)
                                <option value="{{ $opcion->id }}" @selected($opcion->id === $estudio->id)>
                                    {{ $opcion->fecha_estudio->format('d/m/Y') }} · {{ $opcion->titulo }} ({{ $opcion->tipo_legible }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="os-comparar-marco" data-marco>
                        <span class="os-comparar-etiqueta" data-etiqueta>{{ $estudio->fecha_estudio->format('d/m/Y') }} · {{ $estudio->titulo }}</span>
                        <div class="os-comparar-capa" data-capa>
                            <img src="{{ $estudio->url }}" alt="{{ $estudio->titulo }}" data-imagen draggable="false">
                        </div>
                        @if ($lado === 'a')
                            <div class="os-comparar-capa" data-capa-superpuesta data-superpuesta style="opacity: .5;">
                                <img src="{{ $b->url }}" alt="Superposición de {{ $b->titulo }}" data-imagen-superpuesta draggable="false">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-footer py-2 d-flex align-items-center gap-2">
                    <i class="ti ti-zoom-in text-secondary"></i>
                    <input type="range" class="form-range flex-fill" min="50" max="400" step="5" value="100" data-zoom aria-label="Zoom {{ strtoupper($lado) }}">
                    <span class="small text-secondary" style="width: 3.5rem;" data-zoom-valor>100 %</span>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="text-secondary small mt-2">
    Arrastra dentro de cada imagen para desplazarla; usa la rueda del ratón o el control deslizante para acercar.
</div>
@endsection

@push('scripts')
<script>
(() => {
    const raiz = document.querySelector('[data-comparador]');
    if (!raiz) return;

    let estudios = [];
    try { estudios = JSON.parse(raiz.dataset.estudios || '[]'); } catch { /* vacío */ }
    const porId = (id) => estudios.find((e) => String(e.id) === String(id));

    const botonSincronizar = document.querySelector('[data-comparar-sincronizar]');
    const superponer = document.querySelector('[data-comparar-superponer]');
    const opacidad = document.querySelector('[data-comparar-opacidad]');
    const opacidadValor = document.querySelector('[data-comparar-opacidad-valor]');
    let sincronizado = false;

    const paneles = {};

    raiz.querySelectorAll('[data-panel]').forEach((panel) => {
        const lado = panel.dataset.panel;
        const marco = panel.querySelector('[data-marco]');
        const capas = panel.querySelectorAll('[data-capa], [data-capa-superpuesta]');
        const zoom = panel.querySelector('[data-zoom]');
        const zoomValor = panel.querySelector('[data-zoom-valor]');
        const estado = { escala: 1, x: 0, y: 0 };

        const aplicar = () => {
            capas.forEach((capa) => { capa.style.transform = `translate(${estado.x}px, ${estado.y}px) scale(${estado.escala})`; });
            zoom.value = Math.round(estado.escala * 100);
            zoomValor.textContent = `${Math.round(estado.escala * 100)} %`;
        };

        const fijarZoom = (porcentaje, propagar = true) => {
            estado.escala = Math.min(4, Math.max(0.5, porcentaje / 100));
            aplicar();
            if (propagar && sincronizado) {
                Object.values(paneles).forEach((p) => { if (p !== api) p.fijarZoom(porcentaje, false); });
            }
        };

        const desplazar = (x, y, propagar = true) => {
            estado.x = x; estado.y = y;
            aplicar();
            if (propagar && sincronizado) {
                Object.values(paneles).forEach((p) => { if (p !== api) p.desplazar(x, y, false); });
            }
        };

        const reiniciar = () => { estado.escala = 1; estado.x = 0; estado.y = 0; aplicar(); };

        zoom.addEventListener('input', () => fijarZoom(Number(zoom.value)));

        marco.addEventListener('wheel', (e) => {
            e.preventDefault();
            fijarZoom(estado.escala * 100 + (e.deltaY < 0 ? 10 : -10));
        }, { passive: false });

        // Arrastre para desplazar la imagen.
        let arrastre = null;
        marco.addEventListener('pointerdown', (e) => {
            arrastre = { px: e.clientX, py: e.clientY, x: estado.x, y: estado.y };
            marco.classList.add('arrastrando');
            marco.setPointerCapture(e.pointerId);
        });
        marco.addEventListener('pointermove', (e) => {
            if (!arrastre) return;
            desplazar(arrastre.x + (e.clientX - arrastre.px), arrastre.y + (e.clientY - arrastre.py));
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach((evento) => marco.addEventListener(evento, () => {
            arrastre = null;
            marco.classList.remove('arrastrando');
        }));

        const api = { lado, panel, marco, estado, fijarZoom, desplazar, reiniciar, aplicar };
        paneles[lado] = api;
    });

    // Cambio de estudio en cada lado sin recargar la página.
    function actualizarUrl() {
        const url = new URL(window.location.href);
        raiz.querySelectorAll('[data-panel]').forEach((panel) => {
            url.searchParams.set(panel.dataset.panel, panel.querySelector('[data-selector]').value);
        });
        history.replaceState(null, '', url.toString());
    }

    raiz.querySelectorAll('[data-panel]').forEach((panel) => {
        const selector = panel.querySelector('[data-selector]');
        selector.addEventListener('change', () => {
            const estudio = porId(selector.value);
            if (!estudio) return;
            panel.querySelector('[data-imagen]').src = estudio.url;
            panel.querySelector('[data-imagen]').alt = estudio.titulo;
            panel.querySelector('[data-etiqueta]').textContent = `${estudio.fecha} · ${estudio.titulo}`;
            paneles[panel.dataset.panel].reiniciar();

            if (panel.dataset.panel === 'b') {
                const superpuesta = raiz.querySelector('[data-imagen-superpuesta]');
                if (superpuesta) superpuesta.src = estudio.url;
            }
            actualizarUrl();
        });
    });

    botonSincronizar.addEventListener('click', () => {
        sincronizado = !sincronizado;
        botonSincronizar.classList.toggle('btn-primary', sincronizado);
        botonSincronizar.classList.toggle('btn-outline-primary', !sincronizado);
        botonSincronizar.setAttribute('aria-pressed', String(sincronizado));
        if (sincronizado) {
            const a = paneles.a.estado;
            paneles.b.fijarZoom(a.escala * 100, false);
            paneles.b.desplazar(a.x, a.y, false);
        }
    });

    document.querySelector('[data-comparar-reiniciar]').addEventListener('click', () => {
        Object.values(paneles).forEach((p) => p.reiniciar());
    });

    // Superposición de B sobre A con opacidad ajustable.
    const capaSuperpuesta = raiz.querySelector('[data-capa-superpuesta]');
    superponer.addEventListener('change', () => {
        paneles.a.marco.classList.toggle('con-superposicion', superponer.checked);
        opacidad.disabled = !superponer.checked;
        paneles.a.aplicar();
    });
    opacidad.addEventListener('input', () => {
        if (capaSuperpuesta) capaSuperpuesta.style.opacity = String(Number(opacidad.value) / 100);
        opacidadValor.textContent = `${opacidad.value} %`;
    });
})();
</script>
@endpush
