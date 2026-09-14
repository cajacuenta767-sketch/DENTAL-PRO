@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $documento->exists ? 'Editar '.$documento->tipo_legible : 'Nuevo Documento Clínico')

@section('contenido')
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
</script>
@endpush
@endsection
