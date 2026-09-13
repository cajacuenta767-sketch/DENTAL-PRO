@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $paciente->exists ? 'Editar Paciente' : 'Nuevo Paciente')

@section('contenido')
<form method="POST" action="{{ $paciente->exists ? route('admin.pacientes.update', $paciente) : route('admin.pacientes.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($paciente->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-id me-2"></i>Datos personales</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="nombres" etiqueta="Nombres" requerido>
                                <input type="text" id="nombres" name="nombres" class="form-control"
                                       value="{{ old('nombres', $paciente->nombres) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="apellidos" etiqueta="Apellidos" requerido>
                                <input type="text" id="apellidos" name="apellidos" class="form-control"
                                       value="{{ old('apellidos', $paciente->apellidos) }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <x-campo nombre="tipo_documento" etiqueta="Tipo doc." requerido>
                                <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                    @foreach (['CI', 'DNI', 'PASAPORTE', 'CE'] as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo_documento', $paciente->tipo_documento) === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="numero_documento" etiqueta="N° documento" requerido>
                                <input type="text" id="numero_documento" name="numero_documento" class="form-control"
                                       value="{{ old('numero_documento', $paciente->numero_documento) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento">
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                       value="{{ old('fecha_nacimiento', $paciente->fecha_nacimiento?->format('Y-m-d')) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="genero" etiqueta="Género" requerido>
                                <select id="genero" name="genero" class="form-select" required>
                                    <option value="M" @selected(old('genero', $paciente->genero) === 'M')>Masculino</option>
                                    <option value="F" @selected(old('genero', $paciente->genero) === 'F')>Femenino</option>
                                    <option value="O" @selected(old('genero', $paciente->genero) === 'O')>Otro</option>
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control"
                                       value="{{ old('telefono', $paciente->telefono) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="email" etiqueta="Correo electrónico" ayuda="Se usa para enviar la confirmación de la cita.">
                                <input type="email" id="email" name="email" class="form-control"
                                       value="{{ old('email', $paciente->email) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="grupo_sanguineo" etiqueta="Grupo sanguíneo">
                                <select id="grupo_sanguineo" name="grupo_sanguineo" class="form-select">
                                    <option value="">— No registra —</option>
                                    @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $grupo)
                                        <option value="{{ $grupo }}" @selected(old('grupo_sanguineo', $paciente->grupo_sanguineo) === $grupo)>{{ $grupo }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="direccion" etiqueta="Dirección">
                        <textarea id="direccion" name="direccion" class="form-control" rows="2">{{ old('direccion', $paciente->direccion) }}</textarea>
                    </x-campo>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-heartbeat me-2"></i>Antecedentes médicos</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="alergias" etiqueta="Alergias" ayuda="Medicamentos, anestésicos, látex…">
                                <textarea id="alergias" name="alergias" class="form-control" rows="2">{{ old('alergias', $paciente->alergias) }}</textarea>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="enfermedades" etiqueta="Enfermedades de base">
                                <textarea id="enfermedades" name="enfermedades" class="form-control" rows="2">{{ old('enfermedades', $paciente->enfermedades) }}</textarea>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="medicamentos" etiqueta="Medicación actual">
                                <textarea id="medicamentos" name="medicamentos" class="form-control" rows="2">{{ old('medicamentos', $paciente->medicamentos) }}</textarea>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="habitos" etiqueta="Hábitos" ayuda="Tabaco, bruxismo, higiene bucal…">
                                <textarea id="habitos" name="habitos" class="form-control" rows="2">{{ old('habitos', $paciente->habitos) }}</textarea>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="antecedentes" etiqueta="Antecedentes odontológicos y familiares">
                        <textarea id="antecedentes" name="antecedentes" class="form-control" rows="2">{{ old('antecedentes', $paciente->antecedentes) }}</textarea>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2">{{ old('observaciones', $paciente->observaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-urgent me-2"></i>Contacto de emergencia</h3></div>
                <div class="card-body">
                    <x-campo nombre="contacto_emergencia" etiqueta="Nombre del contacto">
                        <input type="text" id="contacto_emergencia" name="contacto_emergencia" class="form-control"
                               value="{{ old('contacto_emergencia', $paciente->contacto_emergencia) }}">
                    </x-campo>
                    <x-campo nombre="telefono_emergencia" etiqueta="Teléfono de emergencia">
                        <input type="text" id="telefono_emergencia" name="telefono_emergencia" class="form-control"
                               value="{{ old('telefono_emergencia', $paciente->telefono_emergencia) }}">
                    </x-campo>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-photo me-2"></i>Fotografía y estado</h3></div>
                <div class="card-body">
                    @if ($paciente->fotografia)
                        <div class="text-center mb-3">
                            <span class="avatar avatar-xl" style="background-image: url({{ Storage::url($paciente->fotografia) }})"></span>
                        </div>
                    @endif
                    <x-campo nombre="foto" etiqueta="Fotografía" ayuda="JPG o PNG, máximo 2 MB.">
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                    </x-campo>
                    <label class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" class="form-check-input"
                               {{ old('activo', $paciente->activo ?? true) ? 'checked' : '' }}>
                        <span class="form-check-label">Paciente activo</span>
                    </label>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.pacientes.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
