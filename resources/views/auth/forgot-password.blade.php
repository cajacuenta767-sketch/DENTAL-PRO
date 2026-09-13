@extends('layouts.auth')

@section('titulo', 'Recuperar contraseña')

@section('contenido')
    <a href="{{ route('login') }}" class="text-secondary text-decoration-none small">
        <i class="ti ti-arrow-left me-1"></i>Volver a iniciar sesión
    </a>

    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-key fs-1"></i></span>
        <h2 class="h1">¿Olvidaste tu contraseña?</h2>
        <p class="text-secondary">Te enviamos un enlace para crear una nueva.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @include('componentes.alertas')

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label required" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" autofocus required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-brand w-100">Enviar enlace de recuperación</button>
    </form>
@endsection
