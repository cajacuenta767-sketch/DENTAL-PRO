@extends('layouts.admin')

@section('pretitulo', 'Laboratorio')
@section('titulo', 'Editar Orden: ' . $orden->folio)

@section('acciones')
    <a href="{{ route('admin.laboratorio.show', $orden) }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Ver orden
    </a>
@endsection

@section('contenido')
<form method="POST" action="{{ route('admin.laboratorio.update', $orden) }}">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-dental me-2"></i>Especificaciones del trabajo protésico</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="tipo_trabajo">Tipo de trabajo / Prótesis</label>
                        <input type="text" id="tipo_trabajo" name="tipo_trabajo" class="form-control @error('tipo_trabajo') is-invalid @enderror"
                               value="{{ old('tipo_trabajo', $orden->tipo_trabajo) }}" list="lista-trabajos-frecuentes" placeholder="Ej: Corona Zirconia Monolítica" required>
                        <datalist id="lista-trabajos-frecuentes">
                            <option value="Corona Zirconia Monolítica">
                            <option value="Corona Metal-Porcelana">
                            <option value="Incrustación E.max (Inlay/Onlay)">
                            <option value="Carilla Disilicato de Litio">
                            <option value="Prótesis Total Acrílico (Superior)">
                            <option value="Prótesis Total Acrílico (Inferior)">
                            <option value="Prótesis Parcial Removible Cromo-Cobalto">
                            <option value="Prótesis Parcial Flexible (Valplast)">
                            <option value="Placa Miorrelajante (Bruxismo)">
                            <option value="Perno Muñón Colado">
                            <option value="Provisorio de Acrílico">
                            <option value="Híbrida sobre Implantes">
                        </datalist>
                        @error('tipo_trabajo')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <label class="form-label" for="piezas_dentales">Piezas dentales (FDI)</label>
                            <input type="text" id="piezas_dentales" name="piezas_dentales" class="form-control font-monospace @error('piezas_dentales') is-invalid @enderror"
                                   value="{{ old('piezas_dentales', $orden->piezas_dentales) }}" placeholder="Ej: 11, 12, 21">
                            <small class="form-hint">Selecciona con los botones rápidos o escribe separadas por coma.</small>
                            @error('piezas_dentales')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <div class="mt-2 p-2 border rounded bg-body-tertiary">
                                <div class="small fw-bold text-secondary mb-1">Dientes frecuentes / Cuadrantes:</div>
                                <div class="d-flex flex-wrap gap-1" id="fdi-chips">
                                    @php $piezasSel = array_map('trim', explode(',', $orden->piezas_dentales ?? '')); @endphp
                                    @foreach (['11','12','13','14','15','16','17','21','22','23','24','25','26','27','31','32','33','34','35','36','37','41','42','43','44','45','46','47'] as $pieza)
                                        <button type="button" class="btn btn-sm {{ in_array($pieza, $piezasSel) ? 'btn-primary' : 'btn-outline-secondary' }} py-0 px-1 font-monospace fdi-btn" data-pieza="{{ $pieza }}" style="font-size: 0.75rem;">
                                            {{ $pieza }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-md-5 mb-3">
                            <label class="form-label" for="color_vita">Color / Guía VITA</label>
                            <select id="color_vita" name="color_vita" class="form-select @error('color_vita') is-invalid @enderror">
                                <option value="">— Sin especificar —</option>
                                @foreach ($coloresVita as $color)
                                    <option value="{{ $color }}" @selected(old('color_vita', $orden->color_vita) === $color)>{{ $color }}</option>
                                @endforeach
                            </select>
                            <small class="form-hint">Escala VITA Classical o Bleach (BL).</small>
                            @error('color_vita')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="tratamiento_id">Tratamiento clínico asociado (opcional)</label>
                        <select id="tratamiento_id" name="tratamiento_id" class="form-select @error('tratamiento_id') is-invalid @enderror">
                            <option value="">— Ninguno / Trabajo independiente —</option>
                            @foreach ($tratamientos as $tr)
                                <option value="{{ $tr->id }}" @selected(old('tratamiento_id', $orden->tratamiento_id) == $tr->id)>
                                    {{ $tr->nombre }} ({{ $tr->categoria }})
                                </option>
                            @endforeach
                        </select>
                        @error('tratamiento_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="notas_tecnicas">Instrucciones técnicas para el protesista</label>
                        <textarea id="notas_tecnicas" name="notas_tecnicas" class="form-control @error('notas_tecnicas') is-invalid @enderror" rows="5"
                                  placeholder="Indicaciones de oclusión, tipo de margen (chamfer, hombro cerámico), anatomía oclusal, espacio interoclusal, pónticos ovalados, etc.">{{ old('notas_tecnicas', $orden->notas_tecnicas) }}</textarea>
                        @error('notas_tecnicas')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-users me-2"></i>Asignación</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Paciente</label>
                        <x-selector-paciente nombre="paciente_id" :seleccionado="old('paciente_id', $orden->paciente_id)" requerido />
                        @error('paciente_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="doctor_id">Doctor odontólogo</label>
                        <select id="doctor_id" name="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" required>
                            <option value="">— Selecciona doctor —</option>
                            @foreach ($doctores as $doc)
                                <option value="{{ $doc->id }}" @selected(old('doctor_id', $orden->doctor_id) == $doc->id)>
                                    {{ $doc->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                        @error('doctor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="laboratorio_id">Laboratorio dental</label>
                        <select id="laboratorio_id" name="laboratorio_id" class="form-select @error('laboratorio_id') is-invalid @enderror" required>
                            <option value="">— Selecciona laboratorio —</option>
                            @foreach ($laboratorios as $lab)
                                <option value="{{ $lab->id }}" @selected(old('laboratorio_id', $orden->laboratorio_id) == $lab->id)>
                                    {{ $lab->nombre }} @if($lab->contacto) ({{ $lab->contacto }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('laboratorio_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-calendar-time me-2"></i>Fechas y Costos</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required" for="fecha_envio">Fecha de envío</label>
                        <input type="date" id="fecha_envio" name="fecha_envio" class="form-control @error('fecha_envio') is-invalid @enderror"
                               value="{{ old('fecha_envio', $orden->fecha_envio?->toDateString()) }}" required>
                        @error('fecha_envio')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="fecha_prometida">Fecha prometida de entrega</label>
                        <input type="date" id="fecha_prometida" name="fecha_prometida" class="form-control @error('fecha_prometida') is-invalid @enderror"
                               value="{{ old('fecha_prometida', $orden->fecha_prometida?->toDateString()) }}" required>
                        @error('fecha_prometida')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="fecha_entrega">Fecha real de entrega / Instalación</label>
                        <input type="date" id="fecha_entrega" name="fecha_entrega" class="form-control @error('fecha_entrega') is-invalid @enderror"
                               value="{{ old('fecha_entrega', $orden->fecha_entrega?->toDateString()) }}">
                        <small class="form-hint">Registrar al momento de recibir o instalar en boca.</small>
                        @error('fecha_entrega')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label" for="costo_laboratorio">Costo Lab ($)</label>
                            <input type="number" step="0.01" min="0" id="costo_laboratorio" name="costo_laboratorio"
                                   class="form-control @error('costo_laboratorio') is-invalid @enderror"
                                   value="{{ old('costo_laboratorio', $orden->costo_laboratorio) }}" placeholder="0.00">
                            @error('costo_laboratorio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" for="precio_paciente">Precio Paciente ($)</label>
                            <input type="number" step="0.01" min="0" id="precio_paciente" name="precio_paciente"
                                   class="form-control @error('precio_paciente') is-invalid @enderror"
                                   value="{{ old('precio_paciente', $orden->precio_paciente) }}" placeholder="0.00">
                            @error('precio_paciente')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required" for="estado">Estado de la orden</label>
                        <select id="estado" name="estado" class="form-select @error('estado') is-invalid @enderror" required>
                            @foreach ($estados as $clave => $meta)
                                <option value="{{ $clave }}" @selected(old('estado', $orden->estado) === $clave)>
                                    {{ is_array($meta) ? ($meta['nombre'] ?? $clave) : $meta }}
                                </option>
                            @endforeach
                        </select>
                        @error('estado')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.laboratorio.show', $orden) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Actualizar orden
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const inputPiezas = document.getElementById('piezas_dentales');
    const botones = document.querySelectorAll('.fdi-btn');

    botones.forEach(btn => {
        btn.addEventListener('click', () => {
            const pieza = btn.getAttribute('data-pieza');
            let actuales = inputPiezas.value.split(',').map(s => s.trim()).filter(Boolean);
            if (actuales.includes(pieza)) {
                actuales = actuales.filter(p => p !== pieza);
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-secondary');
            } else {
                actuales.push(pieza);
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-primary');
            }
            inputPiezas.value = actuales.join(', ');
        });
    });
})();
</script>
@endpush
@endsection
