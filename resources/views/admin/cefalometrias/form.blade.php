@extends('layouts.admin')

@section('pretitulo', 'Ortodoncia')
@section('titulo', $cefalometria->exists ? 'Editar Trazado Cefalométrico' : 'Nuevo Trazado Cefalométrico · '.$paciente->nombre_completo)

@section('acciones')
    <a href="{{ route('admin.cefalometrias.index', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'ortodoncia'])

<form method="POST" action="{{ $cefalometria->exists ? route('admin.cefalometrias.update', $cefalometria) : route('admin.cefalometrias.store', $paciente) }}" enctype="multipart/form-data">
    @csrf
    @if ($cefalometria->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-scan me-2"></i>Datos del Estudio Cefalométrico</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Fecha del estudio</label>
                            <input type="date" name="fecha" class="form-control"
                                   value="{{ old('fecha', $cefalometria->fecha ? $cefalometria->fecha->format('Y-m-d') : now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tipo de Análisis</label>
                            <select name="tipo_analisis" class="form-select" required>
                                <option value="STEINER" @selected(old('tipo_analisis', $cefalometria->tipo_analisis) === 'STEINER')>Análisis de Steiner (Estándar)</option>
                                <option value="RICKETTS" @selected(old('tipo_analisis', $cefalometria->tipo_analisis) === 'RICKETTS')>Análisis de Ricketts</option>
                                <option value="TWEED" @selected(old('tipo_analisis', $cefalometria->tipo_analisis) === 'TWEED')>Triángulo de Tweed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ortodoncista responsable</label>
                            <select name="doctor_id" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($doctores as $doc)
                                    <option value="{{ $doc->id }}" @selected(old('doctor_id', $cefalometria->doctor_id) == $doc->id)>
                                        {{ $doc->nombre_profesional }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Telerradiografía lateral de cráneo</label>
                            <input type="file" name="imagen_radiografia" class="form-control" accept="image/*">
                            @if ($cefalometria->imagen_radiografia)
                                <div class="mt-2 small text-success">
                                    <i class="ti ti-photo me-1"></i>Radiografía adjunta cargada.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Parámetros de Steiner y Diagnóstico interactivo --}}
            @php $medidas = old('medidas', $cefalometria->medidas ?? []); @endphp
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-math me-2"></i>Medidas Cefalométricas de Steiner</h3></div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Parámetro</th>
                                <th>Norma Estándar</th>
                                <th style="width: 25%;">Valor Medido</th>
                                <th>Interpretación Automática</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Ángulo SNA</strong><br><small class="text-secondary">Posición anteroposterior maxilar</small></td>
                                <td>82.0° ± 2°</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" name="medidas[SNA]" id="inp-sna" class="form-control inp-calculo"
                                               value="{{ $medidas['SNA'] ?? '' }}" placeholder="82.0">
                                        <span class="input-group-text">°</span>
                                    </div>
                                </td>
                                <td id="interpretacion-sna" class="small text-secondary">—</td>
                            </tr>
                            <tr>
                                <td><strong>Ángulo SNB</strong><br><small class="text-secondary">Posición anteroposterior mandibular</small></td>
                                <td>80.0° ± 2°</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" name="medidas[SNB]" id="inp-snb" class="form-control inp-calculo"
                                               value="{{ $medidas['SNB'] ?? '' }}" placeholder="80.0">
                                        <span class="input-group-text">°</span>
                                    </div>
                                </td>
                                <td id="interpretacion-snb" class="small text-secondary">—</td>
                            </tr>
                            <tr class="table-primary-lt">
                                <td><strong>Ángulo ANB (SNA - SNB)</strong><br><small class="text-secondary">Discrepancia esquelética sagital</small></td>
                                <td><strong>2.0° ± 2°</strong></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" name="medidas[ANB]" id="inp-anb" class="form-control fw-bold"
                                               value="{{ $medidas['ANB'] ?? '' }}" placeholder="2.0">
                                        <span class="input-group-text">°</span>
                                    </div>
                                </td>
                                <td id="interpretacion-anb" class="fw-bold">—</td>
                            </tr>
                            <tr>
                                <td><strong>Plano Mandibular (GoGn-SN)</strong><br><small class="text-secondary">Patrón de crecimiento vertical</small></td>
                                <td>32.0° ± 3°</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" name="medidas[GoGn_SN]" id="inp-gogn" class="form-control inp-calculo"
                                               value="{{ $medidas['GoGn_SN'] ?? '' }}" placeholder="32.0">
                                        <span class="input-group-text">°</span>
                                    </div>
                                </td>
                                <td id="interpretacion-gogn" class="small text-secondary">—</td>
                            </tr>
                            <tr>
                                <td><strong>Incisivo Superior a NA (1-NA)</strong></td>
                                <td>22.0° / 4.0 mm</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <input type="number" step="0.1" name="medidas[UI_NA_deg]" class="form-control form-control-sm"
                                               value="{{ $medidas['UI_NA_deg'] ?? '' }}" placeholder="grados °">
                                        <input type="number" step="0.1" name="medidas[UI_NA_mm]" class="form-control form-control-sm"
                                               value="{{ $medidas['UI_NA_mm'] ?? '' }}" placeholder="mm">
                                    </div>
                                </td>
                                <td class="small text-secondary">Inclinación y protrusión superior</td>
                            </tr>
                            <tr>
                                <td><strong>Incisivo Inferior a NB (1-NB)</strong></td>
                                <td>25.0° / 4.0 mm</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <input type="number" step="0.1" name="medidas[LI_NB_deg]" class="form-control form-control-sm"
                                               value="{{ $medidas['LI_NB_deg'] ?? '' }}" placeholder="grados °">
                                        <input type="number" step="0.1" name="medidas[LI_NB_mm]" class="form-control form-control-sm"
                                               value="{{ $medidas['LI_NB_mm'] ?? '' }}" placeholder="mm">
                                    </div>
                                </td>
                                <td class="small text-secondary">Inclinación y protrusión inferior</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-notes me-2"></i>Interpretación y Plan Ortodóntico</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Interpretación Diagnóstica Cefalométrica</label>
                        <textarea name="interpretacion" rows="3" class="form-control" id="txt-interpretacion"
                                  placeholder="Resumen del biotipo esquelético, posición de bases apicales y compensación dentoalveolar...">{{ old('interpretacion', $cefalometria->interpretacion) }}</textarea>
                    </div>
                    <div>
                        <label class="form-label">Plan de Tratamiento Ortodóntico Propuesto</label>
                        <textarea name="plan_tratamiento" rows="3" class="form-control"
                                  placeholder="Aparatología propuesta (Brackets autoligables, alineadores, aparatología funcional, exodoncias terapéuticas, etc.)...">{{ old('plan_tratamiento', $cefalometria->plan_tratamiento) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-report-medical me-2"></i>Clasificación Diagnóstica</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Diagnóstico Esquelético</label>
                        <select name="diagnostico_esqueletico" id="select-diag" class="form-select" required>
                            @foreach ($diagnosticos as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('diagnostico_esqueletico', $cefalometria->diagnostico_esqueletico) === $clave)>
                                    {{ $etiqueta }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Patrón de Crecimiento Facial</label>
                        <select name="patron_crecimiento" id="select-patron" class="form-select" required>
                            @foreach ($patrones as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('patron_crecimiento', $cefalometria->patron_crecimiento) === $clave)>
                                    {{ $etiqueta }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-info py-2" id="box-alerta-cefalometria">
                        <i class="ti ti-info-circle me-1"></i>
                        <span id="txt-alerta">El diagnóstico se actualiza automáticamente según las medidas ingresadas.</span>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.cefalometrias.index', $paciente) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $cefalometria->exists ? 'Guardar Cambios' : 'Registrar Estudio' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const sna = document.getElementById('inp-sna');
    const snb = document.getElementById('inp-snb');
    const anb = document.getElementById('inp-anb');
    const gogn = document.getElementById('inp-gogn');
    const interpSna = document.getElementById('interpretacion-sna');
    const interpSnb = document.getElementById('interpretacion-snb');
    const interpAnb = document.getElementById('interpretacion-anb');
    const interpGogn = document.getElementById('interpretacion-gogn');
    const selectDiag = document.getElementById('select-diag');
    const selectPatron = document.getElementById('select-patron');
    const txtAlerta = document.getElementById('txt-alerta');

    function actualizar() {
        const valSna = parseFloat(sna.value);
        const valSnb = parseFloat(snb.value);

        if (!isNaN(valSna) && !isNaN(valSnb)) {
            anb.value = (valSna - valSnb).toFixed(1);
        }

        const valAnb = parseFloat(anb.value);
        const valGogn = parseFloat(gogn.value);

        // SNA
        if (!isNaN(valSna)) {
            if (valSna > 84) interpSna.innerHTML = '<span class="text-danger">Prognatismo maxilar</span>';
            else if (valSna < 80) interpSna.innerHTML = '<span class="text-warning">Retrognatismo maxilar</span>';
            else interpSna.innerHTML = '<span class="text-success">Maxilar ortoposição normal</span>';
        }

        // SNB
        if (!isNaN(valSnb)) {
            if (valSnb > 82) interpSnb.innerHTML = '<span class="text-danger">Prognatismo mandibular</span>';
            else if (valSnb < 78) interpSnb.innerHTML = '<span class="text-warning">Retrognatismo mandibular</span>';
            else interpSnb.innerHTML = '<span class="text-success">Mandíbula ortoposição normal</span>';
        }

        // ANB y Clase
        if (!isNaN(valAnb)) {
            if (valAnb > 4.0) {
                interpAnb.innerHTML = '<span class="text-warning">Clase II Esquelética</span>';
                selectDiag.value = 'CLASE_II';
                txtAlerta.textContent = 'Paciente con Clase II esquelética (ANB > 4°). Posible retrognatismo mandibular.';
            } else if (valAnb < 0.0) {
                interpAnb.innerHTML = '<span class="text-danger">Clase III Esquelética</span>';
                selectDiag.value = 'CLASE_III';
                txtAlerta.textContent = 'Paciente con Clase III esquelética (ANB < 0°). Posible prognatismo mandibular.';
            } else {
                interpAnb.innerHTML = '<span class="text-success">Clase I Esquelética</span>';
                selectDiag.value = 'CLASE_I';
                txtAlerta.textContent = 'Paciente con Clase I esquelética (relación maxilomandibular armónica).';
            }
        }

        // GoGn-SN y Patrón
        if (!isNaN(valGogn)) {
            if (valGogn > 36.0) {
                interpGogn.innerHTML = '<span class="text-danger">Dolicofacial (Crecimiento Vertical)</span>';
                selectPatron.value = 'DOLICOFACIAL';
            } else if (valGogn < 28.0) {
                interpGogn.innerHTML = '<span class="text-warning">Braquifacial (Crecimiento Horizontal)</span>';
                selectPatron.value = 'BRAQUIFACIAL';
            } else {
                interpGogn.innerHTML = '<span class="text-success">Mesofacial (Normodivergente)</span>';
                selectPatron.value = 'MESOFACIAL';
            }
        }
    }

    sna.addEventListener('input', actualizar);
    snb.addEventListener('input', actualizar);
    anb.addEventListener('input', actualizar);
    gogn.addEventListener('input', actualizar);
    actualizar();
});
</script>
@endpush
@endsection
