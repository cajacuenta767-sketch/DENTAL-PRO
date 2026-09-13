@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', $tratamiento->exists ? 'Editar Tratamiento' : 'Nuevo Tratamiento')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="{{ $tratamiento->exists ? route('admin.tratamientos.update', $tratamiento) : route('admin.tratamientos.store') }}">
            @csrf
            @if ($tratamiento->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="nombre" etiqueta="Nombre del tratamiento" requerido>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="{{ old('nombre', $tratamiento->nombre) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="especialidad_id" etiqueta="Especialidad" requerido>
                                <select id="especialidad_id" name="especialidad_id" class="form-select" required>
                                    <option value="">— Selecciona —</option>
                                    @foreach ($especialidades as $especialidad)
                                        <option value="{{ $especialidad->id }}"
                                            @selected(old('especialidad_id', $tratamiento->especialidad_id) == $especialidad->id)>
                                            {{ $especialidad->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="descripcion" etiqueta="Descripción">
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $tratamiento->descripcion) }}</textarea>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="precio" etiqueta="Precio" requerido>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                                    <input type="number" id="precio" name="precio" class="form-control" step="0.01" min="0"
                                           value="{{ old('precio', $tratamiento->precio) }}" required>
                                </div>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="duracion" etiqueta="Duración" requerido ayuda="En minutos.">
                                <div class="input-group">
                                    <input type="number" id="duracion" name="duracion" class="form-control" min="5" max="600" step="5"
                                           value="{{ old('duracion', $tratamiento->duracion) }}" required>
                                    <span class="input-group-text">min</span>
                                </div>
                            </x-campo>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <label class="form-check form-switch">
                                <input type="checkbox" name="activo" value="1" class="form-check-input"
                                       {{ old('activo', $tratamiento->activo ?? true) ? 'checked' : '' }}>
                                <span class="form-check-label">Disponible para agendar</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.tratamientos.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
