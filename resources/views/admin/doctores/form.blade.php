@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $doctor->exists ? 'Editar Doctor' : 'Nuevo Doctor')

@section('contenido')
<form method="POST" action="{{ $doctor->exists ? route('admin.doctores.update', $doctor) : route('admin.doctores.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if ($doctor->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos personales</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="nombres" etiqueta="Nombres" requerido>
                                <input type="text" id="nombres" name="nombres" class="form-control"
                                       value="{{ old('nombres', $doctor->nombres) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="apellidos" etiqueta="Apellidos" requerido>
                                <input type="text" id="apellidos" name="apellidos" class="form-control"
                                       value="{{ old('apellidos', $doctor->apellidos) }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <x-campo nombre="tipo_documento" etiqueta="Tipo doc." requerido>
                                <select id="tipo_documento" name="tipo_documento" class="form-select" required>
                                    @foreach (['CI', 'DNI', 'PASAPORTE', 'CE'] as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo_documento', $doctor->tipo_documento) === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="numero_documento" etiqueta="Número de documento" requerido>
                                <input type="text" id="numero_documento" name="numero_documento" class="form-control"
                                       value="{{ old('numero_documento', $doctor->numero_documento) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento">
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="form-control"
                                       value="{{ old('fecha_nacimiento', $doctor->fecha_nacimiento?->format('Y-m-d')) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-2">
                            <x-campo nombre="genero" etiqueta="Género" requerido>
                                <select id="genero" name="genero" class="form-select" required>
                                    <option value="M" @selected(old('genero', $doctor->genero) === 'M')>M</option>
                                    <option value="F" @selected(old('genero', $doctor->genero) === 'F')>F</option>
                                    <option value="O" @selected(old('genero', $doctor->genero) === 'O')>Otro</option>
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control"
                                       value="{{ old('telefono', $doctor->telefono) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="email" etiqueta="Correo electrónico">
                                <input type="email" id="email" name="email" class="form-control"
                                       value="{{ old('email', $doctor->email) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="colegiatura" etiqueta="N° de colegiatura">
                                <input type="text" id="colegiatura" name="colegiatura" class="form-control"
                                       value="{{ old('colegiatura', $doctor->colegiatura) }}">
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="direccion" etiqueta="Dirección">
                        <textarea id="direccion" name="direccion" class="form-control" rows="2">{{ old('direccion', $doctor->direccion) }}</textarea>
                    </x-campo>

                    <x-campo nombre="descripcion" etiqueta="Perfil profesional" ayuda="Se muestra en la ficha del doctor.">
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $doctor->descripcion) }}</textarea>
                    </x-campo>

                    <x-campo nombre="observaciones" etiqueta="Observaciones internas">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="2">{{ old('observaciones', $doctor->observaciones) }}</textarea>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos profesionales</h3></div>
                <div class="card-body">
                    <x-campo nombre="especialidad_id" etiqueta="Especialidad" requerido>
                        <select id="especialidad_id" name="especialidad_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($especialidades as $especialidad)
                                <option value="{{ $especialidad->id }}"
                                    @selected(old('especialidad_id', $doctor->especialidad_id) == $especialidad->id)>
                                    {{ $especialidad->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="usuario_id" etiqueta="Usuario del sistema"
                             ayuda="Enlázalo para que el doctor entre a Mi Agenda. Solo aparecen usuarios con rol DOCTOR sin ficha.">
                        <select id="usuario_id" name="usuario_id" class="form-select">
                            <option value="">— Sin enlazar —</option>
                            @foreach ($usuarios as $usuario)
                                <option value="{{ $usuario->id }}" @selected(old('usuario_id', $doctor->usuario_id) == $usuario->id)>
                                    {{ $usuario->nombre }} · {{ $usuario->email }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="foto" etiqueta="Fotografía" ayuda="JPG o PNG, máximo 2 MB.">
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                    </x-campo>

                    <label class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" class="form-check-input"
                               {{ old('activo', $doctor->activo ?? true) ? 'checked' : '' }}>
                        <span class="form-check-label">Doctor activo</span>
                    </label>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.doctores.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
