@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $documento->exists ? 'Editar '.$documento->tipo_legible : 'Nuevo Documento Clínico')

@section('contenido')
@if ($paciente)
    <div class="mb-3">
        <x-alerta-medica :paciente="$paciente" />
    </div>
@endif

<form method="POST" action="{{ $documento->exists ? route('admin.documentos.update', $documento) : route('admin.documentos.store') }}">
    @csrf
    @if ($documento->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-writing me-2"></i>Contenido del documento</h3>
                    @unless ($documento->exists)
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-os-plantilla>
                            <i class="ti ti-template me-1"></i>Cargar plantilla del tipo
                        </button>
                    @endunless
                </div>
                <div class="card-body">
                    <x-campo nombre="titulo" etiqueta="Título del documento" requerido>
                        <input type="text" id="titulo" name="titulo" class="form-control"
                               value="{{ old('titulo', $documento->titulo) }}" required>
                    </x-campo>

                    <x-campo nombre="contenido" etiqueta="Contenido" requerido
                             ayuda="Este texto se imprime tal cual en el PDF firmado por el doctor.">
                        <textarea id="contenido" name="contenido" class="form-control font-monospace" rows="14"
                                  required>{{ old('contenido', $documento->contenido) }}</textarea>
                    </x-campo>

                    <x-campo nombre="indicaciones" etiqueta="Indicaciones adicionales">
                        <textarea id="indicaciones" name="indicaciones" class="form-control" rows="3">{{ old('indicaciones', $documento->indicaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>

            {{-- Asistente de Prescripción Odontológica (Vademécum) - Solo visible en recetas --}}
            <div class="card mt-3 border-primary" id="tarjeta-vademecum" {{ old('tipo', $documento->tipo) === 'RECETA' ? '' : 'hidden' }}>
                <div class="card-header bg-primary-lt">
                    <h3 class="card-title text-primary"><i class="ti ti-pill me-2"></i>Asistente de Prescripción (Vademécum Odontológico)</h3>
                </div>
                <div class="card-body">
                    <div id="vademecum-alerta-paciente" class="alert alert-danger d-none mb-3" role="alert">
                        <div class="d-flex">
                            <i class="ti ti-alert-triangle fs-2 me-2"></i>
                            <div>
                                <h4 class="alert-title mb-1 fw-bold">¡ALERTA DE SEGURIDAD CLÍNICA!</h4>
                                <div id="vademecum-alerta-texto"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Buscar fármaco en el vademécum</label>
                            <div class="input-icon">
                                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                                <input type="text" id="vademecum-buscar" class="form-control"
                                       placeholder="Escribe al menos 2 letras (ej: Amoxicilina, Ibuprofeno, Ketorolac)..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <button type="button" id="btn-vademecum-insertar" class="btn btn-primary w-100" disabled>
                                <i class="ti ti-plus me-1"></i>Insertar en la receta
                            </button>
                        </div>
                    </div>

                    <div id="vademecum-resultados" class="list-group list-group-flush border rounded d-none mb-2" style="max-height: 200px; overflow-y: auto;">
                    </div>

                    <div id="vademecum-preview" class="p-3 bg-body-tertiary rounded border d-none small">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold text-primary fs-3" id="vademecum-prev-nombre"></span>
                            <span class="badge bg-blue-lt" id="vademecum-prev-familia"></span>
                        </div>
                        <div class="mb-1"><strong>Presentación:</strong> <span id="vademecum-prev-presentacion"></span> · <span id="vademecum-prev-concentracion"></span></div>
                        <div class="mb-1"><strong>Posología adultos:</strong> <span id="vademecum-prev-posologia"></span></div>
                        <div class="text-danger fw-semibold mt-2 d-none" id="vademecum-prev-contra">
                            <i class="ti ti-alert-circle me-1"></i>Contraindicaciones: <span id="vademecum-prev-contra-txt"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Firma del paciente: solo aplica a consentimientos informados --}}
            <div class="card mt-3" id="tarjeta-firma" {{ old('tipo', $documento->tipo) === 'CONSENTIMIENTO' ? '' : 'hidden' }}>
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-writing-sign me-2"></i>Firma del paciente</h3>
                    @if ($documento->esta_firmado)
                        <span class="badge bg-success-lt ms-auto">
                            <i class="ti ti-writing-sign me-1"></i>Firmado el {{ $documento->firmado_en->format('d/m/Y H:i') }}
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-secondary small mb-2">
                        Pide al paciente que firme con el dedo o el ratón. La firma se imprime en el PDF del consentimiento.
                        @if ($documento->esta_firmado) Si dibujas una nueva, reemplaza a la actual. @endif
                    </p>
                    <canvas id="firma-canvas" width="600" height="200" class="border rounded w-100"
                            style="touch-action:none; background:#fff"></canvas>
                    <input type="hidden" name="firma" id="firma-input">
                    @error('firma')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="firma-limpiar">
                            <i class="ti ti-eraser me-1"></i>Limpiar
                        </button>
                        @if ($documento->esta_firmado)
                            <label class="form-check mb-0">
                                <input type="checkbox" name="quitar_firma" value="1" class="form-check-input"
                                       @checked(old('quitar_firma'))>
                                <span class="form-check-label">Quitar firma actual</span>
                            </label>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos de emisión</h3></div>
                <div class="card-body">
                    @if ($documento->exists)
                        <div class="mb-3">
                            <div class="text-secondary small text-uppercase">Folio</div>
                            <div class="h3 mb-0 font-monospace">{{ $documento->folio }}</div>
                        </div>
                    @endif

                    <x-campo nombre="tipo" etiqueta="Tipo de documento" requerido>
                        <select id="tipo" name="tipo" class="form-select" required>
                            @foreach (\App\Models\DocumentoClinico::TIPOS as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('tipo', $documento->tipo) === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                        <x-selector-paciente nombre="paciente_id" :seleccionado="$documento->paciente_id" requerido />
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor que firma" requerido>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $documento->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $documento->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-7">
                            <x-campo nombre="fecha_emision" etiqueta="Fecha de emisión" requerido>
                                <input type="date" id="fecha_emision" name="fecha_emision" class="form-control"
                                       max="{{ now()->toDateString() }}"
                                       value="{{ old('fecha_emision', $documento->fecha_emision?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-5">
                            <x-campo nombre="vigencia_dias" etiqueta="Vigencia" ayuda="Días. Vacío = sin vencimiento.">
                                <input type="number" id="vigencia_dias" name="vigencia_dias" class="form-control"
                                       min="1" max="3650" value="{{ old('vigencia_dias', $documento->vigencia_dias) }}">
                            </x-campo>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.documentos.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $documento->exists ? 'Guardar' : 'Emitir documento' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const PLANTILLAS = @json($plantillas);
    const TITULOS = @json(\App\Models\DocumentoClinico::TIPOS);

    const tipo = document.getElementById('tipo');
    const contenido = document.getElementById('contenido');
    const titulo = document.getElementById('titulo');
    const boton = document.querySelector('[data-os-plantilla]');

    function cargar() {
        contenido.value = PLANTILLAS[tipo.value] ?? '';
        titulo.value = TITULOS[tipo.value] ?? '';
    }

    boton?.addEventListener('click', cargar);

    // Cambiar de tipo recarga la plantilla solo si el campo sigue intacto.
    tipo.addEventListener('change', () => {
        const sinTocar = Object.values(PLANTILLAS).includes(contenido.value) || contenido.value.trim() === '';
        if (sinTocar) cargar();
    });

    // La tarjeta de firma solo se muestra para consentimientos informados.
    const tarjetaFirma = document.getElementById('tarjeta-firma');
    function alternarFirma() {
        tarjetaFirma.hidden = tipo.value !== 'CONSENTIMIENTO';
    }
    tipo.addEventListener('change', alternarFirma);
    alternarFirma();
})();

(() => {
    // Pad de firma: dibuja con ratón, lápiz o dedo mediante pointer events.
    const canvas = document.getElementById('firma-canvas');
    const entrada = document.getElementById('firma-input');
    const limpiar = document.getElementById('firma-limpiar');
    const formulario = canvas?.closest('form');
    if (!canvas || !entrada || !formulario) return;

    const ctx = canvas.getContext('2d');
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a1a1a';

    let dibujando = false;
    let hayTrazos = false;

    function punto(e) {
        const r = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - r.left) * (canvas.width / r.width),
            y: (e.clientY - r.top) * (canvas.height / r.height),
        };
    }

    canvas.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        canvas.setPointerCapture(e.pointerId);
        dibujando = true;
        const p = punto(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    });

    canvas.addEventListener('pointermove', (e) => {
        if (!dibujando) return;
        e.preventDefault();
        const p = punto(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        hayTrazos = true;
    });

    const terminar = (e) => {
        if (!dibujando) return;
        dibujando = false;
        if (e.pointerId !== undefined && canvas.hasPointerCapture(e.pointerId)) {
            canvas.releasePointerCapture(e.pointerId);
        }
        ctx.closePath();
    };
    canvas.addEventListener('pointerup', terminar);
    canvas.addEventListener('pointercancel', terminar);
    canvas.addEventListener('pointerleave', terminar);

    limpiar?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hayTrazos = false;
        entrada.value = '';
    });

    formulario.addEventListener('submit', () => {
        const tarjeta = document.getElementById('tarjeta-firma');
        entrada.value = hayTrazos && tarjeta && !tarjeta.hidden ? canvas.toDataURL('image/png') : '';
    });
})();

(() => {
    // Asistente de Prescripción Vademécum Odontológico y Verificación de Alergias
    const tipo = document.getElementById('tipo');
    const tarjetaVademecum = document.getElementById('tarjeta-vademecum');
    const inputBuscar = document.getElementById('vademecum-buscar');
    const listaResultados = document.getElementById('vademecum-resultados');
    const preview = document.getElementById('vademecum-preview');
    const btnInsertar = document.getElementById('btn-vademecum-insertar');
    const alertaPaciente = document.getElementById('vademecum-alerta-paciente');
    const alertaTexto = document.getElementById('vademecum-alerta-texto');
    const contenido = document.getElementById('contenido');

    const pacienteAlergias = @json(mb_strtolower($paciente?->alergias ?? ''));

    let medSeleccionado = null;
    let timerBuscar = null;

    function alternarVademecum() {
        if (tarjetaVademecum) {
            tarjetaVademecum.hidden = tipo.value !== 'RECETA';
        }
    }
    tipo.addEventListener('change', alternarVademecum);
    alternarVademecum();

    if (!inputBuscar) return;

    inputBuscar.addEventListener('input', () => {
        clearTimeout(timerBuscar);
        const q = inputBuscar.value.trim();
        if (q.length < 2) {
            listaResultados.classList.add('d-none');
            listaResultados.innerHTML = '';
            return;
        }

        timerBuscar = setTimeout(async () => {
            try {
                const res = await fetch(`{{ route('admin.vademecum.buscar') }}?q=${encodeURIComponent(q)}`);
                if (!res.ok) return;
                const items = await res.json();

                if (!items.length) {
                    listaResultados.innerHTML = '<div class="list-group-item text-secondary small py-2">No se encontraron medicamentos para esa búsqueda.</div>';
                    listaResultados.classList.remove('d-none');
                    return;
                }

                listaResultados.innerHTML = items.map(m => `
                    <button type="button" class="list-group-item list-group-item-action py-2 d-flex justify-content-between align-items-center vademecum-item" data-json='${JSON.stringify(m).replace(/'/g, "&#39;")}'>
                        <div>
                            <strong>${m.principio_activo}</strong> ${m.nombre_comercial ? `<span class="text-secondary small">(${m.nombre_comercial})</span>` : ''}
                            <div class="text-secondary small">${m.presentacion} · ${m.concentracion}</div>
                        </div>
                        <span class="badge bg-blue-lt">${m.familia}</span>
                    </button>
                `).join('');
                listaResultados.classList.remove('d-none');

                listaResultados.querySelectorAll('.vademecum-item').forEach(el => {
                    el.addEventListener('click', () => {
                        const data = JSON.parse(el.getAttribute('data-json'));
                        seleccionarMedicamento(data);
                    });
                });
            } catch (err) {
                console.error('Error buscando en vademécum:', err);
            }
        }, 250);
    });

    function seleccionarMedicamento(med) {
        medSeleccionado = med;
        listaResultados.classList.add('d-none');

        document.getElementById('vademecum-prev-nombre').textContent = `${med.principio_activo} ${med.nombre_comercial ? '(' + med.nombre_comercial + ')' : ''}`;
        document.getElementById('vademecum-prev-familia').textContent = med.familia;
        document.getElementById('vademecum-prev-presentacion').textContent = med.presentacion;
        document.getElementById('vademecum-prev-concentracion').textContent = med.concentracion;
        document.getElementById('vademecum-prev-posologia').textContent = med.posologia_adulto || 'Según criterio del profesional.';

        const divContra = document.getElementById('vademecum-prev-contra');
        if (med.contraindicaciones) {
            document.getElementById('vademecum-prev-contra-txt').textContent = med.contraindicaciones;
            divContra.classList.remove('d-none');
        } else {
            divContra.classList.add('d-none');
        }

        preview.classList.remove('d-none');
        btnInsertar.disabled = false;

        // Verificación cruzada de alergias del paciente
        alertaPaciente.classList.add('d-none');
        alertaTexto.innerHTML = '';

        if (pacienteAlergias.length > 0) {
            const fam = (med.familia || '').toUpperCase();
            const nom = (med.principio_activo || '').toLowerCase();
            let conflicto = false;
            let motivo = '';

            if (fam === 'PENICILINAS' && (pacienteAlergias.includes('penicil') || pacienteAlergias.includes('amoxi') || pacienteAlergias.includes('betalact'))) {
                conflicto = true;
                motivo = `El paciente tiene registro de <strong>alergia a penicilinas / betalactámicos</strong>. El fármaco seleccionado (${med.principio_activo}) pertenece a esta familia y está <strong>estrictamente contraindicado</strong>.`;
            } else if (fam === 'AINES' && (pacienteAlergias.includes('aine') || pacienteAlergias.includes('aspirin') || pacienteAlergias.includes('ibuprof') || pacienteAlergias.includes('ketorol') || pacienteAlergias.includes('diclofen'))) {
                conflicto = true;
                motivo = `El paciente tiene registro de <strong>alergia o intolerancia a AINEs</strong>. Verifique analgésicos alternativos (como Paracetamol).`;
            } else if (pacienteAlergias.includes(nom)) {
                conflicto = true;
                motivo = `El paciente registra antecedentes alérgicos directos a <strong>${med.principio_activo}</strong>.`;
            }

            if (conflicto) {
                alertaTexto.innerHTML = motivo;
                alertaPaciente.classList.remove('d-none');
                if (window.OdontoSuite && window.OdontoSuite.toast) {
                    window.OdontoSuite.toast('⚠️ ¡ALERTA DE SEGURIDAD! Conflicto alérgico detectado para este paciente.', 'error', 6000);
                }
            }
        }
    }

    btnInsertar.addEventListener('click', () => {
        if (!medSeleccionado) return;

        const texto = `\n• ${medSeleccionado.principio_activo} ${medSeleccionado.concentracion} (${medSeleccionado.presentacion})\n  Tomar: ${medSeleccionado.posologia_adulto || '1 cada 8 hs por 7 días'}\n`;
        contenido.value = contenido.value.trimEnd() + '\n' + texto;

        if (window.OdontoSuite && window.OdontoSuite.toast) {
            window.OdontoSuite.toast(`${medSeleccionado.principio_activo} agregado a la receta.`, 'success');
        }

        // Reset
        inputBuscar.value = '';
        listaResultados.classList.add('d-none');
    });
})();
</script>
@endpush
@endsection
