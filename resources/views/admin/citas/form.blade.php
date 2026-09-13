@extends('layouts.admin')

@section('pretitulo', 'Administración')
@section('titulo', $cita->exists ? 'Editar Cita '.$cita->token : 'Agendar Cita')

@section('contenido')
<form method="POST" action="{{ $cita->exists ? route('admin.citas.update', $cita) : route('admin.citas.store') }}">
    @csrf
    @if ($cita->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-calendar-event me-2"></i>Datos de la cita</h3></div>
                <div class="card-body">
                    <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                        <select id="paciente_id" name="paciente_id" class="form-select" required>
                            <option value="">— Selecciona un paciente —</option>
                            @foreach ($pacientes as $paciente)
                                <option value="{{ $paciente->id }}" @selected(old('paciente_id', $cita->paciente_id) == $paciente->id)>
                                    {{ $paciente->nombre_completo }} · {{ $paciente->numero_documento }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="doctor_id" etiqueta="Doctor" requerido>
                                <select id="doctor_id" name="doctor_id" class="form-select" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach ($doctores as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id', $cita->doctor_id) == $doctor->id)>
                                            {{ $doctor->nombre_profesional }} · {{ $doctor->especialidad->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="tratamiento_id" etiqueta="Tratamiento" requerido>
                                <select id="tratamiento_id" name="tratamiento_id" class="form-select" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach ($tratamientos as $tratamiento)
                                        <option value="{{ $tratamiento->id }}"
                                                data-precio="{{ $tratamiento->precio }}"
                                                data-duracion="{{ $tratamiento->duracion }}"
                                                @selected(old('tratamiento_id', $cita->tratamiento_id) == $tratamiento->id)>
                                            {{ $tratamiento->nombre }} · {{ number_format($tratamiento->precio, 2) }} {{ $ajustes->divisa }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="fecha" etiqueta="Fecha" requerido>
                                <input type="date" id="fecha" name="fecha" class="form-control"
                                       value="{{ old('fecha', $cita->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="hora" etiqueta="Hora disponible" requerido
                                     ayuda="Se calcula con los horarios del doctor.">
                                <select id="hora" name="hora" class="form-select" required
                                        data-hora-actual="{{ old('hora', $cita->hora ? substr($cita->hora, 0, 5) : '') }}">
                                    <option value="">— Elige doctor y fecha —</option>
                                    @foreach ($horasLibres as $hora)
                                        <option value="{{ $hora }}" @selected(old('hora', substr((string) $cita->hora, 0, 5)) === $hora)>{{ $hora }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="estado" etiqueta="Estado" requerido>
                                <select id="estado" name="estado" class="form-select" required>
                                    @foreach (\App\Models\Cita::ESTADOS as $estado)
                                        <option value="{{ $estado }}" @selected(old('estado', $cita->estado) === $estado)>
                                            {{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="motivo" etiqueta="Motivo de la consulta">
                        <textarea id="motivo" name="motivo" class="form-control" rows="2">{{ old('motivo', $cita->motivo) }}</textarea>
                    </x-campo>

                    <x-campo nombre="observacion" etiqueta="Observaciones internas">
                        <textarea id="observacion" name="observacion" class="form-control" rows="2">{{ old('observacion', $cita->observacion) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resumen</h3></div>
                <div class="card-body">
                    @if ($cita->exists)
                        <div class="mb-3">
                            <div class="text-secondary small text-uppercase">Código de cita</div>
                            <div class="h2 mb-0 font-monospace">{{ $cita->token }}</div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase">Día seleccionado</div>
                        <div id="resumen-dia" class="fw-medium">—</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase">Duración estimada</div>
                        <div id="resumen-duracion" class="fw-medium">—</div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase">Precio de referencia</div>
                        <div id="resumen-precio" class="h3 mb-0 text-brand">—</div>
                    </div>

                    <x-campo nombre="origen" etiqueta="Origen de la reserva" requerido>
                        <select id="origen" name="origen" class="form-select" required>
                            <option value="RECEPCION" @selected(old('origen', $cita->origen) === 'RECEPCION')>Recepción</option>
                            <option value="ONLINE" @selected(old('origen', $cita->origen) === 'ONLINE')>En línea</option>
                        </select>
                    </x-campo>

                    <div id="aviso-horas" class="alert alert-warning d-none mb-0">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <span id="aviso-horas-texto"></span>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.citas.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $cita->exists ? 'Guardar cambios' : 'Agendar cita' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const doctor = document.getElementById('doctor_id');
    const fecha = document.getElementById('fecha');
    const hora = document.getElementById('hora');
    const tratamiento = document.getElementById('tratamiento_id');
    const aviso = document.getElementById('aviso-horas');
    const avisoTexto = document.getElementById('aviso-horas-texto');
    const citaId = @json($cita->id);
    const simbolo = @json($ajustes->divisa);

    function mostrarAviso(texto) {
        avisoTexto.textContent = texto;
        aviso.classList.remove('d-none');
    }

    function ocultarAviso() {
        aviso.classList.add('d-none');
    }

    async function cargarHoras() {
        if (!doctor.value || !fecha.value) return;

        const seleccionada = hora.dataset.horaActual || hora.value;
        hora.innerHTML = '<option value="">Consultando disponibilidad…</option>';
        hora.disabled = true;

        const url = new URL(@json(route('admin.citas.horas')), window.location.origin);
        url.searchParams.set('doctor_id', doctor.value);
        url.searchParams.set('fecha', fecha.value);
        if (citaId) url.searchParams.set('cita_id', citaId);

        try {
            const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!respuesta.ok) throw new Error('respuesta no válida');
            const datos = await respuesta.json();

            document.getElementById('resumen-dia').textContent = datos.dia || '—';

            hora.innerHTML = '';

            if (!datos.horas.length) {
                hora.innerHTML = '<option value="">Sin cupos disponibles</option>';
                mostrarAviso('El doctor no tiene cupos libres ese día. Elige otra fecha u otro profesional.');
            } else {
                ocultarAviso();
                hora.insertAdjacentHTML('beforeend', '<option value="">— Selecciona una hora —</option>');
                datos.horas.forEach((h) => {
                    const opcion = document.createElement('option');
                    opcion.value = h;
                    opcion.textContent = h;
                    if (h === seleccionada) opcion.selected = true;
                    hora.appendChild(opcion);
                });
            }
        } catch (e) {
            hora.innerHTML = '<option value="">No se pudo consultar la agenda</option>';
            mostrarAviso('No pudimos consultar la disponibilidad. Revisa tu conexión e intenta de nuevo.');
        } finally {
            hora.disabled = false;
            hora.dataset.horaActual = '';
        }
    }

    function actualizarResumen() {
        const opcion = tratamiento.selectedOptions[0];
        if (!opcion || !opcion.value) {
            document.getElementById('resumen-precio').textContent = '—';
            document.getElementById('resumen-duracion').textContent = '—';
            return;
        }
        document.getElementById('resumen-precio').textContent =
            `${Number(opcion.dataset.precio).toFixed(2)} ${simbolo}`;
        document.getElementById('resumen-duracion').textContent = `${opcion.dataset.duracion} minutos`;
    }

    doctor.addEventListener('change', cargarHoras);
    fecha.addEventListener('change', cargarHoras);
    tratamiento.addEventListener('change', actualizarResumen);

    actualizarResumen();
    if (doctor.value && fecha.value) cargarHoras();
})();
</script>
@endpush
@endsection
