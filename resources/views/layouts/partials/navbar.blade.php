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

        {{-- Búsqueda global --}}
        <div class="flex-fill d-none d-lg-block px-3" style="max-width: 28rem;">
            <form action="{{ route('admin.buscar') }}" method="GET" class="position-relative" autocomplete="off">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control"
                           placeholder="Barra de búsqueda..." data-os-buscador
                           aria-label="Buscar en el sistema">
                </div>
                <div class="dropdown-menu w-100 mt-1 d-none" data-os-resultados style="max-height: 24rem; overflow-y: auto;"></div>
            </form>
        </div>

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
                    @can('ajustes.reservas')
                        <a href="{{ route('admin.reservas.edit') }}" class="dropdown-item">
                            <i class="ti ti-qrcode me-2"></i>Turnos online
                        </a>
                    @endcan
                    @if (config('licencia.activa') && blank(config('licencia.clave_privada')))
                        <a href="{{ route('licencia.ver') }}" class="dropdown-item">
                            <i class="ti ti-key me-2"></i>Licencia
                        </a>
                    @endif
                    @if (filled(config('licencia.clave_privada')) && auth()->user()->hasRole('SUPER ADMINISTRADOR'))
                        <a href="{{ route('admin.licencias.index') }}" class="dropdown-item">
                            <i class="ti ti-key me-2"></i>Licencias emitidas
                        </a>
                    @endif
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


@once
    @push('scripts')
        <script>
        (() => {
            const campo = document.querySelector('[data-os-buscador]');
            const panel = document.querySelector('[data-os-resultados]');
            if (!campo || !panel) return;

            const endpoint = @json(route('admin.buscar.sugerencias'));
            let temporizador;

            function cerrar() {
                panel.classList.add('d-none');
                panel.innerHTML = '';
            }

            function pintar(grupos) {
                if (!grupos.length) {
                    panel.innerHTML = '<div class="dropdown-item-text text-secondary py-3">Sin coincidencias.</div>';
                    panel.classList.remove('d-none');
                    return;
                }

                panel.innerHTML = grupos.map((grupo) => `
                    <div class="dropdown-header"><i class="${grupo.icono} me-1"></i>${grupo.titulo}</div>
                    ${grupo.items.map((item) => `
                        <a class="dropdown-item" href="${item.url}">
                            <div class="text-truncate">${item.titulo}</div>
                            <div class="small text-secondary text-truncate">${item.detalle}</div>
                        </a>
                    `).join('')}
                `).join('<div class="dropdown-divider"></div>');

                panel.classList.remove('d-none');
            }

            campo.addEventListener('input', () => {
                clearTimeout(temporizador);
                const termino = campo.value.trim();

                if (termino.length < 2) { cerrar(); return; }

                temporizador = setTimeout(async () => {
                    try {
                        const url = new URL(endpoint, window.location.origin);
                        url.searchParams.set('q', termino);
                        const respuesta = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!respuesta.ok) throw new Error('sin respuesta');
                        pintar((await respuesta.json()).grupos ?? []);
                    } catch {
                        cerrar();
                    }
                }, 250);
            });

            document.addEventListener('click', (e) => {
                if (!panel.contains(e.target) && e.target !== campo) cerrar();
            });

            campo.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') cerrar();
            });
        })();
        </script>
    @endpush
@endonce
