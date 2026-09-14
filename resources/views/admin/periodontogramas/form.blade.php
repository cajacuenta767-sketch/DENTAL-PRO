@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $periodontograma->exists ? 'Editar Periodontograma' : 'Nuevo Periodontograma')
@section('subtitulo', $paciente->nombre_completo)

@section('acciones')
    @if (! $periodontograma->exists && $anterior)
        <a href="{{ route('admin.periodontogramas.create', ['paciente' => $paciente, 'desde' => 'ultimo']) }}"
           class="btn btn-outline-primary" title="Copia el sondaje del periodontograma del {{ $anterior->fecha->format('d/m/Y') }}">
            <i class="ti ti-copy me-1"></i>Partir del último ({{ $anterior->fecha->format('d/m/Y') }})
        </a>
    @endif
@endsection

@push('head')
<style>
    .pd-tabla { table-layout: fixed; width: max-content; min-width: 100%; margin-bottom: 0; }
    .pd-tabla th, .pd-tabla td { padding: .2rem .15rem; text-align: center; vertical-align: middle; border-color: var(--tblr-border-color); }
    .pd-tabla th.pd-etiqueta { position: sticky; left: 0; z-index: 2; background: var(--tblr-bg-surface); text-align: left;
        width: 7.5rem; min-width: 7.5rem; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em;
        color: var(--tblr-secondary); font-weight: 600; padding-left: .6rem; }
    .pd-tabla th.pd-etiqueta small { display: block; text-transform: none; letter-spacing: 0; font-weight: 400; }
    .pd-tabla thead th.pd-col { font-size: .9rem; font-weight: 700; }
    .pd-tabla td.pd-col, .pd-tabla th.pd-col { width: 7rem; min-width: 7rem; }
    .pd-tabla .pd-col:nth-child(9) { border-left: 2px solid var(--tblr-border-color-active, #adb5bd); }
    .pd-sitios { display: flex; gap: 2px; justify-content: center; }
    .pd-sitio { display: flex; flex-direction: column; align-items: center; }
    .pd-celda { width: 2.2rem; height: 1.85rem; padding: 0; text-align: center; font-size: .82rem; font-weight: 500;
        border: 1px solid var(--tblr-border-color); border-radius: 3px; background: var(--tblr-bg-forms); color: inherit; }
    .pd-celda:focus { outline: 2px solid var(--tblr-primary); outline-offset: -1px; }
    .pd-celda:disabled { background: transparent; }
    .pd-celda.pd-bolsa { background: #fff3bf; color: #6b5300; border-color: #f0c000; }
    .pd-celda.pd-profunda { background: #ffc9c9; color: #7a1a1a; border-color: #e03131; }
    .pd-puntos { display: flex; gap: 4px; margin-top: 3px; height: 10px; }
    .pd-punto { width: 10px; height: 10px; border-radius: 50%; border: 1px solid #adb5bd; background: transparent; padding: 0; cursor: pointer; }
    .pd-punto:disabled { cursor: default; }
    .pd-punto.pd-sangrado:hover { border-color: #d63939; }
    .pd-punto.pd-placa:hover { border-color: #f59f00; }
    .pd-punto.pd-sangrado.activo { background: #d63939; border-color: #d63939; }
    .pd-punto.pd-placa.activo { background: #f59f00; border-color: #f59f00; }
    .pd-ausente-col { opacity: .3; }
    .pd-select { width: 3.4rem; padding: .1rem 1.2rem .1rem .3rem; font-size: .75rem; height: 1.6rem; margin: 0 auto; }
    .pd-grafico { width: 100%; height: auto; display: block; }
    .pd-leyenda span { display: inline-flex; align-items: center; gap: .3rem; margin-right: .8rem; font-size: .8rem; }
    .pd-muestra { display: inline-block; width: 14px; height: 14px; border-radius: 3px; border: 1px solid var(--tblr-border-color); }
    .pd-indice { font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
</style>
@endpush

@section('contenido')
@php
    $cuadrantes = \App\Models\Odontograma::PIEZAS_ADULTO;
    $sitios = \App\Models\Periodontograma::SITIOS;
    $arcadas = [
        'superior' => ['etiqueta' => 'Arcada superior', 'rango' => '18 → 28', 'piezas' => array_merge($cuadrantes['superior_derecho'], $cuadrantes['superior_izquierdo'])],
        'inferior' => ['etiqueta' => 'Arcada inferior', 'rango' => '48 → 38', 'piezas' => array_merge($cuadrantes['inferior_derecho'], $cuadrantes['inferior_izquierdo'])],
    ];
    $filas = [
        'vestibular' => ['Vestibular', 'dv · v · mv', ['dv', 'v', 'mv']],
        'lingual' => ['Lingual / Palatino', 'dl · l · ml', ['dl', 'l', 'ml']],
    ];
@endphp

<form method="POST" id="form-periodontograma"
      action="{{ $periodontograma->exists ? route('admin.periodontogramas.update', $periodontograma) : route('admin.periodontogramas.store', $paciente) }}">
    @csrf
    @if ($periodontograma->exists) @method('PUT') @endif

    <input type="hidden" name="piezas" id="piezas" value="{{ old('piezas', json_encode($periodontograma->piezas ?? [])) }}">

    @error('piezas')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-xl-9">
            {{-- Barra de herramientas y leyenda --}}
            <div class="card mb-3">
                <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center">
                    <div class="pd-leyenda">
                        <span><i class="pd-muestra"></i> 1-3 mm</span>
                        <span><i class="pd-muestra" style="background:#fff3bf;border-color:#f0c000;"></i> 4-5 mm bolsa</span>
                        <span><i class="pd-muestra" style="background:#ffc9c9;border-color:#e03131;"></i> ≥ 6 mm profunda</span>
                        <span><i class="pd-punto pd-sangrado activo"></i> Sangrado</span>
                        <span><i class="pd-punto pd-placa activo"></i> Placa</span>
                    </div>
                    <span class="text-secondary small d-none d-lg-inline">
                        <i class="ti ti-keyboard me-1"></i>Un dígito avanza al siguiente sitio · Enter/Tab siguiente · ↑↓ cambia de cara · B sangrado · P placa · Retroceso borra
                    </span>
                    <label class="form-check form-switch mb-0 ms-auto">
                        <input type="checkbox" class="form-check-input" data-pd-recesion>
                        <span class="form-check-label small">Registrar recesión</span>
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-pd-limpiar>
                        <i class="ti ti-eraser me-1"></i>Limpiar todo
                    </button>
                </div>
            </div>

            @foreach ($arcadas as $clave => $arcada)
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h3 class="card-title mb-0"><i class="ti ti-dental me-2"></i>{{ $arcada['etiqueta'] }}</h3>
                        <span class="text-secondary small ms-2">{{ $arcada['rango'] }}</span>
                        <span class="badge bg-secondary-lt ms-auto" data-pd-resumen-arcada="{{ $clave }}">—</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm pd-tabla" data-arcada="{{ $clave }}">
                            <thead>
                                <tr>
                                    <th class="pd-etiqueta">Pieza</th>
                                    @foreach ($arcada['piezas'] as $n)
                                        <th class="pd-col" data-col="{{ $n }}">{{ $n }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th class="pd-etiqueta">Ausente</th>
                                    @foreach ($arcada['piezas'] as $n)
                                        <td class="pd-col">
                                            <input type="checkbox" class="form-check-input" data-pieza="{{ $n }}" data-campo="ausente"
                                                   title="Pieza {{ $n }} ausente" tabindex="-1">
                                        </td>
                                    @endforeach
                                </tr>
                                @foreach ($filas as $fila => [$etiqueta, $ayuda, $sitiosFila])
                                    <tr>
                                        <th class="pd-etiqueta">{{ $etiqueta }}<small>{{ $ayuda }}</small></th>
                                        @foreach ($arcada['piezas'] as $n)
                                            <td class="pd-col" data-col="{{ $n }}">
                                                <div class="pd-sitios">
                                                    @foreach ($sitiosFila as $sitio)
                                                        <div class="pd-sitio">
                                                            <input type="text" class="pd-celda" inputmode="numeric" maxlength="2" autocomplete="off"
                                                                   data-pieza="{{ $n }}" data-sitio="{{ $sitio }}" data-campo="sondaje" data-max="15"
                                                                   aria-label="Sondaje pieza {{ $n }} {{ \App\Models\Periodontograma::SITIOS_ETIQUETA[$sitio] }}">
                                                            <div class="pd-puntos">
                                                                <button type="button" class="pd-punto pd-sangrado" tabindex="-1"
                                                                        data-pieza="{{ $n }}" data-sitio="{{ $sitio }}" data-campo="sangrado"
                                                                        title="Sangrado al sondaje ({{ $sitio }})"></button>
                                                                <button type="button" class="pd-punto pd-placa" tabindex="-1"
                                                                        data-pieza="{{ $n }}" data-sitio="{{ $sitio }}" data-campo="placa"
                                                                        title="Placa ({{ $sitio }})"></button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                @foreach ($filas as $fila => [$etiqueta, $ayuda, $sitiosFila])
                                    <tr class="pd-fila-recesion d-none">
                                        <th class="pd-etiqueta">Recesión {{ $fila === 'vestibular' ? 'V' : 'L' }}<small>{{ $ayuda }}</small></th>
                                        @foreach ($arcada['piezas'] as $n)
                                            <td class="pd-col" data-col="{{ $n }}">
                                                <div class="pd-sitios">
                                                    @foreach ($sitiosFila as $sitio)
                                                        <input type="text" class="pd-celda" inputmode="numeric" maxlength="2" autocomplete="off"
                                                               data-pieza="{{ $n }}" data-sitio="{{ $sitio }}" data-campo="recesion" data-max="10"
                                                               aria-label="Recesión pieza {{ $n }} {{ $sitio }}">
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr>
                                    <th class="pd-etiqueta">Movilidad<small>0 a 3</small></th>
                                    @foreach ($arcada['piezas'] as $n)
                                        <td class="pd-col" data-col="{{ $n }}">
                                            <select class="form-select pd-select" data-pieza="{{ $n }}" data-campo="movilidad" tabindex="-1" aria-label="Movilidad pieza {{ $n }}">
                                                @foreach ([0, 1, 2, 3] as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="pd-etiqueta">Furca<small>0 a 3</small></th>
                                    @foreach ($arcada['piezas'] as $n)
                                        <td class="pd-col" data-col="{{ $n }}">
                                            <select class="form-select pd-select" data-pieza="{{ $n }}" data-campo="furca" tabindex="-1" aria-label="Furca pieza {{ $n }}">
                                                @foreach ([0, 1, 2, 3] as $g)<option value="{{ $g }}">{{ $g }}</option>@endforeach
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th class="pd-etiqueta">Sondaje V<small>gráfico en mm</small></th>
                                    <td colspan="{{ count($arcada['piezas']) }}" class="p-0">
                                        <svg class="pd-grafico" data-grafico="{{ $clave }}" viewBox="0 0 {{ count($arcada['piezas']) * 100 }} 130"
                                             role="img" aria-label="Profundidad de sondaje vestibular de la {{ mb_strtolower($arcada['etiqueta']) }}"></svg>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="col-xl-3">
            {{-- Índices en vivo --}}
            <div class="card mb-3">
                <div class="card-header py-2"><h3 class="card-title mb-0"><i class="ti ti-chart-bar me-2"></i>Índices</h3></div>
                <div class="card-body py-2">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="pd-indice text-danger" data-ind="sangrado">0 %</div>
                            <div class="text-secondary" style="font-size: .68rem;">SANGRADO</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice text-warning" data-ind="placa">0 %</div>
                            <div class="text-secondary" style="font-size: .68rem;">PLACA</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice" data-ind="profundidad_media">0.00</div>
                            <div class="text-secondary" style="font-size: .68rem;">PROF. MEDIA (MM)</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice" data-ind="sitios">0</div>
                            <div class="text-secondary" style="font-size: .68rem;">SITIOS</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice text-warning" data-ind="bolsas">0</div>
                            <div class="text-secondary" style="font-size: .68rem;">SITIOS ≥ 4 MM</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice text-danger" data-ind="bolsas_profundas">0</div>
                            <div class="text-secondary" style="font-size: .68rem;">SITIOS ≥ 6 MM</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice" data-ind="movilidad">0</div>
                            <div class="text-secondary" style="font-size: .68rem;">CON MOVILIDAD</div>
                        </div>
                        <div class="col-6">
                            <div class="pd-indice" data-ind="ausentes">0</div>
                            <div class="text-secondary" style="font-size: .68rem;">AUSENTES</div>
                        </div>
                    </div>
                    <div class="alert alert-success py-2 mt-3 mb-0 small" data-pd-diagnostico>
                        <i class="ti ti-stethoscope me-1"></i><span data-pd-diagnostico-texto>Sin signos de enfermedad periodontal activa.</span>
                        <div class="text-secondary mt-1" style="font-size: .68rem;">Orientativo: no sustituye el criterio clínico.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-2"><h3 class="card-title mb-0">Datos del registro</h3></div>
                <div class="card-body">
                    <x-campo nombre="fecha" etiqueta="Fecha" requerido>
                        <input type="date" id="fecha" name="fecha" class="form-control" max="{{ now()->toDateString() }}"
                               value="{{ old('fecha', $periodontograma->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor">
                        <select id="doctor_id" name="doctor_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $periodontograma->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $periodontograma->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento?->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3" maxlength="2000">{{ old('observaciones', $periodontograma->observaciones) }}</textarea>
                    </x-campo>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.periodontogramas.index', $paciente) }}" class="btn btn-link">Cancelar</a>
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
    const SITIOS = @json($sitios);
    const ARCADAS = @json(array_map(fn ($a) => array_map('strval', $a['piezas']), $arcadas));
    const TODAS = Object.values(ARCADAS).flat();

    const campoPiezas = document.getElementById('piezas');
    const raiz = document.getElementById('form-periodontograma');

    // --- Estado ---------------------------------------------------------------

    function enBlanco() {
        const porSitio = (v) => Object.fromEntries(SITIOS.map((s) => [s, v]));
        return { ausente: false, sondaje: porSitio(null), sangrado: porSitio(false), placa: porSitio(false), recesion: porSitio(null), movilidad: 0, furca: 0, nota: null };
    }

    function normalizar(p) {
        const base = enBlanco();
        if (!p || typeof p !== 'object') return base;
        const entero = (v, max) => {
            if (v === null || v === undefined || v === '' || isNaN(Number(v))) return null;
            const n = Math.trunc(Number(v));
            return n >= 0 && n <= max ? n : null;
        };
        SITIOS.forEach((s) => {
            base.sondaje[s] = entero(p.sondaje?.[s], 15);
            base.recesion[s] = entero(p.recesion?.[s], 10);
            base.sangrado[s] = !!p.sangrado?.[s];
            base.placa[s] = !!p.placa?.[s];
        });
        base.ausente = !!p.ausente;
        base.movilidad = entero(p.movilidad, 3) ?? 0;
        base.furca = entero(p.furca, 3) ?? 0;
        base.nota = p.nota ?? null;
        return base;
    }

    let mapa = {};
    try {
        const v = JSON.parse(campoPiezas.value || '{}');
        mapa = v && typeof v === 'object' ? v : {};
    } catch { mapa = {}; }
    TODAS.forEach((n) => { mapa[n] = normalizar(mapa[n]); });

    const vacio = (v) => v === null || v === undefined || v === '';

    // --- Cálculo de índices (mismo criterio que Periodontograma::indices) ------

    function calcularIndices(piezas = TODAS) {
        let sitios = 0, sangrado = 0, placa = 0, suma = 0, medidos = 0, bolsas = 0, profundas = 0, movilidad = 0, ausentes = 0;

        piezas.forEach((n) => {
            const p = mapa[n];
            if (p.ausente) { ausentes++; return; }
            if ((Number(p.movilidad) || 0) > 0) movilidad++;

            SITIOS.forEach((s) => {
                sitios++;
                if (p.sangrado[s]) sangrado++;
                if (p.placa[s]) placa++;
                const v = p.sondaje[s];
                if (!vacio(v)) {
                    const f = Number(v);
                    medidos++;
                    suma += f;
                    if (f >= 4) bolsas++;
                    if (f >= 6) profundas++;
                }
            });
        });

        const r1 = (x) => Math.round(x * 10) / 10;
        const r2 = (x) => Math.round(x * 100) / 100;

        return {
            sitios,
            sangrado: sitios ? r1(sangrado / sitios * 100) : 0,
            placa: sitios ? r1(placa / sitios * 100) : 0,
            profundidad_media: medidos ? r2(suma / medidos) : 0,
            bolsas,
            bolsas_profundas: profundas,
            movilidad,
            ausentes,
        };
    }

    function diagnostico(i) {
        if (i.bolsas_profundas > 0 || i.profundidad_media >= 5) return ['danger', 'Compatible con periodontitis avanzada: bolsas de 6 mm o más.'];
        if (i.bolsas > 0 || i.profundidad_media >= 4) return ['warning', 'Compatible con periodontitis moderada: bolsas de 4 a 5 mm.'];
        if (i.sangrado >= 10) return ['azure', `Compatible con gingivitis: sangrado al sondaje en el ${i.sangrado} % de los sitios.`];
        return ['success', 'Sin signos de enfermedad periodontal activa.'];
    }

    // --- Pintado --------------------------------------------------------------

    function colorear(input) {
        const v = input.value === '' ? null : Number(input.value);
        input.classList.toggle('pd-bolsa', v !== null && v >= 4 && v < 6);
        input.classList.toggle('pd-profunda', v !== null && v >= 6);
    }

    function pintarPieza(n) {
        const p = mapa[n];

        raiz.querySelectorAll(`[data-pieza="${n}"]`).forEach((el) => {
            const campo = el.dataset.campo;
            const sitio = el.dataset.sitio;

            switch (campo) {
                case 'ausente': el.checked = p.ausente; break;
                case 'sondaje': el.value = vacio(p.sondaje[sitio]) ? '' : p.sondaje[sitio]; colorear(el); el.disabled = p.ausente; break;
                case 'recesion': el.value = vacio(p.recesion[sitio]) ? '' : p.recesion[sitio]; el.disabled = p.ausente; break;
                case 'sangrado': el.classList.toggle('activo', !!p.sangrado[sitio]); el.disabled = p.ausente; break;
                case 'placa': el.classList.toggle('activo', !!p.placa[sitio]); el.disabled = p.ausente; break;
                case 'movilidad': el.value = String(p.movilidad); el.disabled = p.ausente; break;
                case 'furca': el.value = String(p.furca); el.disabled = p.ausente; break;
            }
        });

        raiz.querySelectorAll(`[data-col="${n}"]`).forEach((c) => c.classList.toggle('pd-ausente-col', p.ausente));
    }

    function pintarIndices() {
        const i = calcularIndices();
        const formato = {
            sangrado: `${i.sangrado} %`, placa: `${i.placa} %`, profundidad_media: i.profundidad_media.toFixed(2),
            sitios: i.sitios, bolsas: i.bolsas, bolsas_profundas: i.bolsas_profundas, movilidad: i.movilidad, ausentes: i.ausentes,
        };
        Object.entries(formato).forEach(([k, v]) => {
            const el = raiz.querySelector(`[data-ind="${k}"]`);
            if (el) el.textContent = v;
        });

        const [color, texto] = diagnostico(i);
        const alerta = raiz.querySelector('[data-pd-diagnostico]');
        alerta.className = `alert alert-${color} py-2 mt-3 mb-0 small`;
        raiz.querySelector('[data-pd-diagnostico-texto]').textContent = texto;

        Object.entries(ARCADAS).forEach(([arcada, piezas]) => {
            const ia = calcularIndices(piezas);
            const el = raiz.querySelector(`[data-pd-resumen-arcada="${arcada}"]`);
            if (el) el.textContent = `${ia.sangrado} % sangrado · ${ia.bolsas} sitios ≥ 4 mm · media ${ia.profundidad_media.toFixed(2)} mm`;
        });
    }

    // Línea de profundidad vestibular por pieza (dv, v, mv), con guías en 4 y 6 mm.
    function dibujar(arcada) {
        const svg = raiz.querySelector(`[data-grafico="${arcada}"]`);
        const piezas = ARCADAS[arcada];
        const W = 100, H = 130, MAX = 12, arriba = 10, abajo = 18;
        const y = (v) => H - abajo - Math.min(v, MAX) / MAX * (H - arriba - abajo);
        const ancho = piezas.length * W;
        const partes = [];

        partes.push(`<rect x="0" y="0" width="${ancho}" height="${H}" fill="none"/>`);
        [2, 4, 6, 8, 10].forEach((mm) => {
            const color = mm === 4 ? '#f59f00' : mm === 6 ? '#d63939' : '#dee2e6';
            partes.push(`<line x1="0" x2="${ancho}" y1="${y(mm)}" y2="${y(mm)}" stroke="${color}" stroke-width="1" ${mm === 4 || mm === 6 ? 'stroke-dasharray="6 5"' : ''}/>`);
            partes.push(`<text x="4" y="${y(mm) - 2}" font-size="10" fill="#868e96">${mm}</text>`);
        });
        piezas.forEach((n, i) => {
            if (i > 0) partes.push(`<line x1="${i * W}" x2="${i * W}" y1="${arriba}" y2="${H - abajo}" stroke="#f1f3f5" stroke-width="1"/>`);
            partes.push(`<text x="${i * W + W / 2}" y="${H - 4}" font-size="11" text-anchor="middle" fill="#868e96">${n}</text>`);
        });

        const segmentos = [];
        let actual = [];
        piezas.forEach((n, i) => {
            const p = mapa[n];
            ['dv', 'v', 'mv'].forEach((s, j) => {
                const v = p.ausente ? null : p.sondaje[s];
                if (vacio(v)) {
                    if (actual.length) segmentos.push(actual);
                    actual = [];
                    return;
                }
                actual.push([i * W + (j + 0.5) * (W / 3), y(Number(v)), Number(v)]);
            });
        });
        if (actual.length) segmentos.push(actual);

        segmentos.forEach((seg) => {
            if (seg.length > 1) {
                partes.push(`<polyline fill="none" stroke="#206bc4" stroke-width="2" stroke-linejoin="round" points="${seg.map((p) => `${p[0]},${p[1]}`).join(' ')}"/>`);
            }
            seg.forEach((p) => {
                const color = p[2] >= 6 ? '#d63939' : p[2] >= 4 ? '#f59f00' : '#206bc4';
                partes.push(`<circle cx="${p[0]}" cy="${p[1]}" r="3.5" fill="${color}"/>`);
            });
        });

        svg.innerHTML = partes.join('');
    }

    function actualizar() {
        campoPiezas.value = JSON.stringify(mapa);
        pintarIndices();
        Object.keys(ARCADAS).forEach(dibujar);
    }

    function pintarTodo() {
        TODAS.forEach(pintarPieza);
        actualizar();
    }

    // --- Navegación entre celdas --------------------------------------------

    const celdas = [...raiz.querySelectorAll('input.pd-celda')];

    function navegables(campo) {
        return celdas.filter((c) => c.dataset.campo === campo && !c.disabled && !c.closest('tr').classList.contains('d-none'));
    }

    function mover(el, delta) {
        const lista = navegables(el.dataset.campo);
        const destino = lista[lista.indexOf(el) + delta];
        if (destino) { destino.focus(); destino.select(); }
        return !!destino;
    }

    // ↑ ↓: mismo sitio en la otra cara (dv ↔ dl, v ↔ l, mv ↔ ml).
    function cambiarCara(el) {
        const i = SITIOS.indexOf(el.dataset.sitio);
        const pareja = SITIOS[i < 3 ? i + 3 : i - 3];
        const destino = raiz.querySelector(`input.pd-celda[data-campo="${el.dataset.campo}"][data-pieza="${el.dataset.pieza}"][data-sitio="${pareja}"]`);
        if (destino && !destino.disabled) { destino.focus(); destino.select(); }
    }

    function asignarCelda(el, valor) {
        const p = mapa[el.dataset.pieza];
        p[el.dataset.campo][el.dataset.sitio] = valor;
        el.value = vacio(valor) ? '' : valor;
        if (el.dataset.campo === 'sondaje') colorear(el);
        actualizar();
    }

    function alternar(pieza, campo, sitio) {
        mapa[pieza][campo][sitio] = !mapa[pieza][campo][sitio];
        pintarPieza(pieza);
        actualizar();
    }

    celdas.forEach((el) => {
        const max = Number(el.dataset.max || 15);

        el.addEventListener('focus', () => el.select());

        el.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey || e.altKey) return;

            if (/^[0-9]$/.test(e.key)) {
                e.preventDefault();
                const d = e.key;

                // Tras un "1" se espera un segundo dígito para 10-15 mm.
                if (el.dataset.esperando === '1' && Number('1' + d) <= max) {
                    delete el.dataset.esperando;
                    asignarCelda(el, Number('1' + d));
                    mover(el, 1);
                    return;
                }

                delete el.dataset.esperando;
                asignarCelda(el, Number(d));

                if (d === '1' && max >= 10) {
                    el.dataset.esperando = '1';
                } else {
                    mover(el, 1);
                }
                return;
            }

            switch (e.key) {
                case 'Enter':
                    e.preventDefault();
                    delete el.dataset.esperando;
                    mover(el, e.shiftKey ? -1 : 1);
                    break;
                case 'Tab':
                    delete el.dataset.esperando;
                    break;
                case 'Backspace':
                    e.preventDefault();
                    delete el.dataset.esperando;
                    if (el.value === '') mover(el, -1);
                    else asignarCelda(el, null);
                    break;
                case 'Delete':
                    e.preventDefault();
                    asignarCelda(el, null);
                    break;
                case 'ArrowRight': e.preventDefault(); mover(el, 1); break;
                case 'ArrowLeft': e.preventDefault(); mover(el, -1); break;
                case 'ArrowUp':
                case 'ArrowDown': e.preventDefault(); cambiarCara(el); break;
                case 'b': case 'B':
                    if (el.dataset.campo === 'sondaje') { e.preventDefault(); alternar(el.dataset.pieza, 'sangrado', el.dataset.sitio); }
                    break;
                case 'p': case 'P':
                    if (el.dataset.campo === 'sondaje') { e.preventDefault(); alternar(el.dataset.pieza, 'placa', el.dataset.sitio); }
                    break;
                default:
                    if (e.key.length === 1) e.preventDefault();
            }
        });

        // Teclados móviles y pegado: el evento input es la vía de entrada.
        el.addEventListener('input', () => {
            const texto = el.value.replace(/\D/g, '').slice(0, 2);
            const valor = texto === '' ? null : Math.min(max, Number(texto));
            asignarCelda(el, valor);
            if (texto.length === 2 || (texto.length === 1 && texto !== '1')) mover(el, 1);
        });

        el.addEventListener('blur', () => { delete el.dataset.esperando; });
    });

    raiz.querySelectorAll('button.pd-punto[data-pieza]').forEach((btn) => {
        btn.addEventListener('click', () => alternar(btn.dataset.pieza, btn.dataset.campo, btn.dataset.sitio));
    });

    raiz.querySelectorAll('input[data-campo="ausente"]').forEach((chk) => {
        chk.addEventListener('change', () => {
            mapa[chk.dataset.pieza].ausente = chk.checked;
            pintarPieza(chk.dataset.pieza);
            actualizar();
        });
    });

    raiz.querySelectorAll('select[data-campo="movilidad"], select[data-campo="furca"]').forEach((sel) => {
        sel.addEventListener('change', () => {
            mapa[sel.dataset.pieza][sel.dataset.campo] = Number(sel.value) || 0;
            actualizar();
        });
    });

    raiz.querySelector('[data-pd-recesion]').addEventListener('change', (e) => {
        raiz.querySelectorAll('.pd-fila-recesion').forEach((tr) => tr.classList.toggle('d-none', !e.target.checked));
    });

    raiz.querySelector('[data-pd-limpiar]').addEventListener('click', () => {
        if (!confirm('¿Borrar todo el sondaje registrado en este periodontograma?')) return;
        TODAS.forEach((n) => { mapa[n] = enBlanco(); });
        pintarTodo();
    });

    // Si hay recesión cargada, mostrar las filas de entrada.
    const hayRecesion = TODAS.some((n) => SITIOS.some((s) => !vacio(mapa[n].recesion[s])));
    if (hayRecesion) {
        raiz.querySelector('[data-pd-recesion]').checked = true;
        raiz.querySelectorAll('.pd-fila-recesion').forEach((tr) => tr.classList.remove('d-none'));
    }

    pintarTodo();
});
</script>
@endpush
@endsection
