@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Reservar una cita')

@push('head')
<style>
    #cupos .btn { min-width: 5.5rem; }
    .os-resumen { border-left: 4px solid var(--tblr-primary); }
</style>
@endpush

@section('acciones')
    <a href="{{ route('portal.citas') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Mis citas
    </a>
@endsection

@section('contenido')
@if ($confirmada)
    <div class="card mb-3 border-success">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <span class="avatar avatar-lg bg-green-lt text-green"><i class="ti ti-circle-check fs-1"></i></span>
                </div>
                <div class="col">
                    <h3 class="mb-1">¡Tu cita quedó reservada!</h3>
                    <div class="text-secondary">
                        <strong>{{ $confirmada->fecha_hora }}</strong>
                        · {{ $confirmada->tratamiento?->nombre }}
                        · {{ $confirmada->doctor?->nombre_profesional }}
                        @if ($confirmada->doctor?->especialidad) ({{ $confirmada->doctor->especialidad->nombre }}) @endif
                    </div>
                    <div class="text-secondary small mt-1">
                        Código <code>{{ $confirmada->token }}</code>. La reserva queda <strong>pendiente de confirmación</strong>
                        por la clínica; te avisaremos por correo.
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <a href="{{ route('portal.citas') }}" class="btn btn-success w-100">
                        <i class="ti ti-calendar-event me-1"></i>Ver mis citas
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('portal.reservar.guardar') }}" id="form-reserva">
    @csrf

    <div class="row g-3">
        <div class="col-lg-8">
            {{-- Paso 1: qué necesitas --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="badge bg-primary me-2">1</span>¿Qué necesitas?</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="especialidad_id" etiqueta="Especialidad" requerido>
                                <select id="especialidad_id" class="form-select" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach ($especialidades as $especialidad)
                                        <option value="{{ $especialidad->id }}">{{ $especialidad->nombre }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="tratamiento_id" etiqueta="Motivo de consulta" requerido>
                                <select id="tratamiento_id" name="tratamiento_id" class="form-select" required disabled>
                                    <option value="">— Elige una especialidad —</option>
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="doctor_id" etiqueta="Profesional" requerido>
                                <select id="doctor_id" name="doctor_id" class="form-select" required disabled>
                                    <option value="">— Elige una especialidad —</option>
                                </select>
                            </x-campo>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paso 2: cuándo --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="badge bg-primary me-2">2</span>¿Cuándo te viene bien?</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="fecha" etiqueta="Fecha" requerido>
                                <input type="date" id="fecha" name="fecha" class="form-control"
                                       min="{{ $minimo }}" max="{{ $maximo }}"
                                       value="{{ old('fecha') }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Horarios disponibles</label>
                            <input type="hidden" name="hora" id="hora" value="{{ old('hora') }}" required>
                            <div id="cupos" class="d-flex flex-wrap gap-2">
                                <span class="text-secondary">Elige profesional y fecha para ver los cupos libres.</span>
                            </div>
                            @error('hora')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('fecha')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <x-campo nombre="motivo" etiqueta="¿Algo que debamos saber?" class="mb-0">
                        <textarea id="motivo" name="motivo" class="form-control" rows="2"
                                  placeholder="Dolor, urgencia, alergias, preferencia de horario…">{{ old('motivo') }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card os-resumen sticky-top" style="top: 5rem;">
                <div class="card-body">
                    <div class="text-secondary small text-uppercase">Resumen de tu cita</div>
                    <div id="resumen-reserva" class="fw-medium my-2">Completa los pasos anteriores.</div>
                    <div class="text-secondary small mb-3">
                        <i class="ti ti-user me-1"></i>{{ $paciente->nombre_completo ?? ($paciente->nombres.' '.$paciente->apellidos) }}
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="boton-reservar" disabled>
                        <i class="ti ti-calendar-check me-1"></i>Confirmar reserva
                    </button>
                    <p class="text-secondary small mt-3 mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        Puedes reservar entre el {{ \Illuminate\Support\Carbon::parse($minimo)->format('d/m/Y') }}
                        y el {{ \Illuminate\Support\Carbon::parse($maximo)->format('d/m/Y') }}.
                        La clínica confirmará tu cita y te avisará por correo.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const especialidad = document.getElementById('especialidad_id');
    const tratamiento = document.getElementById('tratamiento_id');
    const doctor = document.getElementById('doctor_id');
    const fecha = document.getElementById('fecha');
    const hora = document.getElementById('hora');
    const cupos = document.getElementById('cupos');
    const boton = document.getElementById('boton-reservar');
    const resumen = document.getElementById('resumen-reserva');

    const urlOpciones = @json(route('portal.reservar.opciones'));
    const urlHoras = @json(route('portal.reservar.horas'));

    function mensajeCupos(texto) {
        cupos.innerHTML = `<span class="text-secondary">${texto}</span>`;
    }

    function actualizarResumen() {
        const partes = [];
        if (tratamiento.value) partes.push(tratamiento.selectedOptions[0].textContent.trim());
        if (doctor.value) partes.push('con ' + doctor.selectedOptions[0].textContent.trim());
        if (fecha.value && hora.value) {
            const d = new Date(`${fecha.value}T00:00:00`);
            partes.push('el ' + d.toLocaleDateString('es', { day: '2-digit', month: 'long' }) + ' a las ' + hora.value);
        }

        resumen.textContent = partes.length ? partes.join(' ') : 'Completa los pasos anteriores.';
        boton.disabled = !(tratamiento.value && doctor.value && fecha.value && hora.value);
    }

    especialidad.addEventListener('change', async () => {
        tratamiento.disabled = doctor.disabled = true;
        tratamiento.innerHTML = '<option value="">Cargando…</option>';
        doctor.innerHTML = '<option value="">Cargando…</option>';

        if (!especialidad.value) return;

        try {
            const url = new URL(urlOpciones, window.location.origin);
            url.searchParams.set('especialidad_id', especialidad.value);
            const datos = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();

            tratamiento.innerHTML = '<option value="">— Selecciona —</option>' +
                datos.tratamientos.map((t) => `<option value="${t.id}">${t.nombre} (${t.duracion} min)</option>`).join('');
            doctor.innerHTML = '<option value="">— Selecciona —</option>' +
                datos.doctores.map((d) => `<option value="${d.id}">${d.nombre}</option>`).join('');

            tratamiento.disabled = doctor.disabled = false;
        } catch {
            tratamiento.innerHTML = '<option value="">No disponible</option>';
            doctor.innerHTML = '<option value="">No disponible</option>';
        }

        hora.value = '';
        mensajeCupos('Elige profesional y fecha para ver los cupos libres.');
        actualizarResumen();
    });

    async function cargarCupos() {
        if (!doctor.value || !fecha.value) return;

        hora.value = '';
        mensajeCupos('Consultando disponibilidad…');

        try {
            const url = new URL(urlHoras, window.location.origin);
            url.searchParams.set('doctor_id', doctor.value);
            url.searchParams.set('fecha', fecha.value);
            if (tratamiento.value) url.searchParams.set('tratamiento_id', tratamiento.value);
            const datos = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();

            if (!datos.horas.length) {
                mensajeCupos(`Sin cupos libres el ${datos.dia.toLowerCase()}. Prueba con otra fecha.`);
            } else {
                cupos.innerHTML = datos.horas.map((h) =>
                    `<button type="button" class="btn btn-outline-primary" data-hora="${h}">${h}</button>`
                ).join('');

                cupos.querySelectorAll('[data-hora]').forEach((b) => {
                    b.addEventListener('click', () => {
                        cupos.querySelectorAll('[data-hora]').forEach((x) => {
                            x.classList.remove('btn-primary');
                            x.classList.add('btn-outline-primary');
                        });
                        b.classList.remove('btn-outline-primary');
                        b.classList.add('btn-primary');
                        hora.value = b.dataset.hora;
                        actualizarResumen();
                    });
                });
            }
        } catch {
            mensajeCupos('No pudimos consultar la agenda. Intenta de nuevo.');
        }

        actualizarResumen();
    }

    doctor.addEventListener('change', () => { cargarCupos(); actualizarResumen(); });
    fecha.addEventListener('change', cargarCupos);
    tratamiento.addEventListener('change', actualizarResumen);
    tratamiento.addEventListener('change', cargarCupos);
})();
</script>
@endpush
@endsection
