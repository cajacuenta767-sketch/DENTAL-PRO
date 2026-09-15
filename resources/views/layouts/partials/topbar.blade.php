<header class="navbar navbar-expand-md d-none d-lg-flex d-print-none os-topbar">
    <div class="container-xl">
        {{-- Búsqueda global --}}
        <div class="flex-fill" style="max-width: 30rem;">
            <form action="{{ route('admin.buscar') }}" method="GET" class="position-relative" autocomplete="off">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control os-topbar-search"
                           placeholder="Buscar pacientes, citas, recibos…" data-os-buscador
                           aria-label="Buscar en el sistema">
                    <span class="input-icon-addon d-none d-xl-flex"><kbd class="os-kbd">/</kbd></span>
                </div>
                <div class="dropdown-menu w-100 mt-1 d-none" data-os-resultados style="max-height: 24rem; overflow-y: auto;"></div>
            </form>
        </div>

        <div class="navbar-nav flex-row order-md-last align-items-center ms-auto gap-1">
            @can('citas.crear')
                <a href="{{ route('admin.citas.create') }}" class="btn btn-brand btn-sm d-none d-xl-inline-flex me-2">
                    <i class="ti ti-plus me-1"></i>Nueva cita
                </a>
            @endcan

            <a href="#" class="nav-link px-2 os-topbar-icon" data-os-theme-toggle title="Cambiar tema" aria-label="Cambiar tema">
                <i class="ti ti-moon fs-3"></i>
            </a>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link px-2 os-topbar-icon" data-bs-toggle="dropdown" title="Citas de hoy" aria-label="Citas de hoy">
                    <i class="ti ti-bell fs-3"></i>
                    @if ($citasHoy > 0)
                        <span class="badge bg-red badge-notification badge-blink"></span>
                    @endif
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-card">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Citas de hoy</h3>
                            @if ($citasHoy > 0)
                                <span class="badge bg-brand ms-auto">{{ $citasHoy }}</span>
                            @endif
                        </div>
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

            <div class="nav-item dropdown ms-1">
                <a href="#" class="nav-link d-flex lh-1 p-0 ps-2" data-bs-toggle="dropdown" aria-label="Menú de usuario">
                    @if (auth()->user()->avatar)
                        <span class="avatar avatar-sm" style="background-image: url({{ auth()->user()->avatar }})"></span>
                    @else
                        <span class="avatar avatar-sm bg-brand">{{ auth()->user()->iniciales }}</span>
                    @endif
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->nombre }}</div>
                        <div class="mt-1 small text-secondary">{{ auth()->user()->rol_principal }}</div>
                    </div>
                    <i class="ti ti-chevron-down ms-2 text-secondary d-none d-xl-block"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    @include('layouts.partials.menu-usuario')
                </div>
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

            // Atajo: "/" enfoca el buscador desde cualquier pantalla.
            document.addEventListener('keydown', (e) => {
                const enCampo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)
                    || document.activeElement?.isContentEditable;
                if (e.key === '/' && !enCampo) {
                    e.preventDefault();
                    campo.focus();
                }
            });
        })();
        </script>
    @endpush
@endonce
