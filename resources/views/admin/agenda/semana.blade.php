@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', 'Agenda semanal')
@section('subtitulo', 'Semana del '.$lunes->format('d/m').' al '.$fin->format('d/m/Y').' · '.$totalCitas.' citas')

@push('head')
<style>
    .semana-grilla { display: grid; grid-template-columns: 4.5rem repeat(var(--dias), minmax(9rem, 1fr)); min-width: 100%; }
    .semana-cabecera { position: sticky; top: 0; z-index: 3; background: var(--tblr-bg-surface); border-bottom: 1px solid var(--tblr-border-color); }
    .semana-cabecera a { display: block; padding: .5rem .25rem; text-align: center; color: inherit; text-decoration: none; }
    .semana-cabecera a:hover { background: var(--tblr-bg-surface-secondary); }
    .semana-cabecera .es-hoy { color: var(--tblr-primary); font-weight: 600; }
    .semana-horas .franja-etiqueta { height: var(--alto); font-size: .75rem; color: var(--tblr-secondary); text-align: right; padding-right: .5rem; border-top: 1px solid var(--tblr-border-color-translucent); }
    .semana-dia { position: relative; border-left: 1px solid var(--tblr-border-color); }
    .semana-dia .franja { height: var(--alto); border-top: 1px solid var(--tblr-border-color-translucent); }
    .semana-dia .franja.hora-en-punto { border-top-color: var(--tblr-border-color); }
    .semana-dia .franja.soltable { background: rgba(var(--tblr-primary-rgb), .12); outline: 2px dashed var(--tblr-primary); outline-offset: -2px; }
    .semana-dia .franja.creable { cursor: cell; }
    .semana-dia .franja.creable:hover { background: var(--tblr-bg-surface-secondary); }
    .semana-dia.es-hoy { background: rgba(var(--tblr-primary-rgb), .03); }
    .cita-tarjeta { position: absolute; z-index: 2; overflow: hidden; border-radius: .375rem; border-left: 4px solid var(--tblr-secondary); background: var(--tblr-bg-surface); box-shadow: 0 1px 3px rgba(0, 0, 0, .12); padding: .25rem .4rem; font-size: .75rem; line-height: 1.2; }
    .cita-tarjeta.arrastrable { cursor: grab; }
    .cita-tarjeta.arrastrable:active { cursor: grabbing; }
    .cita-tarjeta.en-vuelo { opacity: .4; }
    .cita-tarjeta .paciente { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; color: inherit; }
    .cita-tarjeta .tratamiento { color: var(--tblr-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .semana-grilla.arrastrando .cita-tarjeta { pointer-events: none; }
    @foreach (\App\Models\Cita::COLORES_ESTADO as $estado => $color)
    .cita-tarjeta.estado-{{ $estado }} { border-left-color: var(--tblr-{{ $color }}); }
    @endforeach
    .cita-tarjeta.estado-CANCELADA { opacity: .55; text-decoration: line-through; }
    #aviso-agenda { position: fixed; right: 1rem; bottom: 1rem; z-index: 1080; max-width: 24rem; }
</style>
@endpush

@section('acciones')
    <div class="btn-list">
        <div class="btn-group">
            <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-outline-primary">Día</a>
            <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-primary">Semana</a>
            <a href="{{ route('admin.agenda.mes', ['doctor_id' => $doctor?->id, 'fecha' => $fecha]) }}" class="btn btn-outline-primary">Mes</a>
        </div>
        @can('citas.crear')
            <a href="{{ route('admin.citas.create', ['doctor_id' => $doctor?->id]) }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Crear turno
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            @if ($puedeVerTodas)
                <div class="col-md-5">
                    <label class="form-label">Profesional</label>
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">— Toda la clínica —</option>
                        @foreach ($doctores as $d)
                            <option value="{{ $d->id }}" @selected($doctor?->id === $d->id)>
                                {{ $d->nombre_profesional }} · {{ $d->especialidad->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <label class="form-label">Semana de</label>
                <input type="date" name="fecha" value="{{ $fecha }}" class="form-control" onchange="this.form.submit()">
            </div>
            <div class="col-md-4">
                <div class="btn-list">
                    <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => $lunes->subWeek()->toDateString()]) }}"
                       class="btn btn-outline-secondary" title="Semana anterior"><i class="ti ti-chevron-left"></i></a>
                    <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => now()->toDateString()]) }}"
                       class="btn {{ $esSemanaActual ? 'btn-primary' : 'btn-outline-primary' }}">Hoy</a>
                    <a href="{{ route('admin.agenda.semana', ['doctor_id' => $doctor?->id, 'fecha' => $lunes->addWeek()->toDateString()]) }}"
                       class="btn btn-outline-secondary" title="Semana siguiente"><i class="ti ti-chevron-right"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

@php
    $puedeArrastrar = auth()->user()->can('citas.editar');
    $puedeCrear = $doctor && auth()->user()->can('citas.crear');
@endphp

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-calendar-week me-2"></i>{{ $doctor ? $doctor->nombre_profesional : 'Todos los profesionales' }}
        </h3>
        <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
            @foreach (\App\Models\Cita::COLORES_ESTADO as $estado => $color)
                <span class="badge bg-{{ $color }}-lt">{{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}</span>
            @endforeach
        </div>
    </div>
    <div class="card-body p-0" style="overflow-x: auto;">
        <div id="semana-grilla" class="semana-grilla"
             style="--dias: {{ $dias->count() }}; --alto: {{ $altoFranja }}px;"
             data-url-reprogramar="{{ route('admin.citas.reprogramar', ['cita' => '__ID__']) }}"
             data-url-crear="{{ $puedeCrear ? route('admin.citas.create', ['doctor_id' => $doctor->id]) : '' }}"
             data-hora-minima="{{ $horaMinima }}" data-intervalo="{{ $intervalo }}" data-alto="{{ $altoFranja }}">

            {{-- Cabecera --}}
            <div class="semana-cabecera"></div>
            @foreach ($dias as $dia)
                <div class="semana-cabecera">
                    <a href="{{ route('admin.agenda.index', ['doctor_id' => $doctor?->id, 'fecha' => $dia->toDateString()]) }}"
                       class="{{ $dia->isToday() ? 'es-hoy' : '' }}" title="Ver agenda del día">
                        <div class="small text-uppercase">{{ config('odontosuite.dias_semana.'.\App\Models\Horario::DIAS[$dia->dayOfWeekIso - 1]) }}</div>
                        <div class="fs-3">{{ $dia->format('d') }}</div>
                    </a>
                </div>
            @endforeach

            {{-- Columna de horas --}}
            <div class="semana-horas">
                @foreach ($franjas as $franja)
                    <div class="franja-etiqueta">{{ \Illuminate\Support\Str::endsWith($franja, ':00') || $intervalo >= 30 ? $franja : '' }}</div>
                @endforeach
            </div>

            {{-- Columnas de días --}}
            @foreach ($dias as $dia)
                @php $clave = $dia->toDateString(); @endphp
                <div class="semana-dia {{ $dia->isToday() ? 'es-hoy' : '' }}" data-fecha="{{ $clave }}">
                    @foreach ($franjas as $franja)
                        <div class="franja {{ \Illuminate\Support\Str::endsWith($franja, ':00') ? 'hora-en-punto' : '' }} {{ $puedeCrear ? 'creable' : '' }}"
                             data-fecha="{{ $clave }}" data-hora="{{ $franja }}"
                             title="{{ $puedeCrear ? 'Doble clic para agendar a las '.$franja : '' }}"></div>
                    @endforeach

                    @foreach ($porDia->get($clave, collect()) as $p)
                        @php
                            $cita = $p['cita'];
                            $arrastrable = $puedeArrastrar && ! in_array($cita->estado, ['COMPLETADA', 'CANCELADA'], true);
                            $ancho = 100 / $p['carriles'];
                        @endphp
                        <div class="cita-tarjeta estado-{{ $cita->estado }} {{ $arrastrable ? 'arrastrable' : '' }}"
                             draggable="{{ $arrastrable ? 'true' : 'false' }}"
                             data-id="{{ $cita->id }}" data-duracion="{{ $cita->duracion_minutos }}"
                             style="top: {{ $p['top'] }}px; height: {{ $p['alto'] }}px; left: calc({{ $p['carril'] * $ancho }}% + 2px); width: calc({{ $ancho }}% - 4px);"
                             title="{{ substr($cita->hora, 0, 5) }} · {{ $cita->paciente->nombre_completo }} · {{ $cita->tratamiento->nombre }} ({{ $cita->duracion_minutos }} min){{ $doctor ? '' : ' · '.$cita->doctor->nombre_profesional }}">
                            <div class="d-flex justify-content-between align-items-center gap-1">
                                <span class="font-monospace hora">{{ substr($cita->hora, 0, 5) }}</span>
                                <span class="badge bg-{{ $cita->color_estado }}-lt" style="font-size: .6rem;">{{ $cita->estado_legible }}</span>
                            </div>
                            <a href="{{ route('admin.citas.show', $cita) }}" class="paciente" draggable="false">{{ $cita->paciente->nombre_completo }}</a>
                            <div class="tratamiento">{{ $cita->tratamiento->nombre }}@unless ($doctor) · {{ $cita->doctor->nombre_profesional }}@endunless</div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
    <div class="card-footer text-secondary small">
        @if ($puedeArrastrar)
            <i class="ti ti-hand-move me-1"></i>Arrastra una cita a otra franja para reprogramarla.
        @endif
        @if ($puedeCrear)
            <i class="ti ti-pointer me-1 {{ $puedeArrastrar ? 'ms-3' : '' }}"></i>Doble clic en una franja libre para agendar.
        @elseif (auth()->user()->can('citas.crear'))
            <i class="ti ti-info-circle me-1 {{ $puedeArrastrar ? 'ms-3' : '' }}"></i>Elige un profesional para agendar con doble clic.
        @endif
    </div>
</div>

<div id="aviso-agenda"></div>
@endsection

@push('scripts')
<script>
(() => {
    const grilla = document.getElementById('semana-grilla');
    if (!grilla) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const urlReprogramar = grilla.dataset.urlReprogramar;
    const urlCrear = grilla.dataset.urlCrear;
    const horaMinima = Number(grilla.dataset.horaMinima);
    const intervalo = Number(grilla.dataset.intervalo);
    const alto = Number(grilla.dataset.alto);
    const avisos = document.getElementById('aviso-agenda');

    let enVuelo = null;

    function avisar(texto, tipo = 'danger') {
        const alerta = document.createElement('div');
        alerta.className = `alert alert-${tipo} alert-dismissible shadow mb-2`;
        alerta.innerHTML = `<div>${texto}</div><a class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></a>`;
        avisos.appendChild(alerta);
        setTimeout(() => alerta.remove(), 6000);
    }

    function minutos(hora) {
        const [h, m] = hora.split(':').map(Number);
        return h * 60 + m;
    }

    function colocar(tarjeta, columna, hora) {
        columna.appendChild(tarjeta);
        tarjeta.style.top = `${((minutos(hora) - horaMinima) / intervalo) * alto}px`;
        tarjeta.style.left = '2px';
        tarjeta.style.width = 'calc(100% - 4px)';
        tarjeta.querySelector('.hora').textContent = hora;
    }

    grilla.querySelectorAll('.cita-tarjeta[draggable="true"]').forEach((tarjeta) => {
        tarjeta.addEventListener('dragstart', (e) => {
            enVuelo = tarjeta;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', tarjeta.dataset.id);
            tarjeta.classList.add('en-vuelo');
            grilla.classList.add('arrastrando');
        });
        tarjeta.addEventListener('dragend', () => {
            tarjeta.classList.remove('en-vuelo');
            grilla.classList.remove('arrastrando');
            grilla.querySelectorAll('.franja.soltable').forEach((f) => f.classList.remove('soltable'));
            enVuelo = null;
        });
    });

    grilla.querySelectorAll('.franja').forEach((franja) => {
        franja.addEventListener('dragover', (e) => {
            if (!enVuelo) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            franja.classList.add('soltable');
        });
        franja.addEventListener('dragleave', () => franja.classList.remove('soltable'));
        franja.addEventListener('drop', async (e) => {
            e.preventDefault();
            franja.classList.remove('soltable');
            if (!enVuelo) return;

            const tarjeta = enVuelo;
            const columna = franja.parentElement;
            const origen = { columna: tarjeta.parentElement, top: tarjeta.style.top, left: tarjeta.style.left, width: tarjeta.style.width, hora: tarjeta.querySelector('.hora').textContent };
            const destino = { fecha: franja.dataset.fecha, hora: franja.dataset.hora };

            if (origen.columna === columna && origen.hora === destino.hora) return;

            colocar(tarjeta, columna, destino.hora);
            tarjeta.classList.add('en-vuelo');

            try {
                const respuesta = await fetch(urlReprogramar.replace('__ID__', tarjeta.dataset.id), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(destino),
                });

                const datos = await respuesta.json().catch(() => ({}));

                if (!respuesta.ok) {
                    throw new Error(datos.message || 'No se pudo reprogramar la cita.');
                }

                tarjeta.title = tarjeta.title.replace(/^\d{2}:\d{2}/, datos.hora);
                avisar(`Cita reprogramada al ${datos.fecha_hora}.`, 'success');
            } catch (error) {
                origen.columna.appendChild(tarjeta);
                tarjeta.style.top = origen.top;
                tarjeta.style.left = origen.left;
                tarjeta.style.width = origen.width;
                tarjeta.querySelector('.hora').textContent = origen.hora;
                avisar(error.message);
            } finally {
                tarjeta.classList.remove('en-vuelo');
            }
        });

        if (urlCrear) {
            franja.addEventListener('dblclick', () => {
                const url = new URL(urlCrear, window.location.origin);
                url.searchParams.set('fecha', franja.dataset.fecha);
                url.searchParams.set('hora', franja.dataset.hora);
                window.location.href = url.toString();
            });
        }
    });
})();
</script>
@endpush
