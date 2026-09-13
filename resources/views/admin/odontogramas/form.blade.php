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
        <div class="col-xl-9">
            <div class="card">
                {{-- Barra de capas y dentición, como en DentalAdmin --}}
                <div class="card-header flex-wrap gap-2">
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

                {{-- Paleta de hallazgos --}}
                <div class="card-body border-bottom py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="text-secondary small text-uppercase me-1">Hallazgo:</span>
                        @foreach (\App\Models\Odontograma::ESTADOS as $clave => $estado)
                            @continue($clave === 'sano')
                            <button type="button" class="btn btn-sm os-hallazgo {{ $clave === 'caries' ? 'active' : '' }}"
                                    data-hallazgo="{{ $clave }}" data-capa="{{ $estado['capa'] }}"
                                    style="border-color: {{ $estado['color'] }};">
                                <span class="leyenda-muestra me-1" style="background-color: {{ $estado['color'] }}"></span>
                                {{ $estado['etiqueta'] }}
                            </button>
                        @endforeach
                        <button type="button" class="btn btn-sm os-hallazgo" data-hallazgo="sano" data-capa="ninguna">
                            <i class="ti ti-eraser me-1"></i>Borrar
                        </button>
                    </div>

                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <div class="btn-group" role="group" aria-label="Modo de marcado">
                            <button type="button" class="btn btn-sm active" data-os-modo="cara">
                                <i class="ti ti-square me-1"></i>Pintar cara
                            </button>
                            <button type="button" class="btn btn-sm" data-os-modo="pieza">
                                <i class="ti ti-dental me-1"></i>Pieza completa
                            </button>
                        </div>
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

        <div class="col-xl-3">
            {{-- Panel lateral de la pieza seleccionada --}}
            <div class="card mb-3" id="panel-pieza">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="ti ti-dental me-2"></i>Pieza <span data-panel-numero>—</span>
                    </h3>
                </div>
                <div class="card-body" data-panel-vacio>
                    <div class="text-secondary small text-center py-3">
                        Haz clic en una pieza para ver y editar su detalle.
                    </div>
                </div>
                <div class="card-body d-none" data-panel-contenido>
                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase mb-1">Estado de la pieza</div>
                        <select class="form-select form-select-sm" data-panel-estado>
                            @foreach (\App\Models\Odontograma::ESTADOS as $clave => $estado)
                                <option value="{{ $clave }}">{{ $estado['etiqueta'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase mb-1">Caras</div>
                        <div class="list-group list-group-flush" data-panel-caras></div>
                    </div>

                    <div>
                        <div class="text-secondary small text-uppercase mb-1">Nota de la pieza</div>
                        <textarea class="form-control form-control-sm" rows="2" maxlength="255"
                                  data-panel-nota placeholder="Observación breve"></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del registro</h3></div>
                <div class="card-body">
                    <input type="hidden" name="tipo" id="tipo" value="{{ old('tipo', $odontograma->tipo) }}">

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
                        <div class="col-6">
                            <div class="card card-sm">
                                <div class="card-body py-2">
                                    <div class="h2 mb-0 text-danger" id="contador-evaluacion">0</div>
                                    <div class="text-secondary" style="font-size: .7rem;">EVALUACIÓN</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card card-sm">
                                <div class="card-body py-2">
                                    <div class="h2 mb-0 text-primary" id="contador-ejecucion">0</div>
                                    <div class="text-secondary" style="font-size: .7rem;">EJECUCIÓN</div>
                                </div>
                            </div>
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
    const campoTipo = document.getElementById('tipo');
    const lienzo = document.querySelector('[data-odontograma]');

    const panel = {
        numero: document.querySelector('[data-panel-numero]'),
        vacio: document.querySelector('[data-panel-vacio]'),
        contenido: document.querySelector('[data-panel-contenido]'),
        estado: document.querySelector('[data-panel-estado]'),
        caras: document.querySelector('[data-panel-caras]'),
        nota: document.querySelector('[data-panel-nota]'),
    };

    let hallazgo = 'caries';
    let modo = 'cara';
    let capa = 'todos';
    let seleccionada = null;
    let mapa = leerMapa();

    function leerMapa() {
        try {
            const v = JSON.parse(campoPiezas.value || '{}');
            return v && typeof v === 'object' ? v : {};
        } catch { return {}; }
    }

    const enBlanco = () => ({
        estado: 'sano',
        caras: Object.fromEntries(CARAS.map((c) => [c, 'sano'])),
        nota: null,
    });

    /** Un hallazgo se oculta si su capa no es la visible. */
    const visible = (clave) =>
        clave === 'sano' || capa === 'todos' || ESTADOS[clave]?.capa === capa;

    const color = (clave) => (visible(clave) ? ESTADOS[clave]?.color : '#ffffff') ?? '#ffffff';

    function guardar() {
        campoPiezas.value = JSON.stringify(mapa);

        const conteo = { evaluacion: 0, ejecucion: 0 };

        Object.values(mapa).forEach((pieza) => {
            const claves = [...Object.values(pieza.caras ?? {}), pieza.estado];
            ['evaluacion', 'ejecucion'].forEach((c) => {
                if (claves.some((k) => ESTADOS[k]?.capa === c)) conteo[c]++;
            });
        });

        document.getElementById('contador-evaluacion').textContent = conteo.evaluacion;
        document.getElementById('contador-ejecucion').textContent = conteo.ejecucion;
    }

    function pintar() {
        document.querySelectorAll('[data-pieza]').forEach((nodo) => {
            const pieza = mapa[nodo.dataset.pieza] ?? enBlanco();

            nodo.dataset.estado = pieza.estado;
            nodo.classList.toggle('pieza-ausente', pieza.estado === 'ausente' && visible('ausente'));
            nodo.classList.toggle('pieza-activa', nodo.dataset.pieza === seleccionada);

            nodo.querySelectorAll('[data-cara]').forEach((cara) => {
                // El estado de la pieza completa manda sobre el de cada cara.
                const clave = pieza.estado !== 'sano' ? pieza.estado : (pieza.caras?.[cara.dataset.cara] ?? 'sano');
                cara.setAttribute('fill', color(clave));
            });
        });

        guardar();
    }

    // --- Panel lateral -----------------------------------------------------

    function abrirPanel(numero) {
        seleccionada = numero;
        const pieza = (mapa[numero] ??= enBlanco());

        panel.numero.textContent = numero;
        panel.vacio.classList.add('d-none');
        panel.contenido.classList.remove('d-none');
        panel.estado.value = pieza.estado;
        panel.nota.value = pieza.nota ?? '';

        panel.caras.innerHTML = CARAS.map((cara) => `
            <div class="list-group-item px-0 py-1 d-flex align-items-center gap-2">
                <span class="leyenda-muestra" style="background-color: ${color(pieza.caras[cara])}"></span>
                <span class="flex-fill text-capitalize small">${cara}</span>
                <select class="form-select form-select-sm w-auto" data-cara-select="${cara}">
                    ${Object.entries(ESTADOS).map(([k, e]) =>
                        `<option value="${k}" ${pieza.caras[cara] === k ? 'selected' : ''}>${e.etiqueta}</option>`
                    ).join('')}
                </select>
            </div>
        `).join('');

        panel.caras.querySelectorAll('[data-cara-select]').forEach((select) => {
            select.addEventListener('change', () => {
                mapa[numero].caras[select.dataset.caraSelect] = select.value;
                pintar();
                abrirPanel(numero);
            });
        });

        pintar();
    }

    panel.estado.addEventListener('change', () => {
        if (!seleccionada) return;
        mapa[seleccionada].estado = panel.estado.value;
        if (panel.estado.value === 'sano') {
            mapa[seleccionada].caras = Object.fromEntries(CARAS.map((c) => [c, 'sano']));
        }
        pintar();
        abrirPanel(seleccionada);
    });

    panel.nota.addEventListener('input', () => {
        if (!seleccionada) return;
        mapa[seleccionada].nota = panel.nota.value || null;
        guardar();
    });

    // --- Interacción con el mapa -------------------------------------------

    document.querySelectorAll('[data-pieza]').forEach((n) => { mapa[n.dataset.pieza] ??= enBlanco(); });

    document.querySelectorAll('.os-hallazgo').forEach((boton) => {
        boton.addEventListener('click', () => {
            hallazgo = boton.dataset.hallazgo;
            document.querySelectorAll('.os-hallazgo').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');

            // Elegir un hallazgo de otra capa muestra esa capa automáticamente.
            const suCapa = boton.dataset.capa;
            if (capa !== 'todos' && suCapa !== 'ninguna' && suCapa !== capa) {
                document.querySelector(`[data-os-capa="${suCapa}"]`)?.click();
            }
        });
    });

    document.querySelectorAll('[data-os-modo]').forEach((boton) => {
        boton.addEventListener('click', () => {
            modo = boton.dataset.osModo;
            document.querySelectorAll('[data-os-modo]').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');
        });
    });

    document.querySelectorAll('[data-os-capa]').forEach((boton) => {
        boton.addEventListener('click', () => {
            capa = boton.dataset.osCapa;
            document.querySelectorAll('[data-os-capa]').forEach((b) => b.classList.remove('active'));
            boton.classList.add('active');
            pintar();
            if (seleccionada) abrirPanel(seleccionada);
        });
    });

    lienzo.addEventListener('click', (e) => {
        const nodoPieza = e.target.closest('[data-pieza]');
        if (!nodoPieza) return;

        const numero = nodoPieza.dataset.pieza;
        const cara = e.target.closest('[data-cara]');
        mapa[numero] ??= enBlanco();

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

        abrirPanel(numero);
    });

    document.querySelector('[data-os-limpiar]').addEventListener('click', () => {
        if (!window.confirm('¿Restablecer todas las piezas a "sano"?')) return;
        Object.keys(mapa).forEach((n) => { mapa[n] = enBlanco(); });
        if (seleccionada) abrirPanel(seleccionada); else pintar();
    });

    // Cambiar de dentición recarga con el mapa en blanco de esa numeración.
    document.querySelectorAll('[data-os-denticion]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const nuevo = boton.dataset.osDenticion;
            if (nuevo === campoTipo.value) return;
            if (!window.confirm('Cambiar la dentición reinicia el mapa de piezas. ¿Continuar?')) return;

            const url = new URL(window.location.href);
            url.searchParams.set('tipo', nuevo);
            window.location.href = url.toString();
        });
    });

    pintar();
})();
</script>
@endpush
@endsection
