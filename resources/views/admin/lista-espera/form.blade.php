@extends('layouts.admin')

@section('pretitulo', 'Administración')
@section('titulo', $entrada->exists ? 'Editar entrada de la lista de espera' : 'Añadir a la lista de espera')

@section('contenido')
<form method="POST" action="{{ $entrada->exists ? route('admin.lista-espera.update', $entrada) : route('admin.lista-espera.store') }}">
    @csrf
    @if ($entrada->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-hourglass me-2"></i>Solicitud de turno</h3></div>
                <div class="card-body">
                    <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                        <select id="paciente_id" name="paciente_id" class="form-select" required>
                            <option value="">— Selecciona un paciente —</option>
                            @foreach ($pacientes as $paciente)
                                <option value="{{ $paciente->id }}" @selected(old('paciente_id', $entrada->paciente_id) == $paciente->id)>
                                    {{ $paciente->apellidos }}, {{ $paciente->nombres }} · {{ $paciente->numero_documento }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="doctor_id" etiqueta="Doctor" ayuda="Vacío = cualquier doctor disponible.">
                                <select id="doctor_id" name="doctor_id" class="form-select">
                                    <option value="">— Cualquier doctor —</option>
                                    @foreach ($doctores as $doctor)
                                        <option value="{{ $doctor->id }}" data-especialidad="{{ $doctor->especialidad_id }}"
                                                @selected(old('doctor_id', $entrada->doctor_id) == $doctor->id)>
                                            {{ $doctor->nombre_profesional }} · {{ $doctor->especialidad?->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="especialidad_id" etiqueta="Especialidad">
                                <select id="especialidad_id" name="especialidad_id" class="form-select">
                                    <option value="">— Sin preferencia —</option>
                                    @foreach ($especialidades as $especialidad)
                                        <option value="{{ $especialidad->id }}" @selected(old('especialidad_id', $entrada->especialidad_id) == $especialidad->id)>
                                            {{ $especialidad->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="tratamiento_id" etiqueta="Tratamiento solicitado">
                        <select id="tratamiento_id" name="tratamiento_id" class="form-select">
                            <option value="">— Sin definir —</option>
                            @foreach ($tratamientos as $tratamiento)
                                <option value="{{ $tratamiento->id }}" @selected(old('tratamiento_id', $entrada->tratamiento_id) == $tratamiento->id)>
                                    {{ $tratamiento->nombre }}
                                    @if ($tratamiento->especialidad) · {{ $tratamiento->especialidad->nombre }} @endif
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="fecha_desde" etiqueta="Disponible desde">
                                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control"
                                       value="{{ old('fecha_desde', $entrada->fecha_desde?->format('Y-m-d')) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="fecha_hasta" etiqueta="Disponible hasta" ayuda="Vacío = sin límite.">
                                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control"
                                       value="{{ old('fecha_hasta', $entrada->fecha_hasta?->format('Y-m-d')) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="preferencia_turno" etiqueta="Turno preferido" requerido>
                                <select id="preferencia_turno" name="preferencia_turno" class="form-select" required>
                                    @foreach (\App\Models\ListaEspera::TURNOS as $clave => $etiqueta)
                                        <option value="{{ $clave }}" @selected(old('preferencia_turno', $entrada->preferencia_turno ?? 'CUALQUIERA') === $clave)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="notas" etiqueta="Notas internas" ayuda="Máximo 1000 caracteres.">
                        <textarea id="notas" name="notas" class="form-control" rows="3" maxlength="1000">{{ old('notas', $entrada->notas) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Seguimiento</h3></div>
                <div class="card-body">
                    <x-campo nombre="prioridad" etiqueta="Prioridad" requerido>
                        <select id="prioridad" name="prioridad" class="form-select" required>
                            @foreach (\App\Models\ListaEspera::PRIORIDADES as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('prioridad', $entrada->prioridad ?? 'NORMAL') === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="estado" etiqueta="Estado" requerido
                             ayuda="El estado «Agendado» se asigna automáticamente al crear la cita desde el listado.">
                        <select id="estado" name="estado" class="form-select" required>
                            @foreach (['ESPERANDO', 'CONTACTADO', 'CANCELADO'] as $clave)
                                <option value="{{ $clave }}" @selected(old('estado', $entrada->estado ?? 'ESPERANDO') === $clave)>
                                    {{ \App\Models\ListaEspera::ESTADOS[$clave] }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    @if ($entrada->exists)
                        <div class="text-secondary small">
                            <div>Registrada el {{ $entrada->created_at?->format('d/m/Y H:i') }}</div>
                            @if ($entrada->registradoPor)
                                <div>por {{ $entrada->registradoPor->nombre }}</div>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.lista-espera.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $entrada->exists ? 'Guardar cambios' : 'Añadir a la lista' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    // Al elegir un doctor se sugiere su especialidad si aún no hay una marcada.
    const doctor = document.getElementById('doctor_id');
    const especialidad = document.getElementById('especialidad_id');
    if (!doctor || !especialidad) return;

    doctor.addEventListener('change', () => {
        const sugerida = doctor.selectedOptions[0]?.dataset.especialidad;
        if (sugerida && !especialidad.value) especialidad.value = sugerida;
    });
})();
</script>
@endpush
@endsection
