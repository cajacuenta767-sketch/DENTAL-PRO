@extends('layouts.auth')

@section('titulo', 'Nueva contraseña')

@section('contenido')
    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-lock-check fs-1"></i></span>
        <h2 class="h1">Define tu nueva contraseña</h2>
    </div>

    @include('componentes.alertas')

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label class="form-label required" for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label required" for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   autocomplete="new-password" autofocus required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label required" for="password_confirmation">Confirmar contraseña</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-brand w-100">Guardar contraseña</button>
    </form>
@endsection
