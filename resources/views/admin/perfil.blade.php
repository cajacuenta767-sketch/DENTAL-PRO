@extends('layouts.admin')

@section('pretitulo', 'Cuenta')
@section('titulo', 'Mi Perfil')

@section('contenido')
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos personales</h3></div>
                <div class="card-body">
                    <x-campo nombre="nombre" etiqueta="Nombre completo" requerido>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="{{ old('nombre', $usuario->nombre) }}" required>
                    </x-campo>
                    <x-campo nombre="email" etiqueta="Correo electrónico" requerido
                             ayuda="Si lo cambias deberás verificarlo de nuevo.">
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $usuario->email) }}" required>
                    </x-campo>
                    <x-campo nombre="telefono" etiqueta="Teléfono">
                        <input type="text" id="telefono" name="telefono" class="form-control" value="{{ old('telefono', $usuario->telefono) }}">
                    </x-campo>
                    <x-campo nombre="foto" etiqueta="Foto de perfil" ayuda="JPG o PNG, máximo 2 MB.">
                        <input type="file" id="foto" name="foto" class="form-control" accept="image/*">
                    </x-campo>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Guardar perfil</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body text-center">
                @if ($usuario->avatar)
                    <span class="avatar avatar-xl mb-3" style="background-image: url({{ $usuario->avatar }})"></span>
                @else
                    <span class="avatar avatar-xl bg-brand mb-3">{{ $usuario->iniciales }}</span>
                @endif
                <h3 class="mb-1">{{ $usuario->nombre }}</h3>
                <div class="text-secondary">{{ $usuario->email }}</div>
                <div class="mt-2">
                    @foreach ($usuario->roles as $rol)
                        <span class="badge bg-blue-lt">{{ $rol->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('perfil.password') }}" class="mt-3">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><h3 class="card-title">Cambiar contraseña</h3></div>
                <div class="card-body">
                    <x-campo nombre="password_actual" etiqueta="Contraseña actual" requerido>
                        <input type="password" id="password_actual" name="password_actual" class="form-control" autocomplete="current-password" required>
                    </x-campo>
                    <x-campo nombre="password" etiqueta="Nueva contraseña" requerido ayuda="Mínimo 8 caracteres.">
                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required>
                    </x-campo>
                    <x-campo nombre="password_confirmation" etiqueta="Confirmar nueva contraseña" requerido>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                    </x-campo>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-outline-primary"><i class="ti ti-key me-1"></i>Cambiar contraseña</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
