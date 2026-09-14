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
                <div class="text-secondary small mt-3">
                    <i class="ti ti-clock me-1"></i>Último acceso:
                    {{ $usuario->ultimo_acceso_en?->format('d/m/Y H:i') ?? 'Sin registro' }}
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('perfil.2fa') }}" class="mt-3">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-shield-lock me-2 text-primary"></i>Doble factor de autenticación</h3>
                    @if ($usuario->dos_factores)
                        <div class="card-actions"><span class="badge bg-success-lt">Activo</span></div>
                    @endif
                </div>
                <div class="card-body">
                    <p class="text-secondary">
                        Al activarlo, en cada inicio de sesión te enviaremos un código de 6 dígitos a tu correo
                        que deberás ingresar para completar el acceso.
                    </p>

                    @if (! $usuario->hasVerifiedEmail())
                        <div class="alert alert-warning mb-3">
                            <div class="d-flex">
                                <div class="me-2"><i class="ti ti-alert-triangle fs-2"></i></div>
                                <div>
                                    Debes <a href="{{ route('verification.notice') }}" class="alert-link">verificar tu correo</a>
                                    antes de activar el doble factor.
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-check form-switch">
                            <input type="hidden" name="dos_factores" value="0">
                            <input type="checkbox" id="dos_factores" name="dos_factores" value="1" class="form-check-input"
                                   @checked(old('dos_factores', $usuario->dos_factores))
                                   @disabled(! $usuario->hasVerifiedEmail())>
                            <span class="form-check-label">Enviar un código a mi correo al iniciar sesión</span>
                        </label>
                        @error('dos_factores')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label class="form-label required" for="password_actual_2fa">Contraseña actual</label>
                        <input type="password" id="password_actual_2fa" name="password_actual" class="form-control"
                               autocomplete="current-password" required @disabled(! $usuario->hasVerifiedEmail())>
                        @error('password_actual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="form-hint">Confirma tu identidad para cambiar esta configuración.</small>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-outline-primary" @disabled(! $usuario->hasVerifiedEmail())>
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>

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
