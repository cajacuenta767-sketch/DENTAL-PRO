@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $documento->exists ? 'Editar '.$documento->tipo_legible : 'Nuevo Documento Clínico')

@section('contenido')
<form method="POST" action="{{ $documento->exists ? route('admin.documentos.update', $documento) : route('admin.documentos.store') }}">
    @csrf
    @if ($documento->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-writing me-2"></i>Contenido del documento</h3>
                    @unless ($documento->exists)
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-os-plantilla>
                            <i class="ti ti-template me-1"></i>Cargar plantilla del tipo
                        </button>
                    @endunless
                </div>
                <div class="card-body">
                    <x-campo nombre="titulo" etiqueta="Título del documento" requerido>
                        <input type="text" id="titulo" name="titulo" class="form-control"
                               value="{{ old('titulo', $documento->titulo) }}" required>
                    </x-campo>

                    <x-campo nombre="contenido" etiqueta="Contenido" requerido
                             ayuda="Este texto se imprime tal cual en el PDF firmado por el doctor.">
                        <textarea id="contenido" name="contenido" class="form-control font-monospace" rows="14"
                                  required>{{ old('contenido', $documento->contenido) }}</textarea>
                    </x-campo>

                    <x-campo nombre="indicaciones" etiqueta="Indicaciones adicionales">
                        <textarea id="indicaciones" name="indicaciones" class="form-control" rows="3">{{ old('indicaciones', $documento->indicaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos de emisión</h3></div>
                <div class="card-body">
                    @if ($documento->exists)
                        <div class="mb-3">
                            <div class="text-secondary small text-uppercase">Folio</div>
                            <div class="h3 mb-0 font-monospace">{{ $documento->folio }}</div>
                        </div>
                    @endif

                    <x-campo nombre="tipo" etiqueta="Tipo de documento" requerido>
                        <select id="tipo" name="tipo" class="form-select" required>
                            @foreach (\App\Models\DocumentoClinico::TIPOS as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('tipo', $documento->tipo) === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                        <select id="paciente_id" name="paciente_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($pacientes as $paciente)
                                <option value="{{ $paciente->id }}" @selected(old('paciente_id', $documento->paciente_id) == $paciente->id)>
                                    {{ $paciente->nombre_completo }} · {{ $paciente->numero_documento }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor que firma" requerido>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $documento->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $documento->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-7">
                            <x-campo nombre="fecha_emision" etiqueta="Fecha de emisión" requerido>
                                <input type="date" id="fecha_emision" name="fecha_emision" class="form-control"
                                       max="{{ now()->toDateString() }}"
                                       value="{{ old('fecha_emision', $documento->fecha_emision?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-5">
                            <x-campo nombre="vigencia_dias" etiqueta="Vigencia" ayuda="Días. Vacío = sin vencimiento.">
                                <input type="number" id="vigencia_dias" name="vigencia_dias" class="form-control"
                                       min="1" max="3650" value="{{ old('vigencia_dias', $documento->vigencia_dias) }}">
                            </x-campo>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.documentos.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $documento->exists ? 'Guardar' : 'Emitir documento' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const PLANTILLAS = @json($plantillas);
    const TITULOS = @json(\App\Models\DocumentoClinico::TIPOS);

    const tipo = document.getElementById('tipo');
    const contenido = document.getElementById('contenido');
    const titulo = document.getElementById('titulo');
    const boton = document.querySelector('[data-os-plantilla]');

    function cargar() {
        contenido.value = PLANTILLAS[tipo.value] ?? '';
        titulo.value = TITULOS[tipo.value] ?? '';
    }

    boton?.addEventListener('click', cargar);

    // Cambiar de tipo recarga la plantilla solo si el campo sigue intacto.
    tipo.addEventListener('change', () => {
        const sinTocar = Object.values(PLANTILLAS).includes(contenido.value) || contenido.value.trim() === '';
        if (sinTocar) cargar();
    });
})();
</script>
@endpush
@endsection
