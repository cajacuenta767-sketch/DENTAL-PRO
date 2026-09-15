@php
    $ordenGrupos = ['General', 'Clínica', 'Finanzas', 'Operación', 'Catálogos', 'Configuración'];

    $grupos = collect(config('odontosuite.modulos'))
        ->reject(fn ($m) => ($m['oculto_en_menu'] ?? false) || blank($m['ruta'] ?? null))
        ->filter(fn ($m, $clave) => auth()->user()->can("{$clave}.ver"))
        ->groupBy(fn ($m) => $m['grupo'] ?? 'General')
        ->sortBy(fn ($items, $grupo) => ($pos = array_search($grupo, $ordenGrupos)) === false ? 99 : $pos);

    $activo = fn (string $ruta): bool => $ruta === 'admin.home'
        ? request()->routeIs('admin.home')
        : request()->routeIs(Str::beforeLast($ruta, '.').'.*');
@endphp

<aside class="navbar navbar-vertical navbar-expand-lg os-sidebar d-print-none" data-bs-theme="dark">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
                aria-controls="sidebar-menu" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-brand os-sidebar-brand">
            <a href="{{ route('admin.home') }}" class="os-sidebar-brand-link" title="{{ $ajustes->nombre ?? 'OdontoSuite' }}">
                @if ($ajustes?->logo)
                    <img src="{{ Storage::url($ajustes->logo) }}" alt="{{ $ajustes->nombre }}" class="os-sidebar-logo">
                @else
                    <span class="os-sidebar-logo os-sidebar-logo-icon"><i class="ti ti-dental"></i></span>
                @endif
                <span class="os-sidebar-brand-text">
                    <span class="os-sidebar-brand-name">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
                    <span class="os-sidebar-brand-sub">Gestión odontológica</span>
                </span>
            </a>
            <button type="button" class="os-sidebar-fold d-none d-lg-inline-flex" data-bs-toggle="sidebar-folded"
                    title="Contraer menú" aria-label="Contraer o expandir el menú">
                <i class="ti ti-layout-sidebar-left-collapse"></i>
            </button>
        </div>

        {{-- Accesos rápidos en móvil (en escritorio viven en la barra superior) --}}
        <div class="navbar-nav flex-row d-lg-none align-items-center">
            <a href="#" class="nav-link px-2" data-os-theme-toggle title="Cambiar tema" aria-label="Cambiar tema">
                <i class="ti ti-moon fs-3"></i>
            </a>
            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                    @if (auth()->user()->avatar)
                        <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                    @else
                        <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    @include('layouts.partials.menu-usuario')
                </div>
            </div>
        </div>

        <div class="collapse navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav os-sidebar-nav">
                @foreach ($grupos as $grupo => $modulos)
                    <li class="nav-section-title">{{ $grupo }}</li>
                    @foreach ($modulos as $clave => $modulo)
                        <li class="nav-item {{ $activo($modulo['ruta']) ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route($modulo['ruta']) }}" title="{{ $modulo['etiqueta'] }}"
                               @if ($activo($modulo['ruta'])) aria-current="page" @endif>
                                <span class="nav-link-icon"><i class="{{ $modulo['icono'] }}"></i></span>
                                <span class="nav-link-title">{{ $modulo['etiqueta'] }}</span>
                            </a>
                        </li>
                        @if ($clave === 'ajustes')
                            @can('ajustes.reservas')
                                <li class="nav-item {{ request()->routeIs('admin.reservas.*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('admin.reservas.edit') }}" title="Turnos online">
                                        <span class="nav-link-icon"><i class="ti ti-qrcode"></i></span>
                                        <span class="nav-link-title">Turnos online</span>
                                    </a>
                                </li>
                            @endcan
                        @endif
                    @endforeach
                @endforeach
            </ul>

            <div class="navbar-footer d-none d-lg-block">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('publico.inicio') }}" target="_blank" rel="noopener" title="Sitio público">
                            <span class="nav-link-icon"><i class="ti ti-world"></i></span>
                            <span class="nav-link-title">Sitio público</span>
                            <i class="ti ti-external-link ms-auto os-sidebar-ext"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</aside>
