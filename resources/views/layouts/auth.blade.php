<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Acceso') · {{ $ajustes->nombre ?? 'OdontoSuite' }}</title>
    <meta name="theme-color" content="#0d9488">
    <meta name="application-name" content="{{ $ajustes->nombre ?? 'OdontoSuite' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('iconos/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column">
<div class="row g-0 flex-fill min-vh-100">
    {{-- Panel de marca --}}
    <div class="col-12 col-lg-6 col-xl-5 d-none d-lg-flex flex-column os-hero p-5">
        <a href="{{ route('publico.inicio') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
            <span class="avatar avatar-sm bg-brand"><i class="ti ti-dental"></i></span>
            <span class="fw-bold fs-2">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
        </a>

        <div class="my-auto" style="max-width: 26rem;">
            <span class="os-eyebrow"><i class="ti ti-point-filled"></i> Gestión integral para tu clínica</span>
            <h1 class="mt-4 text-white">Tu clínica, <span class="text-brand">en control total.</span></h1>
            <p class="fs-3 text-white-50">
                Pacientes, doctores, tratamientos y agenda en una sola plataforma.
                Segura, moderna y lista para tu crecimiento.
            </p>
            <ul class="list-unstyled text-white-50 mt-4">
                <li class="mb-2"><i class="ti ti-check text-brand me-2"></i>Historia clínica digital completa</li>
                <li class="mb-2"><i class="ti ti-check text-brand me-2"></i>Agenda con reservas en línea</li>
                <li class="mb-2"><i class="ti ti-check text-brand me-2"></i>Recordatorios automáticos</li>
            </ul>
        </div>

        <div class="d-flex justify-content-between text-white-50 small">
            <span>&copy; {{ date('Y') }} {{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
            <span>Hecho con Laravel</span>
        </div>
    </div>

    {{-- Formulario --}}
    <div class="col-12 col-lg-6 col-xl-7 d-flex flex-column justify-content-center p-4">
        <div class="container-tight py-4" style="max-width: 26rem;">
            @yield('contenido')
        </div>
    </div>
</div>
@stack('scripts')
</body>
</html>
