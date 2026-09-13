@extends('layouts.publico')

@section('titulo', 'Reserva tu cita')

@section('contenido')
<nav class="navbar py-3" style="background: #0b3b38;">
    <div class="container-xl">
        <span class="navbar-brand d-flex align-items-center gap-2 text-white mb-0">
            <span class="avatar avatar-sm bg-brand"><i class="ti ti-dental"></i></span>
            <span class="fw-bold fs-3">{{ $ajustes->nombre }}</span>
        </span>
        @if ($ajustes->telefono)
            <span class="text-white-50 small"><i class="ti ti-phone me-1"></i>{{ $ajustes->telefono }}</span>
        @endif
    </div>
</nav>

<section class="os-hero py-5">
    <div class="container-xl">
        <span class="os-eyebrow"><i class="ti ti-calendar-plus"></i> Reserva en línea</span>
        <h1 class="mt-3 text-white">Agenda tu cita <span class="text-brand">en un minuto.</span></h1>
        <p class="fs-3 text-white-50 mb-0" style="max-width: 42rem;">
            {{ $ajustes->reservas_mensaje ?: 'Elige la especialidad, el profesional y el horario que mejor te acomode. Te confirmamos por correo.' }}
        </p>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                @include('componentes.alertas')

                <form method="POST" action="{{ route('reservas.guardar', $token) }}" id="form-reserva">
                    @csrf

                    {{-- Paso 1: qué necesitas --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><span class="badge bg-brand me-2">1</span>¿Qué necesitas?</h3>
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
                            <h3 class="card-title"><span class="badge bg-brand me-2">2</span>¿Cuándo te viene bien?</h3>
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
                        </div>
                    </div>

                    {{-- Paso 3: tus datos --}}
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title"><span class="badge bg-brand me-2">3</span>Tus datos</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <x-campo nombre="nombres" etiqueta="Nombres" requerido>
                                        <input type="text" id="nombres" name="nombres" class="form-control"
                                               value="{{ old('nombres') }}" required>
                                    </x-campo>
                                </div>
                                <div class="col-md-6">
                                    <x-campo nombre="apellidos" etiqueta="Apellidos" requerido>
                                        <input type="text" id="apellidos" name="apellidos" class="form-control"
                                               value="{{ old('apellidos') }}" required>
                                    </x-campo>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <x-campo nombre="tipo_documento" etiqueta="Tipo doc." requerido>
                                        <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                            @foreach (['CI', 'DNI', 'PASAPORTE', 'CE'] as $tipo)
                                                <option value="{{ $tipo }}" @selected(old('tipo_documento') === $tipo)>{{ $tipo }}</option>
                                            @endforeach
                                        </select>
                                    </x-campo>
                                </div>
                                <div class="col-md-4">
                                    <x-campo nombre="numero_documento" etiqueta="N° de documento" requerido>
                                        <input type="text" id="numero_documento" name="numero_documento" class="form-control"
                                               value="{{ old('numero_documento') }}" required>
                                    </x-campo>
                                </div>
                                <div class="col-md-5">
                                    <x-campo nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento">
                                        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                               max="{{ now()->toDateString() }}" value="{{ old('fecha_nacimiento') }}">
                                    </x-campo>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-5">
                                    <x-campo nombre="telefono" etiqueta="Teléfono" requerido>
                                        <input type="text" id="telefono" name="telefono" class="form-control"
                                               value="{{ old('telefono') }}" required>
                                    </x-campo>
                                </div>
                                <div class="col-md-7">
                                    <x-campo nombre="email" etiqueta="Correo electrónico" requerido
                                             ayuda="Te enviamos la confirmación con tu código de cita.">
                                        <input type="email" id="email" name="email" class="form-control"
                                               value="{{ old('email') }}" required>
                                    </x-campo>
                                </div>
                            </div>

                            <x-campo nombre="motivo" etiqueta="¿Algo que debamos saber?">
                                <textarea id="motivo" name="motivo" class="form-control" rows="2">{{ old('motivo') }}</textarea>
                            </x-campo>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <div class="flex-fill">
                                <div class="text-secondary small text-uppercase">Resumen de tu cita</div>
                                <div id="resumen-reserva" class="fw-medium">Completa los pasos anteriores.</div>
                            </div>
                            <button type="submit" class="btn btn-brand btn-lg" id="boton-reservar" disabled>
                                <i class="ti ti-calendar-check me-1"></i>Confirmar reserva
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<footer class="py-4" style="background: #0b3b38; color: #cbd5e1;">
    <div class="container-xl small">
        &copy; {{ date('Y') }} {{ $ajustes->nombre }}
        @if ($ajustes->direccion) · {{ $ajustes->direccion }} @endif
    </div>
</footer>

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

    const urlOpciones = @json(route('reservas.opciones', $token));
    const urlHoras = @json(route('reservas.horas', $token));

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
})();
</script>
@endpush
@endsection
