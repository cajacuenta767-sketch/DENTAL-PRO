@php
    // Módulos visibles para este usuario, agrupados según config('odontosuite.modulos')[*]['grupo'].
    $usuario = auth()->user();

    $modulos = collect(config('odontosuite.modulos'))
        ->reject(fn ($m) => ($m['oculto_en_menu'] ?? false) || blank($m['ruta'] ?? null))
        ->filter(fn ($m, $clave) => $usuario?->can("{$clave}.ver"))
        ->map(function ($m) {
            $m['patron'] = $m['ruta'] === 'admin.home' ? 'admin.home' : Str::beforeLast($m['ruta'], '.').'.*';
            $m['activo'] = request()->routeIs($m['patron']);

            return $m;
        });

    $gruposMenu = [
        'General' => 'ti ti-layout-dashboard',
        'Clínica' => 'ti ti-stethoscope',
        'Catálogos' => 'ti ti-list-details',
        'Finanzas' => 'ti ti-coins',
        'Operación' => 'ti ti-packages',
        'Configuración' => 'ti ti-settings',
    ];

    $menuAgrupado = $modulos
        ->groupBy(fn ($m) => $m['grupo'] ?? 'General', preserveKeys: true)
        ->sortBy(function ($items, $grupo) use ($gruposMenu) {
            $posicion = array_search($grupo, array_keys($gruposMenu), true);

            return $posicion === false ? 99 : $posicion;
        });
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
        @canany(['pacientes.ver', 'citas.ver', 'doctores.ver', 'pagos.ver', 'presupuestos.ver'])
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
        @endcanany

        <div class="navbar-nav flex-row order-md-last align-items-center">
            {{-- Selector de sede (sucursal activa) --}}
            @if (auth()->user()?->sucursal_id && $sucursalActiva)
                <span class="nav-link px-2 text-secondary d-none d-md-flex align-items-center" title="Tu cuenta está ligada a esta sede">
                    <i class="ti ti-building-hospital fs-3 me-1" style="color: {{ $sucursalActiva->color }}"></i>
                    <span class="d-none d-lg-inline small">{{ $sucursalActiva->nombre }}</span>
                </span>
            @elseif (($sucursales ?? collect())->count() > 1 && \App\Support\SucursalActiva::puedeCambiar())
                <div class="nav-item dropdown me-1">
                    <a href="#" class="nav-link px-2 d-flex align-items-center" data-bs-toggle="dropdown" title="Cambiar de sede" aria-label="Cambiar de sede">
                        <i class="ti ti-building-hospital fs-3 me-1" @if ($sucursalActiva) style="color: {{ $sucursalActiva->color }}" @endif></i>
                        <span class="d-none d-lg-inline small">{{ $sucursalActiva?->nombre ?? 'Todas las sedes' }}</span>
                        <i class="ti ti-chevron-down ms-1 d-none d-lg-inline"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <span class="dropdown-header">Sede de trabajo</span>
                        <form method="POST" action="{{ route('admin.sucursal.cambiar') }}">
                            @csrf
                            <button type="submit" name="sucursal_id" value="" class="dropdown-item {{ $sucursalActiva ? '' : 'active' }}">
                                <i class="ti ti-buildings me-2"></i>Todas las sedes
                            </button>
                        </form>
                        @foreach ($sucursales as $sede)
                            <form method="POST" action="{{ route('admin.sucursal.cambiar') }}">
                                @csrf
                                <button type="submit" name="sucursal_id" value="{{ $sede->id }}"
                                        class="dropdown-item {{ $sucursalActiva?->id === $sede->id ? 'active' : '' }}">
                                    <span class="badge me-2" style="background-color: {{ $sede->color }}"></span>{{ $sede->nombre }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <a href="#" class="nav-link px-2" data-os-theme-toggle title="Cambiar tema" aria-label="Cambiar tema claro u oscuro" role="button">
                <i class="ti ti-moon fs-3" aria-hidden="true"></i>
            </a>

            <div class="nav-item dropdown d-none d-md-flex me-2">
                <a href="#" class="nav-link px-2" data-bs-toggle="dropdown" title="Citas de hoy" role="button"
                   aria-label="Citas de hoy{{ $citasHoy > 0 ? " ({$citasHoy})" : '' }}" aria-expanded="false">
                    <i class="ti ti-bell fs-3" aria-hidden="true"></i>
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
                <nav aria-label="Módulos del sistema">
                <ul class="navbar-nav">
                    @foreach ($menuAgrupado as $grupo => $items)
                        @php
                            $grupoActivo = $items->contains('activo', true);
                            $idGrupo = 'menu-grupo-'.Str::slug($grupo);
                        @endphp

                        @if ($grupo === 'General' || $items->count() === 1)
                            {{-- Home (y grupos de un solo módulo) como enlace directo --}}
                            @foreach ($items as $clave => $modulo)
                                @can("{$clave}.ver")
                                    <li class="nav-item {{ $modulo['activo'] ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route($modulo['ruta']) }}" @if ($modulo['activo']) aria-current="page" @endif>
                                            <span class="nav-link-icon d-md-none d-lg-inline-block">
                                                <i class="{{ $modulo['icono'] }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="nav-link-title">{{ $modulo['etiqueta'] }}</span>
                                        </a>
                                    </li>
                                @endcan
                            @endforeach
                        @else
                            <li class="nav-item dropdown {{ $grupoActivo ? 'active' : '' }}">
                                <a class="nav-link dropdown-toggle" href="#{{ $idGrupo }}" data-bs-toggle="dropdown"
                                   data-bs-auto-close="outside" role="button" aria-expanded="false"
                                   aria-label="Abrir menú de {{ $grupo }}">
                                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                                        <i class="{{ $gruposMenu[$grupo] ?? 'ti ti-apps' }}" aria-hidden="true"></i>
                                    </span>
                                    <span class="nav-link-title">{{ $grupo }}</span>
                                </a>
                                <div class="dropdown-menu" id="{{ $idGrupo }}">
                                    <div class="dropdown-menu-columns">
                                        <div class="dropdown-menu-column">
                                            @foreach ($items as $clave => $modulo)
                                                @can("{$clave}.ver")
                                                    <a class="dropdown-item {{ $modulo['activo'] ? 'active' : '' }}"
                                                       href="{{ route($modulo['ruta']) }}" @if ($modulo['activo']) aria-current="page" @endif>
                                                        <i class="{{ $modulo['icono'] }} me-2" aria-hidden="true"></i>{{ $modulo['etiqueta'] }}
                                                    </a>
                                                @endcan
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ul>
                </nav>
            </div>
        </div>
    </div>
</header>


@canany(['pacientes.ver', 'citas.ver', 'doctores.ver', 'pagos.ver', 'presupuestos.ver'])
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
@endcanany
