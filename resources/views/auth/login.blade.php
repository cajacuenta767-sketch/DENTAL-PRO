@extends('layouts.auth')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <a href="{{ route('publico.inicio') }}" class="text-secondary text-decoration-none small">
        <i class="ti ti-arrow-left me-1"></i>Volver al inicio
    </a>

    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-lock fs-1"></i></span>
        <h2 class="h1">Bienvenido de nuevo</h2>
        <p class="text-secondary">Ingresa a tu cuenta para continuar</p>
    </div>

    @include('componentes.alertas')

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   placeholder="tu@email.com" autocomplete="email" autofocus required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-2">
            <label class="form-label d-flex justify-content-between" for="password">
                <span>Contraseña</span>
                <a href="{{ route('password.request') }}" class="text-brand small">¿Olvidaste tu contraseña?</a>
            </label>
            <div class="input-group input-group-flat">
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       placeholder="••••••••" autocomplete="current-password" required>
                <span class="input-group-text">
                    <a href="#" class="link-secondary" data-os-ver-clave title="Mostrar contraseña">
                        <i class="ti ti-eye"></i>
                    </a>
                </span>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-check">
                <input type="checkbox" name="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                <span class="form-check-label">Recordar mi sesión</span>
            </label>
        </div>

        <button type="submit" class="btn btn-brand w-100">Iniciar Sesión</button>
    </form>

    @if ($socialActivo)
        <div class="hr-text text-secondary my-4">o continúa con</div>

        <div class="d-grid gap-2">
            @if ($googleActivo)
                <a href="{{ route('social.redirect', 'google') }}" class="btn w-100">
                    <i class="ti ti-brand-google-filled text-red me-2"></i>Continuar con Google
                </a>
            @endif
            @if ($githubActivo)
                <a href="{{ route('social.redirect', 'github') }}" class="btn w-100">
                    <i class="ti ti-brand-github-filled me-2"></i>Continuar con GitHub
                </a>
            @endif
        </div>
    @endif

    <p class="text-center text-secondary mt-4 mb-0">
        ¿No tienes una cuenta? <a href="{{ route('register') }}" class="text-brand">Regístrate gratis</a>
    </p>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-os-ver-clave]').forEach((enlace) => {
                enlace.addEventListener('click', (e) => {
                    e.preventDefault();
                    const campo = enlace.closest('.input-group').querySelector('input');
                    campo.type = campo.type === 'password' ? 'text' : 'password';
                });
            });
        </script>
    @endpush
@endsection
