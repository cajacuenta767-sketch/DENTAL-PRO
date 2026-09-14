@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', $sucursal->exists ? 'Editar Sede' : 'Nueva Sede')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="{{ $sucursal->exists ? route('admin.sucursales.update', $sucursal) : route('admin.sucursales.store') }}">
            @csrf
            @if ($sucursal->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="nombre" etiqueta="Nombre de la sede" requerido>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="{{ old('nombre', $sucursal->nombre) }}" maxlength="120" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="codigo" etiqueta="Código" requerido ayuda="Hasta 10 caracteres en mayúsculas.">
                                <input type="text" id="codigo" name="codigo" class="form-control text-uppercase font-monospace"
                                       value="{{ old('codigo', $sucursal->codigo) }}" maxlength="10" required>
                            </x-campo>
                        </div>
                        <div class="col-md-2">
                            <x-campo nombre="color" etiqueta="Color" requerido>
                                <input type="color" id="color" name="color" class="form-control form-control-color w-100"
                                       value="{{ old('color', $sucursal->color ?? '#0d9488') }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="direccion" etiqueta="Dirección">
                        <input type="text" id="direccion" name="direccion" class="form-control"
                               value="{{ old('direccion', $sucursal->direccion) }}" maxlength="500">
                    </x-campo>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control"
                                       value="{{ old('telefono', $sucursal->telefono) }}" maxlength="50">
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="email" etiqueta="Correo">
                                <input type="email" id="email" name="email" class="form-control"
                                       value="{{ old('email', $sucursal->email) }}" maxlength="150">
                            </x-campo>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-4">
                        <label class="form-check form-switch mb-0">
                            <input type="checkbox" name="principal" value="1" class="form-check-input"
                                   {{ old('principal', $sucursal->principal) ? 'checked' : '' }}>
                            <span class="form-check-label">Sede principal</span>
                            <small class="d-block text-secondary">Se usa por defecto cuando no se elige otra. Solo puede haber una.</small>
                        </label>
                        <label class="form-check form-switch mb-0">
                            <input type="checkbox" name="activo" value="1" class="form-check-input"
                                   {{ old('activo', $sucursal->activo ?? true) ? 'checked' : '' }}>
                            <span class="form-check-label">Sede activa</span>
                            <small class="d-block text-secondary">Una sede inactiva desaparece del selector y de los formularios.</small>
                        </label>
                    </div>
                    @error('principal')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.sucursales.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
