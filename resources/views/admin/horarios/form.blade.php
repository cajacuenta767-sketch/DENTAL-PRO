@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $horario->exists ? 'Editar Horario' : 'Nuevo Horario')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <form method="POST" action="{{ $horario->exists ? route('admin.horarios.update', $horario) : route('admin.horarios.store') }}">
            @csrf
            @if ($horario->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <x-campo nombre="doctor_id" etiqueta="Doctor" requerido>
                        <select id="doctor_id" name="doctor_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $horario->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }} · {{ $doctor->especialidad->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="dia_semana" etiqueta="Día de la semana" requerido>
                                <select id="dia_semana" name="dia_semana" class="form-select" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach ($dias as $clave => $etiqueta)
                                        <option value="{{ $clave }}" @selected(old('dia_semana', $horario->dia_semana) === $clave)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="turno" etiqueta="Turno" requerido>
                                <select id="turno" name="turno" class="form-select" required>
                                    @foreach (\App\Models\Horario::TURNOS as $turno)
                                        <option value="{{ $turno }}" @selected(old('turno', $horario->turno) === $turno)>{{ $turno }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="hora_inicio" etiqueta="Hora de inicio" requerido>
                                <input type="time" id="hora_inicio" name="hora_inicio" class="form-control"
                                       value="{{ old('hora_inicio', $horario->hora_inicio ? substr($horario->hora_inicio, 0, 5) : '08:00') }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="hora_fin" etiqueta="Hora de fin" requerido>
                                <input type="time" id="hora_fin" name="hora_fin" class="form-control"
                                       value="{{ old('hora_fin', $horario->hora_fin ? substr($horario->hora_fin, 0, 5) : '12:00') }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <label class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" class="form-check-input"
                               {{ old('activo', $horario->activo ?? true) ? 'checked' : '' }}>
                        <span class="form-check-label">Turno activo</span>
                    </label>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.horarios.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
