@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', $usuario->exists ? 'Editar Usuario' : 'Nuevo Usuario')

@section('contenido')
<form method="POST" action="{{ $usuario->exists ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
    @csrf
    @if ($usuario->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos de acceso</h3></div>
                <div class="card-body">
                    <x-campo nombre="nombre" etiqueta="Nombre completo" requerido>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="{{ old('nombre', $usuario->nombre) }}" required>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="email" etiqueta="Correo electrónico" requerido>
                                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $usuario->email) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control" value="{{ old('telefono', $usuario->telefono) }}">
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="password" etiqueta="Contraseña" :requerido="! $usuario->exists"
                                     :ayuda="$usuario->exists ? 'Déjala vacía para conservar la actual.' : 'Mínimo 8 caracteres.'">
                                <input type="password" id="password" name="password" class="form-control"
                                       autocomplete="new-password" {{ $usuario->exists ? '' : 'required' }}>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="password_confirmation" etiqueta="Confirmar contraseña" :requerido="! $usuario->exists">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                                       autocomplete="new-password" {{ $usuario->exists ? '' : 'required' }}>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="estado" etiqueta="Estado" requerido>
                        <select id="estado" name="estado" class="form-select" required>
                            <option value="activo" @selected(old('estado', $usuario->estado) === 'activo')>Activo</option>
                            <option value="inactivo" @selected(old('estado', $usuario->estado) === 'inactivo')>Inactivo</option>
                        </select>
                    </x-campo>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Roles asignados</h3></div>
                <div class="card-body">
                    @foreach ($roles as $rol)
                        <label class="form-check">
                            <input type="checkbox" name="roles[]" value="{{ $rol->name }}" class="form-check-input"
                                   {{ in_array($rol->name, old('roles', $asignados), true) ? 'checked' : '' }}>
                            <span class="form-check-label">
                                {{ $rol->name }}
                                <small class="d-block text-secondary">
                                    {{ config("odontosuite.roles.{$rol->name}.descripcion", $rol->permissions_count.' permisos') }}
                                </small>
                            </span>
                        </label>
                    @endforeach
                    @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.usuarios.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $usuario->exists ? 'Guardar cambios' : 'Crear usuario' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
