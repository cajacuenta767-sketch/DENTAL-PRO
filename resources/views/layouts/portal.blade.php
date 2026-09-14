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
    <title>@yield('titulo', 'Portal del paciente') · {{ $ajustes->nombre ?? 'OdontoSuite' }}</title>
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
@php
    $menuPortal = [
        ['ruta' => 'portal.inicio', 'patron' => 'portal.inicio', 'icono' => 'ti ti-home', 'etiqueta' => 'Inicio'],
        ['ruta' => 'portal.citas', 'patron' => 'portal.citas*', 'icono' => 'ti ti-calendar-event', 'etiqueta' => 'Mis citas'],
        ['ruta' => 'portal.documentos', 'patron' => 'portal.documentos*', 'icono' => 'ti ti-file-text', 'etiqueta' => 'Mis documentos'],
        ['ruta' => 'portal.presupuestos', 'patron' => 'portal.presupuestos*', 'icono' => 'ti ti-file-invoice', 'etiqueta' => 'Mis presupuestos'],
        ['ruta' => 'portal.pagos', 'patron' => 'portal.pagos*', 'icono' => 'ti ti-cash', 'etiqueta' => 'Mis pagos'],
    ];
@endphp
<a href="#contenido-principal" class="os-saltar">Saltar al contenido</a>
<div class="page">
    <header class="navbar navbar-expand-md d-print-none sticky-top">
        <div class="container-xl">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-portal"
                    aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>

            <a href="{{ route('portal.inicio') }}" class="navbar-brand navbar-brand-autodark d-flex align-items-center gap-2 me-3">
                @if ($ajustes?->logo)
                    <img src="{{ Storage::url($ajustes->logo) }}" alt="{{ $ajustes->nombre }}" height="32">
                @else
                    <span class="text-brand fs-2"><i class="ti ti-dental"></i></span>
                @endif
                <span class="fw-bold">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
                <span class="badge bg-blue-lt d-none d-sm-inline">Portal del paciente</span>
            </a>

            <div class="navbar-nav flex-row order-md-last align-items-center">
                <a href="#" class="nav-link px-2" data-os-theme-toggle title="Cambiar tema" aria-label="Cambiar tema claro u oscuro" role="button">
                    <i class="ti ti-moon fs-3" aria-hidden="true"></i>
                </a>

                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                        @if (auth()->user()->avatar)
                            <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                        @else
                            <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
                        @endif
                        <div class="d-none d-xl-block ps-2">
                            <div>{{ auth()->user()->nombre }}</div>
                            <div class="mt-1 small text-secondary">Paciente</div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <a href="{{ route('perfil.edit') }}" class="dropdown-item">
                            <i class="ti ti-user me-2"></i>Mi perfil
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="ti ti-logout me-2"></i>Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <nav class="collapse navbar-collapse" id="menu-portal" aria-label="Menú del portal">
                <ul class="navbar-nav">
                    @foreach ($menuPortal as $item)
                        <li class="nav-item {{ request()->routeIs($item['patron']) ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($item['ruta']) }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="{{ $item['icono'] }}"></i></span>
                                <span class="nav-link-title">{{ $item['etiqueta'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </header>

    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row g-2 align-items-center">
                    <div class="col">
                        @hasSection('pretitulo')
                            <div class="page-pretitle">@yield('pretitulo')</div>
                        @endif
                        <h2 class="page-title">@yield('titulo', 'Portal del paciente')</h2>
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

        <footer class="footer footer-transparent d-print-none">
            <div class="container-xl">
                <div class="row text-center align-items-center flex-row-reverse">
                    <div class="col-lg-auto ms-lg-auto">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item"><a href="{{ route('publico.inicio') }}" class="link-secondary">Sitio público</a></li>
                            @if ($ajustes?->telefono)
                                <li class="list-inline-item"><span class="text-secondary"><i class="ti ti-phone me-1"></i>{{ $ajustes->telefono }}</span></li>
                            @endif
                        </ul>
                    </div>
                    <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                        <ul class="list-inline list-inline-dots mb-0">
                            <li class="list-inline-item">
                                Copyright &copy; {{ date('Y') }}
                                <span class="link-secondary">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>.
                                Todos los derechos reservados.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
