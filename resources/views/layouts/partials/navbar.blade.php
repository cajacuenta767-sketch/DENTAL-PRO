@php
    $modulos = collect(config('odontosuite.modulos'))
        ->reject(fn ($m) => ($m['oculto_en_menu'] ?? false) || blank($m['ruta'] ?? null));
@endphp

<header class="navbar navbar-expand-md d-print-none sticky-top">
    <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <a href="{{ route('admin.home') }}" class="navbar-brand navbar-brand-autodark d-flex align-items-center gap-2 me-3">
            @if ($ajustes?->logo)
                <img src="{{ Storage::url($ajustes->logo) }}" alt="{{ $ajustes->nombre }}" height="32">
            @else
                <span class="text-brand fs-2"><i class="ti ti-dental"></i></span>
            @endif
            <span class="fw-bold">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
        </a>

        <div class="navbar-nav flex-row order-md-last align-items-center">
            <a href="#" class="nav-link px-2" data-os-theme-toggle title="Cambiar tema">
                <i class="ti ti-moon fs-3"></i>
            </a>

            <div class="nav-item dropdown d-none d-md-flex me-2">
                <a href="#" class="nav-link px-2" data-bs-toggle="dropdown" title="Citas de hoy">
                    <i class="ti ti-bell fs-3"></i>
                    @if ($citasHoy > 0)
                        <span class="badge bg-red badge-notification badge-blink"></span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Citas de hoy</h3></div>
                        <div class="list-group list-group-flush list-group-hoverable">
                            @forelse ($agendaHoy as $cita)
                                <div class="list-group-item">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <span class="badge bg-{{ $cita->color_estado }}"></span>
                                        </div>
                                        <div class="col text-truncate">
                                            <span class="text-body d-block">{{ $cita->paciente?->nombre_completo }}</span>
                                            <small class="d-block text-secondary text-truncate">
                                                {{ substr($cita->hora, 0, 5) }} · {{ $cita->tratamiento?->nombre }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="list-group-item text-secondary">No hay citas programadas para hoy.</div>
                            @endforelse
                        </div>
                        @can('citas.ver')
                            <div class="card-footer text-center">
                                <a href="{{ route('admin.citas.index') }}" class="btn btn-sm btn-link">Ver todas las citas</a>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                    @if (auth()->user()->avatar)
                        <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                    @else
                        <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
                    @endif
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->nombre }}</div>
                        <div class="mt-1 small text-secondary">Rol: {{ auth()->user()->rol_principal }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <a href="{{ route('perfil.edit') }}" class="dropdown-item">
                        <i class="ti ti-user me-2"></i>Mi perfil
                    </a>
                    @can('ajustes.ver')
                        <a href="{{ route('admin.ajustes.edit') }}" class="dropdown-item">
                            <i class="ti ti-settings me-2"></i>Ajustes de la clínica
                        </a>
                    @endcan
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
    </div>
</header>

<header class="navbar-expand-md">
    <div class="collapse navbar-collapse" id="menu-principal">
        <div class="navbar">
            <div class="container-xl">
                <ul class="navbar-nav">
                    @foreach ($modulos as $clave => $modulo)
                        @can("{$clave}.ver")
                            @php
                                $patron = $modulo['ruta'] === 'admin.home'
                                    ? 'admin.home'
                                    : Str::beforeLast($modulo['ruta'], '.').'.*';
                            @endphp
                            <li class="nav-item {{ request()->routeIs($patron) ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route($modulo['ruta']) }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="{{ $modulo['icono'] }}"></i>
                                    </span>
                                    <span class="nav-link-title">{{ $modulo['etiqueta'] }}</span>
                                </a>
                            </li>
                        @endcan
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</header>
