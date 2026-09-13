@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $historial->exists ? 'Editar Consulta' : 'Nueva Consulta')
@section('subtitulo', $paciente->nombre_completo)

@section('contenido')
<form method="POST" action="{{ $historial->exists ? route('admin.historiales.update', $historial) : route('admin.historiales.store', $paciente) }}">
    @csrf
    @if ($historial->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-notes-medical me-2"></i>Registro de la consulta</h3></div>
                <div class="card-body">
                    <x-campo nombre="motivo_consulta" etiqueta="Motivo de consulta" requerido>
                        <textarea id="motivo_consulta" name="motivo_consulta" class="form-control" rows="2" required>{{ old('motivo_consulta', $historial->motivo_consulta) }}</textarea>
                    </x-campo>

                    <x-campo nombre="sintomas" etiqueta="Síntomas referidos">
                        <textarea id="sintomas" name="sintomas" class="form-control" rows="2">{{ old('sintomas', $historial->sintomas) }}</textarea>
                    </x-campo>

                    <x-campo nombre="diagnostico" etiqueta="Diagnóstico" requerido>
                        <textarea id="diagnostico" name="diagnostico" class="form-control" rows="3" required>{{ old('diagnostico', $historial->diagnostico) }}</textarea>
                    </x-campo>

                    <x-campo nombre="tratamiento_realizado" etiqueta="Tratamiento realizado">
                        <textarea id="tratamiento_realizado" name="tratamiento_realizado" class="form-control" rows="3">{{ old('tratamiento_realizado', $historial->tratamiento_realizado) }}</textarea>
                    </x-campo>

                    <x-campo nombre="prescripcion_receta" etiqueta="Prescripción / receta"
                             ayuda="Medicamento, dosis y duración.">
                        <textarea id="prescripcion_receta" name="prescripcion_receta" class="form-control" rows="3">{{ old('prescripcion_receta', $historial->prescripcion_receta) }}</textarea>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones e indicaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2">{{ old('observaciones', $historial->observaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos de la atención</h3></div>
                <div class="card-body">
                    <x-campo nombre="fecha" etiqueta="Fecha de la consulta" requerido>
                        <input type="date" id="fecha" name="fecha" class="form-control" max="{{ now()->toDateString() }}"
                               value="{{ old('fecha', $historial->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor responsable" requerido>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $historial->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="cita_id" etiqueta="Cita asociada" ayuda="Opcional: enlaza esta consulta con una cita.">
                        <select id="cita_id" name="cita_id" class="form-select">
                            <option value="">— Sin cita asociada —</option>
                            @foreach ($citas as $cita)
                                <option value="{{ $cita->id }}" @selected(old('cita_id', $historial->cita_id) == $cita->id)>
                                    {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.historiales.index', $paciente) }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title text-warning"><i class="ti ti-alert-triangle me-2"></i>Alertas</h3></div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">Alergias</div>
                        <div>{{ $paciente->alergias ?: 'Sin registro' }}</div>
                    </div>
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">Enfermedades</div>
                        <div>{{ $paciente->enfermedades ?: 'Sin registro' }}</div>
                    </div>
                    <div>
                        <div class="text-secondary small text-uppercase">Medicación actual</div>
                        <div>{{ $paciente->medicamentos ?: 'Sin registro' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
