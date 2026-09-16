@php
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
        'General' => 'ti ti-layout-dashboard', 'Clínica' => 'ti ti-stethoscope',
        'Catálogos' => 'ti ti-list-details', 'Finanzas' => 'ti ti-coins',
        'Operación' => 'ti ti-packages', 'Configuración' => 'ti ti-settings',
    ];
    $menuAgrupado = $modulos
        ->groupBy(fn ($m) => $m['grupo'] ?? 'General', preserveKeys: true)
        ->sortBy(function ($items, $grupo) use ($gruposMenu) {
            $posicion = array_search($grupo, array_keys($gruposMenu), true);
            return $posicion === false ? 99 : $posicion;
        });
@endphp

<aside class="os-sidebar d-print-none" id="menu-principal" aria-label="Navegación principal">
    <div class="os-sidebar-brand">
        <a href="{{ route('admin.home') }}" class="os-brand" aria-label="Ir al panel de control">
            <span class="os-brand-mark">
                @if ($ajustes?->logo)
                    <img src="{{ Storage::url($ajustes->logo) }}" alt="" width="36" height="36">
                @else
                    <i class="ti ti-dental" aria-hidden="true"></i>
                @endif
            </span>
            <span class="os-brand-copy">
                <strong>{{ $ajustes->nombre ?? 'OdontoSuite' }}</strong>
                <small>Gestión odontológica</small>
            </span>
        </a>
        <button class="os-icon-button os-sidebar-close d-lg-none" type="button" data-os-sidebar-close aria-label="Cerrar menú">
            <i class="ti ti-x" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="os-sidebar-nav">
        @if ($usuario?->esSuperAdministrador())
            <section class="os-nav-section">
                <div class="os-nav-heading"><i class="ti ti-crown"></i><span>Plataforma</span></div>
                <ul class="os-nav-list"><li>
                    <a class="os-nav-link {{ request()->routeIs('admin.activaciones.*') ? 'active' : '' }}" href="{{ route('admin.activaciones.index') }}">
                        <i class="ti ti-building-plus"></i><span>Clínicas y activaciones</span>
                    </a>
                </li></ul>
            </section>
        @endif
        @if ($usuario?->clinica_id && $usuario->hasAnyRole(['ADMINISTRADOR', 'SUPER ADMINISTRADOR']))
            <section class="os-nav-section">
                <div class="os-nav-heading"><i class="ti ti-user-share"></i><span>Equipo</span></div>
                <ul class="os-nav-list"><li>
                    <a class="os-nav-link {{ request()->routeIs('admin.invitaciones.*') ? 'active' : '' }}" href="{{ route('admin.invitaciones.index') }}">
                        <i class="ti ti-mail-forward"></i><span>Invitaciones</span>
                    </a>
                </li></ul>
            </section>
        @endif
        @foreach ($menuAgrupado as $grupo => $items)
            <section class="os-nav-section" aria-labelledby="os-grupo-{{ Str::slug($grupo) }}">
                <div class="os-nav-heading" id="os-grupo-{{ Str::slug($grupo) }}">
                    <i class="{{ $gruposMenu[$grupo] ?? 'ti ti-apps' }}" aria-hidden="true"></i>
                    <span>{{ $grupo }}</span>
                </div>
                <ul class="os-nav-list">
                    @foreach ($items as $clave => $modulo)
                        <li>
                            <a class="os-nav-link {{ $modulo['activo'] ? 'active' : '' }}" href="{{ route($modulo['ruta']) }}"
                               title="{{ $modulo['etiqueta'] }}" @if ($modulo['activo']) aria-current="page" @endif>
                                <i class="{{ $modulo['icono'] }}" aria-hidden="true"></i>
                                <span>{{ $modulo['etiqueta'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </nav>

    <div class="os-sidebar-footer">
        @if ($sucursalActiva)
            <div class="os-clinic-status" title="Sede activa: {{ $sucursalActiva->nombre }}">
                <span class="os-status-dot" style="--os-sede-color: {{ $sucursalActiva->color }}"></span>
                <span class="os-sidebar-label"><small>Sede activa</small><strong>{{ $sucursalActiva->nombre }}</strong></span>
            </div>
        @endif
        <button class="os-collapse-button d-none d-lg-flex" type="button" data-os-sidebar-collapse aria-label="Contraer menú" title="Contraer menú">
            <i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i><span>Contraer menú</span>
        </button>
    </div>
</aside>

<button class="os-sidebar-backdrop d-print-none" type="button" data-os-sidebar-close aria-label="Cerrar menú"></button>

<header class="os-topbar d-print-none">
    <div class="os-topbar-inner">
        <button class="os-icon-button d-lg-none" type="button" data-os-sidebar-open aria-label="Abrir menú" aria-controls="menu-principal" aria-expanded="false">
            <i class="ti ti-menu-2" aria-hidden="true"></i>
        </button>

        @canany(['pacientes.ver', 'citas.ver', 'doctores.ver', 'pagos.ver', 'presupuestos.ver'])
            <form action="{{ route('admin.buscar') }}" method="GET" class="os-search" autocomplete="off" role="search">
                <i class="ti ti-search" aria-hidden="true"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar pacientes, citas, pagos..."
                       data-os-buscador aria-label="Buscar en el sistema">
                <kbd class="d-none d-xl-inline-flex">Ctrl K</kbd>
                <div class="dropdown-menu w-100 mt-2 d-none" data-os-resultados></div>
            </form>
        @endcanany

        <div class="os-topbar-actions">
            @if (auth()->user()?->sucursal_id && $sucursalActiva)
                <span class="os-sede-pill d-none d-md-flex" title="Tu cuenta está ligada a esta sede">
                    <i class="ti ti-building-hospital" style="color: {{ $sucursalActiva->color }}" aria-hidden="true"></i>
                    <span>{{ $sucursalActiva->nombre }}</span>
                </span>
            @elseif (($sucursales ?? collect())->count() > 1 && \App\Support\SucursalActiva::puedeCambiar())
                <div class="dropdown">
                    <button class="os-sede-pill" type="button" data-bs-toggle="dropdown" aria-label="Cambiar de sede" aria-expanded="false">
                        <i class="ti ti-building-hospital" @if ($sucursalActiva) style="color: {{ $sucursalActiva->color }}" @endif aria-hidden="true"></i>
                        <span class="d-none d-md-inline">{{ $sucursalActiva?->nombre ?? 'Todas las sedes' }}</span>
                        <i class="ti ti-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <span class="dropdown-header">Sede de trabajo</span>
                        <form method="POST" action="{{ route('admin.sucursal.cambiar') }}">@csrf
                            <button type="submit" name="sucursal_id" value="" class="dropdown-item {{ $sucursalActiva ? '' : 'active' }}"><i class="ti ti-buildings me-2"></i>Todas las sedes</button>
                        </form>
                        @foreach ($sucursales as $sede)
                            <form method="POST" action="{{ route('admin.sucursal.cambiar') }}">@csrf
                                <button type="submit" name="sucursal_id" value="{{ $sede->id }}" class="dropdown-item {{ $sucursalActiva?->id === $sede->id ? 'active' : '' }}">
                                    <span class="badge me-2" style="background-color: {{ $sede->color }}"></span>{{ $sede->nombre }}
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endif

            <button class="os-icon-button" type="button" data-os-theme-toggle title="Cambiar tema" aria-label="Cambiar tema claro u oscuro"><i class="ti ti-moon" aria-hidden="true"></i></button>
            <div class="dropdown">
                <button class="os-icon-button position-relative" type="button" data-bs-toggle="dropdown" title="Citas de hoy"
                        aria-label="Citas de hoy{{ $citasHoy > 0 ? " ({$citasHoy})" : '' }}" aria-expanded="false">
                    <i class="ti ti-bell" aria-hidden="true"></i>
                    @if ($citasHoy > 0)<span class="os-notification-dot"></span>@endif
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-card os-notifications">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Citas de hoy</h3></div>
                        <div class="list-group list-group-flush list-group-hoverable">
                            @forelse ($agendaHoy as $cita)
                                <div class="list-group-item"><div class="row align-items-center">
                                    <div class="col-auto"><span class="badge bg-{{ $cita->color_estado }}"></span></div>
                                    <div class="col text-truncate"><span class="text-body d-block">{{ $cita->paciente?->nombre_completo }}</span>
                                        <small class="d-block text-secondary text-truncate">{{ substr($cita->hora, 0, 5) }} · {{ $cita->tratamiento?->nombre }}</small></div>
                                </div></div>
                            @empty
                                <div class="list-group-item text-secondary">No hay citas programadas para hoy.</div>
                            @endforelse
                        </div>
                        @can('citas.ver')<div class="card-footer text-center"><a href="{{ route('admin.citas.index') }}" class="btn btn-sm btn-link">Ver todas las citas</a></div>@endcan
                    </div>
                </div>
            </div>

            <div class="dropdown os-user-menu">
                <button class="os-user-trigger" type="button" data-bs-toggle="dropdown" aria-label="Menú de usuario" aria-expanded="false">
                    @if (auth()->user()->avatar)
                        <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                    @else
                        <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
                    @endif
                    <span class="os-user-copy d-none d-xl-block"><strong>{{ auth()->user()->nombre }}</strong><small>{{ auth()->user()->rol_principal }}</small></span>
                    <i class="ti ti-chevron-down d-none d-md-block" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <div class="dropdown-header">{{ auth()->user()->nombre }}</div>
                    <a href="{{ route('perfil.edit') }}" class="dropdown-item"><i class="ti ti-user me-2"></i>Mi perfil</a>
                    @can('ajustes.ver')<a href="{{ route('admin.ajustes.edit') }}" class="dropdown-item"><i class="ti ti-settings me-2"></i>Ajustes de la clínica</a>@endcan
                    @can('ajustes.reservas')<a href="{{ route('admin.reservas.edit') }}" class="dropdown-item"><i class="ti ti-qrcode me-2"></i>Turnos online</a>@endcan
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="ti ti-logout me-2"></i>Cerrar sesión</button>
                    </form>
                </div>
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
            const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            })[c]);
            function cerrar() { panel.classList.add('d-none'); panel.innerHTML = ''; }
            function pintar(grupos) {
                if (!grupos.length) {
                    panel.innerHTML = '<div class="dropdown-item-text text-secondary py-3">Sin coincidencias.</div>';
                    panel.classList.remove('d-none'); return;
                }
                panel.innerHTML = grupos.map((grupo) => `
                    <div class="dropdown-header">${escapar(grupo.titulo)}</div>
                    ${(grupo.items ?? []).map((item) => `
                        <a class="dropdown-item" href="${escapar(item.url)}">
                            <div class="text-truncate">${escapar(item.titulo)}</div>
                            <div class="small text-secondary text-truncate">${escapar(item.detalle)}</div>
                        </a>`).join('')}
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
                    } catch { cerrar(); }
                }, 250);
            });
            document.addEventListener('click', (e) => { if (!panel.contains(e.target) && e.target !== campo) cerrar(); });
            campo.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });
        })();
        </script>
    @endpush
@endonce
@endcanany
