@extends('layouts.admin')

@section('pretitulo', 'Endodoncia')
@section('titulo', $conductometria->exists ? 'Editar Conductometría Pieza '.$conductometria->diente : 'Nueva Conductometría · '.$paciente->nombre_completo)

@section('acciones')
    <a href="{{ route('admin.conductometrias.index', $paciente) }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver al listado</a>
@endsection

@section('contenido')
@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'endodoncia'])

<form method="POST" action="{{ $conductometria->exists ? route('admin.conductometrias.update', $conductometria) : route('admin.conductometrias.store', $paciente) }}">
    @csrf
    @if ($conductometria->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-dental me-2"></i>Pieza Dental y Diagnóstico</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Pieza dental (FDI)</label>
                            <input type="number" name="diente" id="input-diente" class="form-control form-control-lg fw-bold"
                                   min="11" max="85" value="{{ old('diente', $conductometria->diente ?? 16) }}" required>
                            <small class="text-secondary" id="nombre-diente-hint">Seleccione la pieza dental a tratar</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Fecha del registro</label>
                            <input type="date" name="fecha" class="form-control"
                                   value="{{ old('fecha', $conductometria->fecha ? $conductometria->fecha->format('Y-m-d') : now()->toDateString()) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Doctor responsable</label>
                            <select name="doctor_id" class="form-select">
                                <option value="">— Sin asignar —</option>
                                @foreach ($doctores as $doc)
                                    <option value="{{ $doc->id }}" @selected(old('doctor_id', $conductometria->doctor_id) == $doc->id)>
                                        {{ $doc->nombre_profesional }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnóstico Pulpar</label>
                            <select name="diagnostico_pulpar" class="form-select">
                                <option value="">— Seleccionar diagnóstico pulpar —</option>
                                @foreach ($diagnosticosPulpares as $clave => $etiqueta)
                                    <option value="{{ $etiqueta }}" @selected(old('diagnostico_pulpar', $conductometria->diagnostico_pulpar) === $etiqueta)>
                                        {{ $etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Diagnóstico Periapical</label>
                            <select name="diagnostico_periapical" class="form-select">
                                <option value="">— Seleccionar diagnóstico periapical —</option>
                                @foreach ($diagnosticosPeriapicales as $clave => $etiqueta)
                                    <option value="{{ $etiqueta }}" @selected(old('diagnostico_periapical', $conductometria->diagnostico_periapical) === $etiqueta)>
                                        {{ $etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Matriz interactiva de conductos --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title"><i class="ti ti-table me-2"></i>Matriz de Conductometría y Longitud de Trabajo</h3>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-conducto">
                        <i class="ti ti-plus me-1"></i>Agregar Conducto
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="tabla-conductos">
                        <thead>
                            <tr>
                                <th style="width: 25%;">Conducto</th>
                                <th style="width: 25%;">Ref. Anatómica</th>
                                <th style="width: 14%;">L. Aparente (mm)</th>
                                <th style="width: 14%;">L. Trabajo (mm)</th>
                                <th style="width: 16%;">Lima Apical (MAF)</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody id="cuerpo-conductos">
                            @php
                                $conductosExistentes = old('conductos', $conductometria->conductos ?? []);
                            @endphp
                            @foreach ($conductosExistentes as $i => $cond)
                                <tr class="fila-conducto">
                                    <td>
                                        <input type="text" name="conductos[{{ $i }}][nombre]" class="form-control form-control-sm fw-medium"
                                               value="{{ $cond['nombre'] ?? '' }}" placeholder="Ej. MV1, Palatino..." required>
                                    </td>
                                    <td>
                                        <input type="text" name="conductos[{{ $i }}][referencia]" class="form-control form-control-sm"
                                               value="{{ $cond['referencia'] ?? '' }}" placeholder="Ej. Cúspide MV">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="45" name="conductos[{{ $i }}][longitud_aparente]"
                                               class="form-control form-control-sm" value="{{ $cond['longitud_aparente'] ?? '' }}" placeholder="mm">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="45" name="conductos[{{ $i }}][longitud_trabajo]"
                                               class="form-control form-control-sm fw-bold text-primary" value="{{ $cond['longitud_trabajo'] ?? '' }}" placeholder="mm">
                                    </td>
                                    <td>
                                        <input type="text" name="conductos[{{ $i }}][lima_apical]" class="form-control form-control-sm"
                                               value="{{ $cond['lima_apical'] ?? '' }}" placeholder="Ej. 25.04, 30">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-ghost-danger btn-quitar-conducto" title="Eliminar conducto">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-notes me-2"></i>Observaciones y Hallazgos Clínicos</h3></div>
                <div class="card-body">
                    <textarea name="observaciones" rows="4" class="form-control"
                              placeholder="Detalles sobre curvatura radicular, calcificaciones, dolor a la percusión, fístula, etc.">{{ old('observaciones', $conductometria->observaciones) }}</textarea>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-flask me-2"></i>Protocolo Químico y Obturación</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Estado del tratamiento</label>
                        <select name="estado" class="form-select" required>
                            @foreach ($estados as $val => $etiqueta)
                                <option value="{{ $val }}" @selected(old('estado', $conductometria->estado) === $val)>
                                    {{ $etiqueta }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Solución Irrigante</label>
                        <input type="text" name="solucion_irrigante" class="form-control" list="lista-irrigantes"
                               value="{{ old('solucion_irrigante', $conductometria->solucion_irrigante ?? 'Hipoclorito de Sodio 5.25% + EDTA 17%') }}">
                        <datalist id="lista-irrigantes">
                            <option value="Hipoclorito de Sodio 5.25% + EDTA 17%">
                            <option value="Hipoclorito de Sodio 2.5%">
                            <option value="Clorhexidina 2%">
                            <option value="Suero Fisiológico Estéril">
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Medicación Intraconducto</label>
                        <input type="text" name="medicacion_intraconducto" class="form-control" list="lista-medicacion"
                               value="{{ old('medicacion_intraconducto', $conductometria->medicacion_intraconducto) }}" placeholder="Si es en varias sesiones">
                        <datalist id="lista-medicacion">
                            <option value="Hidróxido de Calcio [Ca(OH)2] pasta">
                            <option value="Pasta CTZ">
                            <option value="Paramonoclorofenol alcanforado">
                            <option value="Sin medicación (Sesión única)">
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Cemento Sellador</label>
                        <input type="text" name="cemento_sellador" class="form-control" list="lista-cementos"
                               value="{{ old('cemento_sellador', $conductometria->cemento_sellador) }}" placeholder="Ej. Biocerámico, AH Plus">
                        <datalist id="lista-cementos">
                            <option value="Sellador Biocerámico Bio-C Sealer">
                            <option value="AH Plus (Resina epóxica)">
                            <option value="EndoSequence BC Sealer">
                            <option value="Óxido de Zinc y Eugenol (Grossman)">
                        </datalist>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Técnica de Obturación</label>
                        <input type="text" name="tecnica_obturacion" class="form-control" list="lista-tecnicas"
                               value="{{ old('tecnica_obturacion', $conductometria->tecnica_obturacion) }}" placeholder="Ej. Cono único, Onda continua">
                        <datalist id="lista-tecnicas">
                            <option value="Cono único biocerámico">
                            <option value="Onda continua de calor (Warm vertical)">
                            <option value="Condensación lateral en frío">
                            <option value="Termoplástica inyectable">
                        </datalist>
                    </div>

                    @if ($citas->isNotEmpty())
                    <div class="mb-3">
                        <label class="form-label">Cita asociada</label>
                        <select name="cita_id" class="form-select">
                            <option value="">— Ninguna —</option>
                            @foreach ($citas as $c)
                                <option value="{{ $c->id }}" @selected(old('cita_id', $conductometria->cita_id) == $c->id)>
                                    {{ $c->fecha->format('d/m/Y') }} · {{ $c->tratamiento?->nombre ?? 'Cita' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.conductometrias.index', $paciente) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $conductometria->exists ? 'Guardar Cambios' : 'Registrar Conductometría' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabla = document.getElementById('cuerpo-conductos');
    const btnAgregar = document.getElementById('btn-agregar-conducto');
    let contador = tabla.querySelectorAll('.fila-conducto').length;

    btnAgregar.addEventListener('click', () => {
        const index = contador++;
        const tr = document.createElement('tr');
        tr.className = 'fila-conducto';
        tr.innerHTML = `
            <td>
                <input type="text" name="conductos[${index}][nombre]" class="form-control form-control-sm fw-medium" placeholder="Conducto" required>
            </td>
            <td>
                <input type="text" name="conductos[${index}][referencia]" class="form-control form-control-sm" placeholder="Ref. Anatómica">
            </td>
            <td>
                <input type="number" step="0.5" min="0" max="45" name="conductos[${index}][longitud_aparente]" class="form-control form-control-sm" placeholder="mm">
            </td>
            <td>
                <input type="number" step="0.5" min="0" max="45" name="conductos[${index}][longitud_trabajo]" class="form-control form-control-sm fw-bold text-primary" placeholder="mm">
            </td>
            <td>
                <input type="text" name="conductos[${index}][lima_apical]" class="form-control form-control-sm" placeholder="Lima MAF">
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-ghost-danger btn-quitar-conducto" title="Eliminar conducto">
                    <i class="ti ti-trash"></i>
                </button>
            </td>
        `;
        tabla.appendChild(tr);
        adjuntarEventoQuitar(tr.querySelector('.btn-quitar-conducto'));
    });

    function adjuntarEventoQuitar(btn) {
        btn.addEventListener('click', (e) => {
            const fila = e.target.closest('tr');
            if (tabla.querySelectorAll('.fila-conducto').length > 1) {
                fila.remove();
            } else {
                alert('Debe conservar al menos un conducto en la matriz.');
            }
        });
    }

    document.querySelectorAll('.btn-quitar-conducto').forEach(adjuntarEventoQuitar);
});
</script>
@endpush
@endsection
