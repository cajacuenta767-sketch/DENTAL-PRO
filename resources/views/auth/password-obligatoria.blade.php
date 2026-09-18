@extends('layouts.auth')

@section('titulo', 'Define tu contraseña')

@section('contenido')
    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-key fs-1"></i></span>
        <h2 class="h1">Define una contraseña nueva</h2>
        <p class="text-secondary">
            La contraseña con la que ingresaste es temporal. Por seguridad, debes definir una propia
            antes de continuar.
        </p>
    </div>

    @include('componentes.alertas')

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.obligatoria.guardar') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label class="form-label" for="password_actual">Contraseña temporal (actual)</label>
            <input type="password" id="password_actual" name="password_actual"
                   class="form-control @error('password_actual') is-invalid @enderror"
                   placeholder="••••••••" autocomplete="current-password" autofocus required>
            @error('password_actual')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Nueva contraseña</label>
            <div class="input-group input-group-flat">
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       placeholder="••••••••" autocomplete="new-password" minlength="8" required>
                <span class="input-group-text">
                    <a href="#" class="link-secondary" data-os-ver-clave title="Mostrar contraseña">
                        <i class="ti ti-eye"></i>
                    </a>
                </span>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <small class="form-hint">Mínimo 8 caracteres, con letras y números. Debe ser distinta a la temporal.</small>
        </div>

        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" placeholder="••••••••" autocomplete="new-password" minlength="8" required>
        </div>

        <button type="submit" class="btn btn-brand w-100">
            <i class="ti ti-device-floppy me-1"></i>Guardar contraseña
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-secondary">
            <i class="ti ti-logout me-1"></i>Cerrar sesión
        </button>
    </form>

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
