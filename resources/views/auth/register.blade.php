@extends('layouts.auth')

@section('titulo', 'Crear cuenta')

@section('contenido')
    <a href="{{ route('publico.inicio') }}" class="text-secondary text-decoration-none small">
        <i class="ti ti-arrow-left me-1"></i>Volver al inicio
    </a>

    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-user-plus fs-1"></i></span>
        <h2 class="h1">Crea tu cuenta</h2>
        <p class="text-secondary">Regístrate para reservar y consultar tus citas</p>
    </div>

    @include('componentes.alertas')

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label required" for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" value="{{ old('nombre') }}"
                   class="form-control @error('nombre') is-invalid @enderror" autofocus required>
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label required" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" autocomplete="email" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" value="{{ old('telefono') }}"
                   class="form-control @error('telefono') is-invalid @enderror">
            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="codigo">Código de invitación o activación <span class="text-secondary">(opcional)</span></label>
            <input type="text" id="codigo" name="codigo" value="{{ old('codigo', $codigo ?? '') }}"
                   class="form-control text-uppercase @error('codigo') is-invalid @enderror"
                   placeholder="Ej. ADMIN-XXXX-XXXX" autocomplete="off">
            <div class="form-hint">Si no tienes código, crearás una cuenta normal de paciente.</div>
            @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="clinica_nombre">Nombre de la clínica</label>
            <input type="text" id="clinica_nombre" name="clinica_nombre" value="{{ old('clinica_nombre') }}"
                   class="form-control @error('clinica_nombre') is-invalid @enderror"
                   placeholder="Solo si estás activando una clínica">
            @error('clinica_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label required" for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="new-password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label required" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-brand w-100">Crear cuenta</button>
    </form>

    <p class="text-center text-secondary mt-4 mb-0">
        ¿Ya tienes una cuenta? <a href="{{ route('login') }}" class="text-brand">Inicia sesión</a>
    </p>
@endsection
