@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', $especialidad->exists ? 'Editar Especialidad' : 'Nueva Especialidad')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <form method="POST" action="{{ $especialidad->exists ? route('admin.especialidades.update', $especialidad) : route('admin.especialidades.store') }}">
            @csrf
            @if ($especialidad->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <x-campo nombre="nombre" etiqueta="Nombre" requerido>
                        <input type="text" id="nombre" name="nombre" class="form-control"
                               value="{{ old('nombre', $especialidad->nombre) }}" required>
                    </x-campo>

                    <x-campo nombre="descripcion" etiqueta="Descripción">
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3">{{ old('descripcion', $especialidad->descripcion) }}</textarea>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="color" etiqueta="Color identificador" requerido
                                     ayuda="Se usa en la agenda y en los listados.">
                                <input type="color" id="color" name="color" class="form-control form-control-color"
                                       value="{{ old('color', $especialidad->color ?: '#0d9488') }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <label class="form-check form-switch">
                                <input type="checkbox" name="activo" value="1" class="form-check-input"
                                       {{ old('activo', $especialidad->activo ?? true) ? 'checked' : '' }}>
                                <span class="form-check-label">Especialidad activa</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.especialidades.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
