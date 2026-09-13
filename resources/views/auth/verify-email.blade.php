@extends('layouts.auth')

@section('titulo', 'Verifica tu correo')

@section('contenido')
    <div class="text-center my-4">
        <span class="avatar avatar-lg bg-brand mb-3"><i class="ti ti-mail-check fs-1"></i></span>
        <h2 class="h1">Verifica tu correo</h2>
        <p class="text-secondary">
            Te enviamos un enlace de verificación. Si no lo recibiste, podemos reenviarlo.
        </p>
    </div>

    @if (session('status') === 'verification-link-sent')
        <div class="alert alert-success">Se envió un nuevo enlace de verificación a tu correo.</div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit" class="btn btn-brand w-100">Reenviar enlace</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-link w-100 text-secondary">Cerrar sesión</button>
    </form>
@endsection
