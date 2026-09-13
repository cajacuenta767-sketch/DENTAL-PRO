@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $odontograma->exists ? 'Editar Odontograma' : 'Nuevo Odontograma')
@section('subtitulo', $paciente->nombre_completo)

@section('contenido')
<form method="POST" id="form-odontograma"
      action="{{ $odontograma->exists ? route('admin.odontogramas.update', $odontograma) : route('admin.odontogramas.store', $paciente) }}">
    @csrf
    @if ($odontograma->exists) @method('PUT') @endif

    <input type="hidden" name="piezas" id="piezas" value="{{ old('piezas', json_encode($odontograma->piezas ?? [])) }}">

    <div class="row g-3">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-dental me-2"></i>Registro por pieza</h3>
                    <div class="ms-auto text-secondary small">
                        Elige un hallazgo y haz clic sobre la cara del diente.
                    </div>
                </div>

                <div class="card-body border-bottom">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-secondary small text-uppercase me-2">Hallazgo activo:</span>
                        @foreach (\App\Models\Odontograma::ESTADOS as $clave => $estado)
                            <button type="button" class="btn btn-sm os-hallazgo {{ $clave === 'caries' ? 'active' : '' }}"
                                    data-hallazgo="{{ $clave }}"
                                    style="border-color: {{ $estado['color'] }};">
                                <span class="leyenda-muestra me-1" style="background-color: {{ $estado['color'] }}"></span>
                                {{ $estado['etiqueta'] }}
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-os-modo="cara">
                            <i class="ti ti-square me-1"></i>Pintar cara
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-os-modo="pieza">
                            <i class="ti ti-dental me-1"></i>Marcar pieza completa
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-os-limpiar>
                            <i class="ti ti-eraser me-1"></i>Limpiar todo
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    @include('componentes.odontograma', [
                        'tipo' => old('tipo', $odontograma->tipo),
                        'piezas' => $odontograma->piezas ?? [],
                        'editable' => true,
                    ])
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del registro</h3></div>
                <div class="card-body">
                    <x-campo nombre="tipo" etiqueta="Dentición" requerido
                             ayuda="Cambiarla reinicia el mapa de piezas.">
                        <select id="tipo" name="tipo" class="form-select" required>
                            <option value="ADULTO" @selected(old('tipo', $odontograma->tipo) === 'ADULTO')>Permanente (adulto)</option>
                            <option value="INFANTIL" @selected(old('tipo', $odontograma->tipo) === 'INFANTIL')>Temporal (infantil)</option>
                        </select>
                    </x-campo>

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
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="4">{{ old('observaciones', $odontograma->observaciones) }}</textarea>
                    </x-campo>

                    <div class="alert alert-info mb-0">
                        <div class="small">
                            Piezas con hallazgo: <strong id="contador-hallazgos">0</strong>
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
(() => {
    const ESTADOS = @json(\App\Models\Odontograma::ESTADOS);
    const CARAS = @json(\App\Models\Odontograma::CARAS);

    const campoPiezas = document.getElementById('piezas');
    const contador = document.getElementById('contador-hallazgos');
    const selectorTipo = document.getElementById('tipo');

    let hallazgo = 'caries';
    let modo = 'cara';
    let mapa = leerMapa();

    function leerMapa() {
        try {
            const valor = JSON.parse(campoPiezas.value || '{}');
            return valor && typeof valor === 'object' ? valor : {};
        } catch {
            return {};
        }
    }

    function piezaPorDefecto() {
        return { estado: 'sano', caras: Object.fromEntries(CARAS.map((c) => [c, 'sano'])), nota: null };
    }

    function guardar() {
        campoPiezas.value = JSON.stringify(mapa);
        contador.textContent = Object.values(mapa).filter(
            (p) => p.estado !== 'sano' || Object.values(p.caras || {}).some((c) => c !== 'sano')
        ).length;
    }

    function pintar() {
        document.querySelectorAll('[data-pieza]').forEach((nodo) => {
            const numero = nodo.dataset.pieza;
            const pieza = mapa[numero] ?? piezaPorDefecto();

            nodo.dataset.estado = pieza.estado;
            nodo.classList.toggle('pieza-ausente', pieza.estado === 'ausente');

            nodo.querySelectorAll('[data-cara]').forEach((cara) => {
                const clave = pieza.caras?.[cara.dataset.cara] ?? 'sano';
                cara.setAttribute('fill', ESTADOS[clave]?.color ?? '#ffffff');
            });

            // Una pieza marcada por completo se resalta con su color en todas las caras.
            if (pieza.estado !== 'sano') {
                nodo.querySelectorAll('[data-cara]').forEach((cara) => {
                    cara.setAttribute('fill', ESTADOS[pieza.estado].color);
                });
            }
        });
        guardar();
    }

    // Inicializa las piezas que aún no existen en el mapa (odontograma nuevo).
    document.querySelectorAll('[data-pieza]').forEach((nodo) => {
        mapa[nodo.dataset.pieza] ??= piezaPorDefecto();
    });

    document.querySelectorAll('.os-hallazgo').forEach((boton) => {
        boton.addEventListener('click', () => {
            hallazgo = boton.dataset.hallazgo;
            document.querySelectorAll('.os-hallazgo').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');
        });
    });

    document.querySelectorAll('[data-os-modo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            modo = boton.dataset.osModo;
            document.querySelectorAll('[data-os-modo]').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');
        });
    });

    document.querySelector('[data-odontograma]').addEventListener('click', (e) => {
        const cara = e.target.closest('[data-cara]');
        const pieza = e.target.closest('[data-pieza]');
        if (!pieza) return;

        const numero = pieza.dataset.pieza;
        mapa[numero] ??= piezaPorDefecto();

        if (modo === 'pieza') {
            mapa[numero].estado = mapa[numero].estado === hallazgo ? 'sano' : hallazgo;
            if (mapa[numero].estado === 'sano') {
                mapa[numero].caras = Object.fromEntries(CARAS.map((c) => [c, 'sano']));
            }
        } else if (cara) {
            const clave = cara.dataset.cara;
            mapa[numero].estado = 'sano';
            mapa[numero].caras[clave] = mapa[numero].caras[clave] === hallazgo ? 'sano' : hallazgo;
        }

        pintar();
    });

    document.querySelector('[data-os-limpiar]').addEventListener('click', () => {
        if (!window.confirm('¿Restablecer todas las piezas a "sano"?')) return;
        Object.keys(mapa).forEach((numero) => { mapa[numero] = piezaPorDefecto(); });
        pintar();
    });

    // Cambiar de dentición recarga el formulario con el mapa en blanco correcto.
    selectorTipo.addEventListener('change', () => {
        if (!window.confirm('Cambiar la dentición reinicia el mapa de piezas. ¿Continuar?')) {
            selectorTipo.value = selectorTipo.value === 'ADULTO' ? 'INFANTIL' : 'ADULTO';
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('tipo', selectorTipo.value);
        window.location.href = url.toString();
    });

    document.querySelector('[data-os-modo="cara"]').classList.add('active');
    pintar();
})();
</script>
@endpush
@endsection
