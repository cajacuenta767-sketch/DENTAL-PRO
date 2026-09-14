@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $estudio->exists ? 'Editar Estudio' : 'Cargar Estudio')

@section('contenido')
<form method="POST" enctype="multipart/form-data"
      action="{{ $estudio->exists ? route('admin.estudios.update', $estudio) : route('admin.estudios.store') }}">
    @csrf
    @if ($estudio->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-photo-scan me-2"></i>Datos del estudio</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                                <x-selector-paciente nombre="paciente_id" :seleccionado="$estudio->paciente_id" requerido />
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="tipo" etiqueta="Tipo de estudio" requerido>
                                <select id="tipo" name="tipo" class="form-select" required>
                                    @foreach (\App\Models\EstudioImagen::TIPOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}" @selected(old('tipo', $estudio->tipo) === $clave)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="titulo" etiqueta="Título" requerido ayuda="Por ejemplo: Panorámica de control.">
                                <input type="text" id="titulo" name="titulo" class="form-control"
                                       value="{{ old('titulo', $estudio->titulo) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="fecha_estudio" etiqueta="Fecha del estudio" requerido>
                                <input type="date" id="fecha_estudio" name="fecha_estudio" class="form-control"
                                       max="{{ now()->toDateString() }}"
                                       value="{{ old('fecha_estudio', $estudio->fecha_estudio?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="piezas_referidas" etiqueta="Piezas referidas"
                             ayuda="Numeración FDI separada por comas. Ejemplo: 16, 26, 36.">
                        <input type="text" id="piezas_referidas" name="piezas_referidas" class="form-control"
                               value="{{ old('piezas_referidas', $estudio->piezas_referidas) }}">
                    </x-campo>

                    <x-campo nombre="hallazgos" etiqueta="Hallazgos radiográficos">
                        <textarea id="hallazgos" name="hallazgos" class="form-control" rows="3">{{ old('hallazgos', $estudio->hallazgos) }}</textarea>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2">{{ old('observaciones', $estudio->observaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-file-upload me-2"></i>Archivo</h3></div>
                <div class="card-body">
                    @if ($estudio->exists)
                        @if ($estudio->es_visualizable)
                            <div class="ratio ratio-16x9 bg-dark rounded mb-3 overflow-hidden">
                                <img src="{{ $estudio->url }}" alt="{{ $estudio->titulo }}" style="object-fit: contain;">
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-3 p-3 border rounded mb-3">
                                <i class="ti {{ $estudio->icono }} fs-1 text-secondary"></i>
                                <div class="small">
                                    <div class="fw-medium text-uppercase">{{ $estudio->extension }}</div>
                                    <div class="text-secondary">Se descarga o se abre en el visor; no admite anotaciones.</div>
                                </div>
                            </div>
                        @endif
                        <div class="text-secondary small mb-3">
                            {{ $estudio->nombre_original }} · {{ $estudio->tamano_legible }}
                        </div>
                    @endif

                    @php($accept = \App\Rules\ArchivoClinico::accept())
                    @php($maximo = round(\App\Http\Controllers\Admin\EstudioImagenController::TAMANO_MAXIMO_KB / 1024))
                    @if ($estudio->exists)
                        <x-campo nombre="archivo" etiqueta="Reemplazar archivo"
                                 ayuda="Imagen (JPG, PNG, WEBP, TIFF, HEIC), PDF, documento (DOC, DOCX, ODT, RTF, TXT, XLS, XLSX) o DICOM. Máximo {{ $maximo }} MB.">
                            <input type="file" id="archivo" name="archivo" class="form-control" accept="{{ $accept }}">
                        </x-campo>
                    @else
                        <x-campo nombre="archivos" etiqueta="Archivos del estudio" requerido
                                 ayuda="Puede seleccionar varios a la vez (hasta 20): cada archivo queda como un estudio con estos mismos datos. Imagen (JPG, PNG, WEBP, TIFF, HEIC), PDF, documento (DOC, DOCX, ODT, RTF, TXT, XLS, XLSX) o DICOM. Máximo {{ $maximo }} MB por archivo.">
                            <input type="file" id="archivos" name="archivos[]" class="form-control" multiple required
                                   accept="{{ $accept }}" data-archivos-multiples>
                        </x-campo>
                        @error('archivos.*')
                            <div class="text-danger small mt-n2 mb-3">{{ $message }}</div>
                        @enderror
                        <div class="list-group list-group-flush mb-3 d-none" data-archivos-lista></div>
                    @endif

                    <x-campo nombre="doctor_id" etiqueta="Doctor que solicita">
                        <select id="doctor_id" name="doctor_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $estudio->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $estudio->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.estudios.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const entrada = document.querySelector('[data-archivos-multiples]');
    const lista = document.querySelector('[data-archivos-lista]');
    if (!entrada || !lista) return;

    const iconos = { pdf: 'ti-file-type-pdf', doc: 'ti-file-text', docx: 'ti-file-text', odt: 'ti-file-text', rtf: 'ti-file-text', txt: 'ti-file-text', xls: 'ti-file-spreadsheet', xlsx: 'ti-file-spreadsheet', dcm: 'ti-radioactive', dicom: 'ti-radioactive' };
    const legible = (b) => b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.round(b / 1024) + ' KB';

    entrada.addEventListener('change', () => {
        lista.innerHTML = '';
        const archivos = Array.from(entrada.files);
        lista.classList.toggle('d-none', archivos.length === 0);
        archivos.forEach((a) => {
            const ext = a.name.split('.').pop().toLowerCase();
            const fila = document.createElement('div');
            fila.className = 'list-group-item px-0 py-1 d-flex align-items-center gap-2 small';
            fila.innerHTML = `<i class="ti ${iconos[ext] ?? 'ti-photo'} text-secondary"></i><span class="text-truncate flex-fill"></span><span class="text-secondary"></span>`;
            fila.children[1].textContent = a.name;
            fila.children[2].textContent = legible(a.size);
            lista.appendChild(fila);
        });
    });
})();
</script>
@endpush
