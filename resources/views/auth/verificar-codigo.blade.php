@extends('layouts.auth')

@section('titulo', 'Verificar código')

@section('contenido')
    <a href="{{ route('login') }}" class="text-secondary text-decoration-none small">
        <i class="ti ti-arrow-left me-1"></i>Volver
    </a>

    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-shield-lock fs-1"></i></span>
        <h2 class="h1">Verifica tu identidad</h2>
        <p class="text-secondary">
            Te enviamos un código de 6 dígitos a tu correo. Ingrésalo para completar el inicio de sesión.
        </p>
    </div>

    @include('componentes.alertas')

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login.confirmar') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="codigo">Código de verificación</label>
            <input type="text" id="codigo" name="codigo"
                   class="form-control form-control-lg text-center fs-1 @error('codigo') is-invalid @enderror"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6"
                   autocomplete="one-time-code" placeholder="••••••" autofocus required
                   style="letter-spacing: .5em;">
            @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <small class="form-hint">El código caduca en pocos minutos. Revisa también tu carpeta de spam.</small>
        </div>

        <button type="submit" class="btn btn-brand w-100">
            <i class="ti ti-check me-1"></i>Confirmar código
        </button>
    </form>

    <form method="POST" action="{{ route('login.reenviar') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-secondary">
            <i class="ti ti-refresh me-1"></i>Reenviar código
        </button>
    </form>
@endsection
