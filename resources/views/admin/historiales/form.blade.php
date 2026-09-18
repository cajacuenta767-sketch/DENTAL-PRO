@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $historial->exists ? 'Editar Consulta' : 'Nueva Consulta')
@section('subtitulo', $paciente->nombre_completo)

@push('head')
<style>
    .os-dictar-barra { display: flex; justify-content: flex-end; align-items: center; gap: .5rem; margin-top: .25rem; }
    .os-dictar-barra[hidden] { display: none; }
    .os-dictar.grabando { color: #d63939; border-color: #d63939; }
    .os-dictar.grabando .ti { animation: os-pulso 1s ease-in-out infinite; }
    .os-dictar-interino { font-style: italic; color: #6c757d; font-size: .8rem; flex: 1; text-align: right; }
    @keyframes os-pulso { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }
</style>
@endpush

@php
    $camposPlantilla = config('evolucion.campos', []);
    $plantillaActual = old('plantilla', $historial->plantilla);
@endphp

@section('contenido')
<form method="POST" action="{{ $historial->exists ? route('admin.historiales.update', $historial) : route('admin.historiales.store', $paciente) }}">
    @csrf
    @if ($historial->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-notes-medical me-2"></i>Registro de la consulta</h3>
                </div>
                <div class="card-body">
                    <x-campo nombre="plantilla" etiqueta="Plantilla de nota"
                             ayuda="Rellena los campos vacíos con un texto base según el tipo de atención; siempre puedes corregirlo.">
                        <select id="plantilla" name="plantilla" class="form-select" data-plantilla-selector>
                            <option value="">— Nota libre —</option>
                            @foreach ($plantillas as $clave => $plantilla)
                                <option value="{{ $clave }}" @selected($plantillaActual === $clave)>{{ $plantilla['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    @foreach ([
                        ['motivo_consulta', 'Motivo de consulta', true, 2, null],
                        ['sintomas', 'Síntomas referidos', false, 2, null],
                        ['diagnostico', 'Diagnóstico', true, 3, null],
                        ['tratamiento_realizado', 'Tratamiento realizado', false, 3, null],
                        ['prescripcion_receta', 'Prescripción / receta', false, 3, 'Medicamento, dosis y duración.'],
                    ] as [$campo, $etiqueta, $requerido, $filas, $ayuda])
                        <x-campo :nombre="$campo" :etiqueta="$etiqueta" :requerido="$requerido" :ayuda="$ayuda">
                            <textarea id="{{ $campo }}" name="{{ $campo }}" class="form-control" rows="{{ $filas }}" @required($requerido)>{{ old($campo, $historial->{$campo}) }}</textarea>
                            <div class="os-dictar-barra" data-dictar-barra hidden>
                                <span class="os-dictar-interino" data-dictar-interino></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary os-dictar" data-dictar="{{ $campo }}" title="Dictar con el micrófono">
                                    <i class="ti ti-microphone me-1"></i>Dictar
                                </button>
                            </div>
                        </x-campo>
                    @endforeach

                    <div class="row g-2">
                        <div class="col-md-8">
                            <x-campo nombre="anestesia" etiqueta="Anestésico utilizado">
                                <select id="anestesia" name="anestesia" class="form-select">
                                    <option value="">— No aplica —</option>
                                    @foreach ($anestesicos as $anestesico)
                                        <option value="{{ $anestesico }}" @selected(old('anestesia', $historial->anestesia) === $anestesico)>{{ $anestesico }}</option>
                                    @endforeach
                                    @if (old('anestesia', $historial->anestesia) && ! in_array(old('anestesia', $historial->anestesia), $anestesicos, true))
                                        <option value="{{ old('anestesia', $historial->anestesia) }}" selected>{{ old('anestesia', $historial->anestesia) }}</option>
                                    @endif
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="anestesia_cantidad" etiqueta="Cantidad (cartuchos)">
                                <input type="text" id="anestesia_cantidad" name="anestesia_cantidad" class="form-control"
                                       maxlength="40" placeholder="Ej.: 1.5" value="{{ old('anestesia_cantidad', $historial->anestesia_cantidad) }}">
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="medicacion" etiqueta="Medicación administrada en consulta"
                             ayuda="Fármacos aplicados durante la atención (distintos de la receta).">
                        <textarea id="medicacion" name="medicacion" class="form-control" rows="2">{{ old('medicacion', $historial->medicacion) }}</textarea>
                        <div class="os-dictar-barra" data-dictar-barra hidden>
                            <span class="os-dictar-interino" data-dictar-interino></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary os-dictar" data-dictar="medicacion" title="Dictar con el micrófono">
                                <i class="ti ti-microphone me-1"></i>Dictar
                            </button>
                        </div>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones e indicaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2">{{ old('observaciones', $historial->observaciones) }}</textarea>
                        <div class="os-dictar-barra" data-dictar-barra hidden>
                            <span class="os-dictar-interino" data-dictar-interino></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary os-dictar" data-dictar="observaciones" title="Dictar con el micrófono">
                                <i class="ti ti-microphone me-1"></i>Dictar
                            </button>
                        </div>
                    </x-campo>

                    <x-campo nombre="proxima_cita_indicaciones" etiqueta="Indicaciones para la próxima cita"
                             ayuda="Qué se hará en la siguiente sesión y qué debe traer o preparar el paciente.">
                        <textarea id="proxima_cita_indicaciones" name="proxima_cita_indicaciones" class="form-control" rows="2">{{ old('proxima_cita_indicaciones', $historial->proxima_cita_indicaciones) }}</textarea>
                        <div class="os-dictar-barra" data-dictar-barra hidden>
                            <span class="os-dictar-interino" data-dictar-interino></span>
                            <button type="button" class="btn btn-sm btn-outline-secondary os-dictar" data-dictar="proxima_cita_indicaciones" title="Dictar con el micrófono">
                                <i class="ti ti-microphone me-1"></i>Dictar
                            </button>
                        </div>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos de la atención</h3></div>
                <div class="card-body">
                    <x-campo nombre="fecha" etiqueta="Fecha de la consulta" requerido>
                        <input type="date" id="fecha" name="fecha" class="form-control" max="{{ now()->toDateString() }}"
                               value="{{ old('fecha', $historial->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor responsable" requerido>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $historial->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada" ayuda="Opcional: enlaza esta consulta con una cita.">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita asociada —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $historial->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.historiales.index', $paciente) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title text-warning"><i class="ti ti-alert-triangle me-2"></i>Alertas</h3></div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">Alergias</div>
                        <div>{{ $paciente->alergias ?: 'Sin registro' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">Enfermedades</div>
                        <div>{{ $paciente->enfermedades ?: 'Sin registro' }}</div>
                    </div>
                    <div>
                        <div class="text-secondary small text-uppercase">Medicación actual</div>
                        <div>{{ $paciente->medicamentos ?: 'Sin registro' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    // ---- Plantillas de nota: rellenan los campos vacíos ----------------------
    const plantillas = @json($plantillas);
    const campos = @json($camposPlantilla);
    const selector = document.querySelector('[data-plantilla-selector]');

    selector?.addEventListener('change', () => {
        const plantilla = plantillas[selector.value];
        if (!plantilla) return;

        const conTexto = [];

        campos.forEach((campo) => {
            const control = document.getElementById(campo);
            const texto = plantilla[campo] ?? '';
            if (!control || texto === '') return;

            if (control.value.trim() === '') {
                control.value = texto;
            } else if (control.value.trim() !== texto.trim()) {
                conTexto.push({ control, texto });
            }
        });

        if (conTexto.length > 0
            && confirm(`Hay ${conTexto.length} campo(s) con texto escrito. ¿Reemplazarlos por el contenido de la plantilla "${plantilla.etiqueta}"?`)) {
            conTexto.forEach(({ control, texto }) => { control.value = texto; });
        }
    });

    // ---- Dictado por voz (Web Speech API) ------------------------------------
    const Reconocimiento = window.SpeechRecognition || window.webkitSpeechRecognition;
    const barras = document.querySelectorAll('[data-dictar-barra]');

    if (!Reconocimiento) {
        // El navegador no soporta dictado: los botones ni siquiera se muestran.
        return;
    }

    barras.forEach((barra) => { barra.hidden = false; });

    let activo = null; // { reconocimiento, boton, control, interino }

    function detener() {
        if (!activo) return;
        try { activo.reconocimiento.stop(); } catch { /* ya detenido */ }
        activo.boton.classList.remove('grabando');
        activo.boton.innerHTML = '<i class="ti ti-microphone me-1"></i>Dictar';
        activo.interino.textContent = '';
        activo = null;
    }

    function insertar(control, texto) {
        const actual = control.value;
        const separador = actual === '' || /\s$/.test(actual) ? '' : ' ';
        control.value = actual + separador + texto;
        control.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.querySelectorAll('[data-dictar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const control = document.getElementById(boton.dataset.dictar);
            const interino = boton.closest('[data-dictar-barra]').querySelector('[data-dictar-interino]');
            if (!control) return;

            if (activo?.boton === boton) {
                detener();
                return;
            }
            detener();

            const reconocimiento = new Reconocimiento();
            reconocimiento.lang = 'es-ES';
            reconocimiento.continuous = true;
            reconocimiento.interimResults = true;

            reconocimiento.onresult = (evento) => {
                let parcial = '';
                for (let i = evento.resultIndex; i < evento.results.length; i++) {
                    const resultado = evento.results[i];
                    const transcripcion = resultado[0].transcript.trim();
                    if (resultado.isFinal) {
                        if (transcripcion !== '') insertar(control, transcripcion);
                    } else {
                        parcial += transcripcion + ' ';
                    }
                }
                interino.textContent = parcial.trim();
            };

            reconocimiento.onerror = (evento) => {
                if (evento.error === 'no-speech' || evento.error === 'aborted') return;
                interino.textContent = evento.error === 'not-allowed'
                    ? 'Permite el acceso al micrófono para dictar.'
                    : 'No se pudo reconocer la voz.';
                setTimeout(detener, 1500);
            };

            reconocimiento.onend = () => {
                // Si el navegador corta la sesión y seguimos "grabando", la reanudamos.
                if (activo?.reconocimiento === reconocimiento) {
                    try { reconocimiento.start(); } catch { detener(); }
                }
            };

            activo = { reconocimiento, boton, control, interino };
            boton.classList.add('grabando');
            boton.innerHTML = '<i class="ti ti-player-stop me-1"></i>Detener';
            interino.textContent = 'Escuchando…';
            control.focus();

            try { reconocimiento.start(); } catch { detener(); }
        });
    });

    document.querySelector('form')?.addEventListener('submit', detener);
})();
</script>
@endpush
