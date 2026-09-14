@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $odontograma->exists ? 'Editar Odontograma' : 'Nuevo Odontograma')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    @if (! $odontograma->exists && $anterior)
        <a href="{{ route('admin.odontogramas.create', ['paciente' => $paciente, 'tipo' => $odontograma->tipo, 'desde' => 'ultimo']) }}"
           class="btn btn-outline-primary" title="Copia los hallazgos del odontograma del {{ $anterior->fecha->format('d/m/Y') }}">
            <i class="ti ti-copy me-1"></i>Partir del último ({{ $anterior->fecha->format('d/m/Y') }})
        </a>
    @endif
@endsection

@section('contenido')
@php
    $estados = \App\Models\Odontograma::ESTADOS;
    $atajos = array_values(array_keys(array_filter($estados, fn ($e, $k) => $k !== 'sano', ARRAY_FILTER_USE_BOTH)));
@endphp

<form method="POST" id="form-odontograma"
      action="{{ $odontograma->exists ? route('admin.odontogramas.update', $odontograma) : route('admin.odontogramas.store', $paciente) }}">
    @csrf
    @if ($odontograma->exists) @method('PUT') @endif

    <input type="hidden" name="piezas" id="piezas" value="{{ old('piezas', json_encode($odontograma->piezas ?? [])) }}">
    <input type="hidden" name="tipo" id="tipo" value="{{ old('tipo', $odontograma->tipo) }}">

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                {{-- Vista, capa y dentición --}}
                <div class="card-header flex-wrap gap-2">
                    <div class="btn-group" role="group" aria-label="Vista">
                        <button type="button" class="btn btn-sm active" data-os-vista="arcada" title="Arcada vista desde arriba">
                            <i class="ti ti-dental me-1"></i>Arcada
                        </button>
                        <button type="button" class="btn btn-sm" data-os-vista="3d" title="Arcada en perspectiva">
                            <i class="ti ti-3d-cube-sphere me-1"></i>3D
                        </button>
                        <button type="button" class="btn btn-sm" data-os-vista="cuadricula" title="Cuadrícula clásica">
                            <i class="ti ti-layout-grid me-1"></i>Cuadrícula
                        </button>
                    </div>

                    <div class="btn-group" role="group" aria-label="Capa visible">
                        <button type="button" class="btn btn-sm active" data-os-capa="todos">Todos</button>
                        <button type="button" class="btn btn-sm" data-os-capa="evaluacion">Evaluación</button>
                        <button type="button" class="btn btn-sm" data-os-capa="ejecucion">Ejecución</button>
                    </div>

                    <div class="btn-group ms-auto" role="group" aria-label="Dentición">
                        <button type="button" class="btn btn-sm {{ old('tipo', $odontograma->tipo) === 'ADULTO' ? 'active' : '' }}"
                                data-os-denticion="ADULTO">Permanente</button>
                        <button type="button" class="btn btn-sm {{ old('tipo', $odontograma->tipo) === 'INFANTIL' ? 'active' : '' }}"
                                data-os-denticion="INFANTIL">Temporal</button>
                    </div>
                </div>

                {{-- Paleta de hallazgos con atajos de teclado --}}
                <div class="card-body border-bottom py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-secondary small text-uppercase me-1">Hallazgo:</span>
                        @foreach ($estados as $clave => $estado)
                            @continue($clave === 'sano')
                            @php($indice = array_search($clave, $atajos, true))
                            <button type="button" class="btn btn-sm os-hallazgo {{ $clave === 'caries' ? 'active' : '' }}"
                                    data-hallazgo="{{ $clave }}" data-capa="{{ $estado['capa'] }}"
                                    style="border-color: {{ $estado['color'] }};"
                                    title="Atajo: tecla {{ $indice + 1 }}">
                                <span class="leyenda-muestra me-1" style="background-color: {{ $estado['color'] }}"></span>
                                {{ $estado['etiqueta'] }}
                                @if ($indice < 9)<kbd class="os-atajo">{{ $indice + 1 }}</kbd>@endif
                            </button>
                        @endforeach
                        <button type="button" class="btn btn-sm os-hallazgo" data-hallazgo="sano" data-capa="ninguna" title="Atajo: tecla 0">
                            <i class="ti ti-eraser me-1"></i>Borrar <kbd class="os-atajo">0</kbd>
                        </button>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                        <div class="btn-group" role="group" aria-label="Modo de marcado">
                            <button type="button" class="btn btn-sm active" data-os-modo="cara" title="Atajo: C">
                                <i class="ti ti-square me-1"></i>Pintar cara
                            </button>
                            <button type="button" class="btn btn-sm" data-os-modo="pieza" title="Atajo: P">
                                <i class="ti ti-dental me-1"></i>Pieza completa
                            </button>
                        </div>

                        <div class="btn-group" role="group" aria-label="Historial">
                            <button type="button" class="btn btn-sm" data-os-deshacer disabled title="Deshacer (Ctrl+Z)">
                                <i class="ti ti-arrow-back-up"></i>
                            </button>
                            <button type="button" class="btn btn-sm" data-os-rehacer disabled title="Rehacer (Ctrl+Y)">
                                <i class="ti ti-arrow-forward-up"></i>
                            </button>
                        </div>

                        <span class="text-secondary small d-none d-lg-inline">
                            <i class="ti ti-keyboard me-1"></i>1-9 hallazgo · ← → pieza · Esc quitar selección
                        </span>

                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-os-limpiar>
                            <i class="ti ti-eraser me-1"></i>Limpiar todo
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    {{-- Arcada desde arriba (también sirve para el modo 3D) --}}
                    <div data-os-vista-panel="arcada">
                        @include('componentes.odontograma-arcada', [
                            'tipo' => old('tipo', $odontograma->tipo),
                            'piezas' => [],
                            'editable' => true,
                        ])
                    </div>

                    {{-- Cuadrícula clásica, oculta por defecto --}}
                    <div data-os-vista-panel="cuadricula" class="d-none">
                        @include('componentes.odontograma', [
                            'tipo' => old('tipo', $odontograma->tipo),
                            'piezas' => $odontograma->piezas ?? [],
                            'editable' => true,
                        ])
                    </div>

                    {{-- Leyenda clicable: resalta las piezas con ese hallazgo --}}
                    <div class="odontograma-leyenda text-center mt-3" data-os-leyenda>
                        @foreach ($estados as $clave => $estado)
                            @continue($clave === 'sano')
                            <button type="button" class="btn btn-sm btn-ghost-secondary leyenda-item py-0 px-1" data-resaltar="{{ $clave }}"
                                    title="Resaltar piezas con {{ mb_strtolower($estado['etiqueta']) }}">
                                <span class="leyenda-muestra" style="background-color: {{ $estado['color'] }}"></span>
                                {{ $estado['etiqueta'] }} <span class="badge bg-secondary-lt ms-1" data-conteo="{{ $clave }}">0</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Resumen de hallazgos y cambios respecto al anterior --}}
            <div class="row g-3 mt-0">
                <div class="col-md-{{ $anterior ? '7' : '12' }}">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="ti ti-file-description me-2"></i>Resumen de diagnóstico</h3>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-os-copiar-resumen>
                                <i class="ti ti-clipboard-copy me-1"></i>Copiar
                            </button>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0 small" style="white-space: pre-wrap;" data-os-resumen>Sin hallazgos.</pre>
                        </div>
                    </div>
                </div>
                @if ($anterior)
                    <div class="col-md-5">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="ti ti-git-compare me-2"></i>Cambios desde el {{ $anterior->fecha->format('d/m/Y') }}</h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0 small" data-os-cambios>
                                    <li class="text-secondary">Sin cambios.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            {{-- Panel de la pieza seleccionada --}}
            <div class="card mb-3" id="panel-pieza">
                <div class="card-header">
                    <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-os-pieza-anterior title="Pieza anterior (←)">
                        <i class="ti ti-chevron-left"></i>
                    </button>
                    <h3 class="card-title mx-2">
                        <i class="ti ti-dental me-1"></i>Pieza <span data-panel-numero>—</span>
                        <span class="badge bg-red-lt ms-2 d-none" data-panel-urgente-badge>Urgente</span>
                    </h3>
                    <button type="button" class="btn btn-sm btn-icon btn-ghost-secondary" data-os-pieza-siguiente title="Pieza siguiente (→)">
                        <i class="ti ti-chevron-right"></i>
                    </button>
                    <div class="ms-auto d-none" data-panel-cambio></div>
                </div>

                <div class="card-body" data-panel-vacio>
                    <div class="text-secondary small text-center py-3">
                        Toca una pieza en la arcada para ver sus caras ampliadas y marcar lo que tiene.
                    </div>
                </div>

                <div class="card-body d-none" data-panel-contenido>
                    {{-- Pieza ampliada con sus cinco caras --}}
                    <svg class="pieza-detalle" viewBox="0 0 200 200" role="img" aria-label="Detalle de la pieza">
                        <polygon class="pieza-cara" data-detalle-cara="vestibular" points="20,20 180,20 140,60 60,60" fill="#fff"></polygon>
                        <polygon class="pieza-cara" data-detalle-cara="distal" points="180,20 180,180 140,140 140,60" fill="#fff"></polygon>
                        <polygon class="pieza-cara" data-detalle-cara="lingual" points="20,180 60,140 140,140 180,180" fill="#fff"></polygon>
                        <polygon class="pieza-cara" data-detalle-cara="mesial" points="20,20 60,60 60,140 20,180" fill="#fff"></polygon>
                        <rect class="pieza-cara" data-detalle-cara="oclusal" x="60" y="60" width="80" height="80" fill="#fff"></rect>
                        <text x="100" y="45" class="detalle-etiqueta">VESTIBULAR</text>
                        <text x="100" y="165" class="detalle-etiqueta">LINGUAL</text>
                        <text x="40" y="104" class="detalle-etiqueta" transform="rotate(-90 40 104)">MESIAL</text>
                        <text x="160" y="104" class="detalle-etiqueta" transform="rotate(90 160 104)">DISTAL</text>
                        <text x="100" y="104" class="detalle-etiqueta">OCLUSAL</text>
                    </svg>
                    <div class="text-secondary small text-center mb-3">Toca una cara para aplicarle el hallazgo elegido.</div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase mb-1">Pieza completa</div>
                        <div class="d-flex flex-wrap gap-1" data-panel-chips>
                            @foreach ($estados as $clave => $estado)
                                @continue($clave === 'sano')
                                <button type="button" class="btn btn-sm btn-outline-secondary os-chip-hallazgo" data-chip="{{ $clave }}"
                                        style="border-color: {{ $estado['color'] }}; --chip: {{ $estado['color'] }};">
                                    {{ $estado['etiqueta'] }}
                                </button>
                            @endforeach
                            <button type="button" class="btn btn-sm btn-outline-success os-chip-hallazgo" data-chip="sano">
                                <i class="ti ti-check me-1"></i>Sana
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase mb-1">Caras</div>
                        <div class="list-group list-group-flush" data-panel-caras></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small text-secondary text-uppercase mb-1">Movilidad</label>
                            <select class="form-select form-select-sm" data-panel-movilidad>
                                @foreach (\App\Models\Odontograma::MOVILIDAD as $grado => $etiqueta)
                                    <option value="{{ $grado }}">{{ $etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5 d-flex align-items-end">
                            <label class="form-check form-switch mb-2">
                                <input type="checkbox" class="form-check-input" data-panel-urgente>
                                <span class="form-check-label small">Urgente</span>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase mb-1">Nota de la pieza</div>
                        <textarea class="form-control form-control-sm" rows="2" maxlength="255"
                                  data-panel-nota placeholder="Observación breve"></textarea>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-os-copiar-contralateral
                                title="Copia los hallazgos a la pieza del lado opuesto">
                            <i class="ti ti-arrows-left-right me-1"></i>Copiar a la contralateral
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-os-copiar-antagonista
                                title="Copia los hallazgos a la pieza antagonista">
                            <i class="ti ti-arrows-up-down me-1"></i>A la antagonista
                        </button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del registro</h3></div>
                <div class="card-body">
                    <x-campo nombre="fecha" etiqueta="Fecha" requerido>
                        <input type="date" id="fecha" name="fecha" class="form-control" max="{{ now()->toDateString() }}"
                               value="{{ old('fecha', $odontograma->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor">
                        <select id="doctor_id" name="doctor_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $odontograma->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $odontograma->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3">{{ old('observaciones', $odontograma->observaciones) }}</textarea>
                    </x-campo>

                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="card card-sm"><div class="card-body py-2">
                                <div class="h2 mb-0 text-danger" id="contador-evaluacion">0</div>
                                <div class="text-secondary" style="font-size: .7rem;">EVALUACIÓN</div>
                            </div></div>
                        </div>
                        <div class="col-4">
                            <div class="card card-sm"><div class="card-body py-2">
                                <div class="h2 mb-0 text-primary" id="contador-ejecucion">0</div>
                                <div class="text-secondary" style="font-size: .7rem;">EJECUCIÓN</div>
                            </div></div>
                        </div>
                        <div class="col-4">
                            <div class="card card-sm"><div class="card-body py-2">
                                <div class="h2 mb-0 text-warning" id="contador-urgentes">0</div>
                                <div class="text-secondary" style="font-size: .7rem;">URGENTES</div>
                            </div></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.odontogramas.index', $paciente) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ESTADOS = @json($estados);
    const CARAS = @json(\App\Models\Odontograma::CARAS);
    const ATAJOS = @json($atajos);
    const ANTERIOR = @json($anterior?->piezas);
    const { montarArcada, pintarMapa } = window.OdontoSuite;

    const campoPiezas = document.getElementById('piezas');
    const campoTipo = document.getElementById('tipo');
    const contenedorArcada = document.querySelector('[data-arcada]');
    const raiz = document.getElementById('form-odontograma');

    const panel = {
        numero: document.querySelector('[data-panel-numero]'),
        vacio: document.querySelector('[data-panel-vacio]'),
        contenido: document.querySelector('[data-panel-contenido]'),
        caras: document.querySelector('[data-panel-caras]'),
        nota: document.querySelector('[data-panel-nota]'),
        movilidad: document.querySelector('[data-panel-movilidad]'),
        urgente: document.querySelector('[data-panel-urgente]'),
        urgenteBadge: document.querySelector('[data-panel-urgente-badge]'),
        cambio: document.querySelector('[data-panel-cambio]'),
        chips: document.querySelectorAll('[data-chip]'),
        detalle: document.querySelectorAll('[data-detalle-cara]'),
    };

    let hallazgo = 'caries';
    let modo = 'cara';
    let capa = 'todos';
    let resaltar = null;
    let seleccionada = null;
    let mapa = leerMapa();
    const historial = [];
    const rehacer = [];

    montarArcada(contenedorArcada, { tipo: campoTipo.value });

    // Orden de navegación: el mismo que en la arcada (18 → 28, 48 → 38).
    const orden = [...contenedorArcada.querySelectorAll('[data-pieza]')].map((n) => n.dataset.pieza);
    orden.forEach((n) => { mapa[n] ??= enBlanco(); });

    function leerMapa() {
        try {
            const v = JSON.parse(campoPiezas.value || '{}');
            return v && typeof v === 'object' ? v : {};
        } catch { return {}; }
    }

    function enBlanco() {
        return { estado: 'sano', caras: Object.fromEntries(CARAS.map((c) => [c, 'sano'])), nota: null, movilidad: 0, urgente: false };
    }

    const etiqueta = (clave) => ESTADOS[clave]?.etiqueta ?? clave;
    const colorDe = (clave) => ESTADOS[clave]?.color ?? '#ffffff';
    const visible = (clave) => clave === 'sano' || capa === 'todos' || ESTADOS[clave]?.capa === capa;
    const colorVisible = (clave) => (visible(clave) ? colorDe(clave) : '#ffffff');

    // --- Historial (deshacer / rehacer) ---------------------------------------

    function recordar() {
        historial.push(JSON.stringify(mapa));
        if (historial.length > 60) historial.shift();
        rehacer.length = 0;
        actualizarHistorial();
    }

    function actualizarHistorial() {
        document.querySelector('[data-os-deshacer]').disabled = historial.length === 0;
        document.querySelector('[data-os-rehacer]').disabled = rehacer.length === 0;
    }

    function deshacer() {
        if (!historial.length) return;
        rehacer.push(JSON.stringify(mapa));
        mapa = JSON.parse(historial.pop());
        actualizarHistorial();
        refrescar();
    }

    function rehacerCambio() {
        if (!rehacer.length) return;
        historial.push(JSON.stringify(mapa));
        mapa = JSON.parse(rehacer.pop());
        actualizarHistorial();
        refrescar();
    }

    // --- Pintado y resumen ------------------------------------------------------

    function guardar() {
        campoPiezas.value = JSON.stringify(mapa);

        const conteo = { evaluacion: 0, ejecucion: 0, urgentes: 0 };
        const porHallazgo = Object.fromEntries(Object.keys(ESTADOS).map((k) => [k, 0]));

        Object.values(mapa).forEach((pieza) => {
            const claves = [...Object.values(pieza.caras ?? {}), pieza.estado];
            ['evaluacion', 'ejecucion'].forEach((c) => {
                if (claves.some((k) => ESTADOS[k]?.capa === c)) conteo[c]++;
            });
            if (pieza.urgente) conteo.urgentes++;
            new Set(claves.filter((k) => k !== 'sano')).forEach((k) => { porHallazgo[k]++; });
        });

        document.getElementById('contador-evaluacion').textContent = conteo.evaluacion;
        document.getElementById('contador-ejecucion').textContent = conteo.ejecucion;
        document.getElementById('contador-urgentes').textContent = conteo.urgentes;
        document.querySelectorAll('[data-conteo]').forEach((b) => { b.textContent = porHallazgo[b.dataset.conteo] ?? 0; });

        pintarResumen();
        pintarCambios();
    }

    function pintar() {
        pintarMapa(raiz, mapa, ESTADOS, { capa, seleccionada, resaltar });
        guardar();
    }

    function refrescar() {
        pintar();
        if (seleccionada) abrirPanel(seleccionada);
    }

    function describir(numero, pieza) {
        const partes = [];
        if (pieza.estado !== 'sano') {
            partes.push(etiqueta(pieza.estado).toLowerCase());
        } else {
            CARAS.forEach((c) => {
                if (pieza.caras?.[c] && pieza.caras[c] !== 'sano') partes.push(`${etiqueta(pieza.caras[c]).toLowerCase()} (${c})`);
            });
        }
        if (Number(pieza.movilidad) > 0) partes.push(`movilidad grado ${['0', 'I', 'II', 'III'][Number(pieza.movilidad)]}`);
        if (!partes.length) return null;
        let linea = `Pieza ${numero}: ${partes.join(', ')}`;
        if (pieza.urgente) linea += ' [URGENTE]';
        if (pieza.nota) linea += ` — ${pieza.nota}`;
        return `${linea}.`;
    }

    function pintarResumen() {
        const lineas = orden.map((n) => describir(n, mapa[n] ?? enBlanco())).filter(Boolean);
        document.querySelector('[data-os-resumen]').textContent = lineas.length ? lineas.join('\n') : 'Sin hallazgos: dentición sana.';
    }

    function cambiosDe(numero) {
        if (!ANTERIOR) return [];
        const antes = ANTERIOR[numero] ?? enBlanco();
        const ahora = mapa[numero] ?? enBlanco();
        const lista = [];
        if ((antes.estado ?? 'sano') !== (ahora.estado ?? 'sano')) {
            lista.push({ cara: null, antes: antes.estado ?? 'sano', despues: ahora.estado ?? 'sano' });
        } else {
            CARAS.forEach((c) => {
                const a = antes.caras?.[c] ?? 'sano';
                const b = ahora.caras?.[c] ?? 'sano';
                if (a !== b) lista.push({ cara: c, antes: a, despues: b });
            });
        }
        return lista;
    }

    function textoCambio(c) {
        const donde = c.cara ? ` (${c.cara})` : '';
        if (c.antes === 'sano') return `<span class="badge bg-red-lt">Nuevo</span> ${etiqueta(c.despues)}${donde}`;
        if (c.despues === 'sano') return `<span class="badge bg-green-lt">Resuelto</span> ${etiqueta(c.antes)}${donde}`;
        return `<span class="badge bg-azure-lt">Cambió</span> ${etiqueta(c.antes)} → ${etiqueta(c.despues)}${donde}`;
    }

    function pintarCambios() {
        const lista = document.querySelector('[data-os-cambios]');
        if (!lista) return;
        const filas = orden.flatMap((n) => cambiosDe(n).map((c) => `<li><strong>Pieza ${n}</strong>: ${textoCambio(c)}</li>`));
        lista.innerHTML = filas.length ? filas.join('') : '<li class="text-secondary">Sin cambios.</li>';
    }

    // --- Panel de la pieza ---------------------------------------------------------

    function abrirPanel(numero) {
        seleccionada = String(numero);
        const pieza = (mapa[seleccionada] ??= enBlanco());

        panel.numero.textContent = seleccionada;
        panel.vacio.classList.add('d-none');
        panel.contenido.classList.remove('d-none');
        panel.nota.value = pieza.nota ?? '';
        panel.movilidad.value = String(pieza.movilidad ?? 0);
        panel.urgente.checked = Boolean(pieza.urgente);
        panel.urgenteBadge.classList.toggle('d-none', !pieza.urgente);

        panel.detalle.forEach((cara) => {
            const clave = pieza.estado !== 'sano' ? pieza.estado : (pieza.caras?.[cara.dataset.detalleCara] ?? 'sano');
            cara.setAttribute('fill', colorVisible(clave));
        });

        panel.chips.forEach((chip) => {
            const activo = chip.dataset.chip === pieza.estado || (chip.dataset.chip === 'sano' && pieza.estado === 'sano');
            chip.classList.toggle('active', activo);
            chip.style.backgroundColor = activo && chip.dataset.chip !== 'sano' ? colorDe(chip.dataset.chip) : '';
        });

        panel.caras.innerHTML = CARAS.map((cara) => `
            <div class="list-group-item px-0 py-1 d-flex align-items-center gap-2">
                <span class="leyenda-muestra" style="background-color: ${colorVisible(pieza.caras[cara] ?? 'sano')}"></span>
                <span class="flex-fill text-capitalize small">${cara}</span>
                <select class="form-select form-select-sm w-auto" data-cara-select="${cara}" ${pieza.estado !== 'sano' ? 'disabled' : ''}>
                    ${Object.entries(ESTADOS).map(([k, e]) =>
                        `<option value="${k}" ${(pieza.caras[cara] ?? 'sano') === k ? 'selected' : ''}>${e.etiqueta}</option>`
                    ).join('')}
                </select>
            </div>
        `).join('');

        panel.caras.querySelectorAll('[data-cara-select]').forEach((select) => {
            select.addEventListener('change', () => {
                recordar();
                mapa[seleccionada].caras[select.dataset.caraSelect] = select.value;
                refrescar();
            });
        });

        const cambios = cambiosDe(seleccionada);
        panel.cambio.classList.toggle('d-none', cambios.length === 0);
        panel.cambio.innerHTML = cambios.length ? textoCambio(cambios[0]) : '';

        pintar();
    }

    function cerrarPanel() {
        seleccionada = null;
        panel.numero.textContent = '—';
        panel.vacio.classList.remove('d-none');
        panel.contenido.classList.add('d-none');
        pintar();
    }

    function aplicarEnCara(numero, cara) {
        recordar();
        mapa[numero] ??= enBlanco();
        mapa[numero].estado = 'sano';
        mapa[numero].caras[cara] = mapa[numero].caras[cara] === hallazgo ? 'sano' : hallazgo;
        abrirPanel(numero);
    }

    function aplicarEnPieza(numero, valor = hallazgo) {
        recordar();
        mapa[numero] ??= enBlanco();
        mapa[numero].estado = mapa[numero].estado === valor ? 'sano' : valor;
        if (mapa[numero].estado === 'sano') {
            mapa[numero].caras = Object.fromEntries(CARAS.map((c) => [c, 'sano']));
        }
        abrirPanel(numero);
    }

    panel.detalle.forEach((cara) => {
        cara.addEventListener('click', () => { if (seleccionada) aplicarEnCara(seleccionada, cara.dataset.detalleCara); });
    });

    panel.chips.forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!seleccionada) return;
            if (chip.dataset.chip === 'sano') {
                recordar();
                mapa[seleccionada] = { ...enBlanco(), nota: mapa[seleccionada].nota ?? null };
                abrirPanel(seleccionada);
            } else {
                aplicarEnPieza(seleccionada, chip.dataset.chip);
            }
        });
    });

    panel.nota.addEventListener('input', () => {
        if (!seleccionada) return;
        mapa[seleccionada].nota = panel.nota.value || null;
        guardar();
    });

    panel.movilidad.addEventListener('change', () => {
        if (!seleccionada) return;
        recordar();
        mapa[seleccionada].movilidad = Number(panel.movilidad.value);
        refrescar();
    });

    panel.urgente.addEventListener('change', () => {
        if (!seleccionada) return;
        recordar();
        mapa[seleccionada].urgente = panel.urgente.checked;
        refrescar();
    });

    /** Pieza del lado opuesto: 16 ↔ 26, 36 ↔ 46, 55 ↔ 65… */
    function contralateral(numero) {
        const c = Number(String(numero)[0]);
        const pares = { 1: 2, 2: 1, 3: 4, 4: 3, 5: 6, 6: 5, 7: 8, 8: 7 };
        return `${pares[c]}${String(numero).slice(1)}`;
    }

    /** Pieza antagonista: 16 ↔ 46, 26 ↔ 36, 55 ↔ 85… */
    function antagonista(numero) {
        const c = Number(String(numero)[0]);
        const pares = { 1: 4, 4: 1, 2: 3, 3: 2, 5: 8, 8: 5, 6: 7, 7: 6 };
        return `${pares[c]}${String(numero).slice(1)}`;
    }

    function copiarA(destino) {
        if (!seleccionada || !mapa[destino]) return;
        recordar();
        mapa[destino] = JSON.parse(JSON.stringify(mapa[seleccionada]));
        abrirPanel(destino);
    }

    document.querySelector('[data-os-copiar-contralateral]').addEventListener('click', () => copiarA(contralateral(seleccionada)));
    document.querySelector('[data-os-copiar-antagonista]').addEventListener('click', () => copiarA(antagonista(seleccionada)));

    function moverSeleccion(paso) {
        if (!orden.length) return;
        const i = seleccionada ? orden.indexOf(seleccionada) : -1;
        abrirPanel(orden[(i + paso + orden.length) % orden.length]);
    }

    document.querySelector('[data-os-pieza-anterior]').addEventListener('click', () => moverSeleccion(-1));
    document.querySelector('[data-os-pieza-siguiente]').addEventListener('click', () => moverSeleccion(1));

    // --- Interacción con arcada y cuadrícula ---------------------------------------

    raiz.addEventListener('click', (e) => {
        const nodoPieza = e.target.closest('[data-pieza]');
        if (!nodoPieza || !raiz.contains(nodoPieza)) return;

        const numero = nodoPieza.dataset.pieza;
        const cara = e.target.closest('[data-cara]');

        if (modo === 'pieza') {
            aplicarEnPieza(numero);
        } else if (cara) {
            aplicarEnCara(numero, cara.dataset.cara);
        } else {
            abrirPanel(numero);
        }
    });

    // Teclado sobre la arcada: Enter selecciona la pieza enfocada.
    raiz.addEventListener('keydown', (e) => {
        const nodoPieza = e.target.closest?.('[data-pieza]');
        if (nodoPieza && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            abrirPanel(nodoPieza.dataset.pieza);
        }
    });

    function elegirHallazgo(clave) {
        const boton = document.querySelector(`.os-hallazgo[data-hallazgo="${clave}"]`);
        if (!boton) return;
        hallazgo = clave;
        document.querySelectorAll('.os-hallazgo').forEach((b) => b.classList.remove('active'));
        boton.classList.add('active');

        // Elegir un hallazgo de otra capa muestra esa capa automáticamente.
        const suCapa = boton.dataset.capa;
        if (capa !== 'todos' && suCapa !== 'ninguna' && suCapa !== capa) {
            document.querySelector(`[data-os-capa="${suCapa}"]`)?.click();
        }
    }

    document.querySelectorAll('.os-hallazgo').forEach((boton) => {
        boton.addEventListener('click', () => elegirHallazgo(boton.dataset.hallazgo));
    });

    function elegirModo(nuevo) {
        modo = nuevo;
        document.querySelectorAll('[data-os-modo]').forEach((b) => b.classList.toggle('active', b.dataset.osModo === nuevo));
    }

    document.querySelectorAll('[data-os-modo]').forEach((boton) => {
        boton.addEventListener('click', () => elegirModo(boton.dataset.osModo));
    });

    document.querySelectorAll('[data-os-capa]').forEach((boton) => {
        boton.addEventListener('click', () => {
            capa = boton.dataset.osCapa;
            document.querySelectorAll('[data-os-capa]').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');
            refrescar();
        });
    });

    document.querySelectorAll('[data-os-vista]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const vista = boton.dataset.osVista;
            document.querySelectorAll('[data-os-vista]').forEach((b) => b.classList.toggle('active', b === boton));
            document.querySelector('[data-os-vista-panel="arcada"]').classList.toggle('d-none', vista === 'cuadricula');
            document.querySelector('[data-os-vista-panel="cuadricula"]').classList.toggle('d-none', vista !== 'cuadricula');
            contenedorArcada.classList.toggle('odontograma-3d', vista === '3d');
            try { localStorage.setItem('odontosuite-vista-odontograma', vista); } catch { /* sin almacenamiento */ }
        });
    });

    try {
        const guardada = localStorage.getItem('odontosuite-vista-odontograma');
        if (guardada) document.querySelector(`[data-os-vista="${guardada}"]`)?.click();
    } catch { /* sin almacenamiento */ }

    document.querySelectorAll('[data-resaltar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            resaltar = resaltar === boton.dataset.resaltar ? null : boton.dataset.resaltar;
            document.querySelectorAll('[data-resaltar]').forEach((b) => b.classList.toggle('active', b.dataset.resaltar === resaltar));
            pintar();
        });
    });

    document.querySelector('[data-os-limpiar]').addEventListener('click', () => {
        if (!window.confirm('¿Restablecer todas las piezas a "sano"?')) return;
        recordar();
        Object.keys(mapa).forEach((n) => { mapa[n] = enBlanco(); });
        refrescar();
    });

    document.querySelector('[data-os-deshacer]').addEventListener('click', deshacer);
    document.querySelector('[data-os-rehacer]').addEventListener('click', rehacerCambio);

    document.querySelector('[data-os-copiar-resumen]').addEventListener('click', async (e) => {
        const texto = document.querySelector('[data-os-resumen]').textContent;
        try {
            await navigator.clipboard.writeText(texto);
            e.currentTarget.innerHTML = '<i class="ti ti-check me-1"></i>Copiado';
            setTimeout(() => { e.target.closest('button').innerHTML = '<i class="ti ti-clipboard-copy me-1"></i>Copiar'; }, 1500);
        } catch {
            window.prompt('Copia el resumen:', texto);
        }
    });

    // Cambiar de dentición recarga con el mapa en blanco de esa numeración.
    document.querySelectorAll('[data-os-denticion]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const nuevo = boton.dataset.osDenticion;
            if (nuevo === campoTipo.value) return;
            if (!window.confirm('Cambiar la dentición reinicia el mapa de piezas. ¿Continuar?')) return;

            const url = new URL(window.location.href);
            url.searchParams.set('tipo', nuevo);
            url.searchParams.delete('desde');
            window.location.href = url.toString();
        });
    });

    // --- Atajos de teclado ----------------------------------------------------------

    document.addEventListener('keydown', (e) => {
        const enCampo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !enCampo) { e.preventDefault(); deshacer(); return; }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y' && !enCampo) { e.preventDefault(); rehacerCambio(); return; }
        if (enCampo || e.ctrlKey || e.metaKey || e.altKey) return;

        if (e.key >= '1' && e.key <= '9') { const clave = ATAJOS[Number(e.key) - 1]; if (clave) elegirHallazgo(clave); }
        else if (e.key === '0') elegirHallazgo('sano');
        else if (e.key === 'ArrowLeft') { e.preventDefault(); moverSeleccion(-1); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); moverSeleccion(1); }
        else if (e.key === 'Escape') cerrarPanel();
        else if (e.key.toLowerCase() === 'c') elegirModo('cara');
        else if (e.key.toLowerCase() === 'p') elegirModo('pieza');
    });

    pintar();
});
</script>
@endpush
@endsection
