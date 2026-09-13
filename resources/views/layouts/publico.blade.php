<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Gestiona tu clínica dental con un solo sistema') · {{ $ajustes->nombre ?? 'OdontoSuite' }}</title>
    <meta name="description" content="{{ $ajustes->descripcion ?? 'OdontoSuite centraliza pacientes, doctores, tratamientos y agenda en una plataforma moderna, segura y fácil de usar.' }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
@yield('contenido')
@stack('scripts')
</body>
</html>
