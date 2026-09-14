<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d9488">
    <meta name="application-name" content="{{ $ajustes->nombre ?? 'OdontoSuite' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title>@yield('titulo', 'Panel') · {{ $ajustes->nombre ?? 'OdontoSuite' }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Restaura el tema guardado antes de pintar para evitar parpadeo.
        try {
            const t = localStorage.getItem('odontosuite-tema');
            if (t) document.documentElement.setAttribute('data-bs-theme', t);
        } catch (e) {}
    </script>
    @stack('head')
</head>
<body class="layout-fluid">
<a href="#contenido-principal" class="os-saltar">Saltar al contenido</a>
<div class="page">
    @include('layouts.partials.navbar')

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        @hasSection('pretitulo')
                            <div class="page-pretitle">@yield('pretitulo')</div>
                        @endif
                        <h2 class="page-title">
                            @yield('titulo', 'Panel de Control')
                            @hasSection('subtitulo')
                                <span class="text-secondary fw-normal fs-5 ms-2">@yield('subtitulo')</span>
                            @endif
                        </h2>
                    </div>
                    <div class="col-auto ms-auto d-print-none">
                        @yield('acciones')
                    </div>
                </div>
            </div>
        </div>

        <main class="page-body" id="contenido-principal" tabindex="-1">
            <div class="container-xl">
                @include('componentes.alertas')
                @yield('contenido')
            </div>
        </main>

        @include('layouts.partials.footer')
    </div>
</div>
@stack('scripts')
</body>
</html>
