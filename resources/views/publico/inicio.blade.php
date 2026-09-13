@extends('layouts.publico')

@section('titulo', 'Gestiona tu clínica dental con un solo sistema')

@section('contenido')
    {{-- Barra superior --}}
    <nav class="navbar navbar-expand-md os-hero py-3" style="background: #0b3b38;">
        <div class="container-xl">
            <a href="{{ route('publico.inicio') }}" class="navbar-brand d-flex align-items-center gap-2 text-white">
                <span class="avatar avatar-sm bg-brand"><i class="ti ti-dental"></i></span>
                <span class="fw-bold fs-3">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-publico"
                    aria-label="Abrir menú">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu-publico">
                <ul class="navbar-nav ms-auto align-items-md-center gap-md-3">
                    <li class="nav-item"><a class="nav-link text-white-50" href="#modulos">Módulos</a></li>
                    <li class="nav-item"><a class="nav-link text-white-50" href="#como-funciona">Cómo funciona</a></li>
                    <li class="nav-item"><a class="nav-link text-white-50" href="#beneficios">Beneficios</a></li>
                    <li class="nav-item mt-2 mt-md-0">
                        <a class="btn btn-white" href="{{ route('login') }}">Iniciar sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    {{-- Hero --}}
    <section class="os-hero py-6">
        <div class="container-xl">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="os-eyebrow"><i class="ti ti-point-filled"></i> Sistema líder para clínicas odontológicas</span>
                    <h1 class="mt-4 text-white">
                        Gestiona tu clínica dental <span class="text-brand">con un solo sistema.</span>
                    </h1>
                    <p class="mt-3 fs-3 text-white-50" style="max-width: 34rem;">
                        {{ $ajustes->nombre ?? 'OdontoSuite' }} centraliza pacientes, doctores, tratamientos y agenda
                        en una plataforma moderna, segura y fácil de usar, pensada para crecer contigo.
                    </p>
                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <a href="{{ route('login') }}" class="btn btn-brand btn-lg">Comenzar ahora <i class="ti ti-arrow-right ms-1"></i></a>
                        <a href="#modulos" class="btn btn-outline-light btn-lg">Ver módulos</a>
                    </div>

                    <div class="row g-4 mt-4 text-white">
                        <div class="col-4">
                            <div class="h1 mb-0">+{{ $totalModulos }}</div>
                            <div class="text-white-50 small">Módulos integrados</div>
                        </div>
                        <div class="col-4">
                            <div class="h1 mb-0">100%</div>
                            <div class="text-white-50 small">Agenda en la nube</div>
                        </div>
                        <div class="col-4">
                            <div class="h1 mb-0">24/7</div>
                            <div class="text-white-50 small">Reservas en línea</div>
                        </div>
                    </div>
                </div>

                {{-- Maqueta de agenda --}}
                <div class="col-lg-6">
                    <div class="os-mockup p-3 position-relative">
                        <div class="d-flex gap-1 mb-3">
                            <span class="badge bg-red rounded-circle p-1"></span>
                            <span class="badge bg-yellow rounded-circle p-1"></span>
                            <span class="badge bg-green rounded-circle p-1"></span>
                            <span class="ms-auto text-white-50 small">Agenda del día</span>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach ($agendaDemo as $fila)
                                <div class="list-group-item bg-transparent border-0 px-2 py-2 d-flex align-items-center gap-3">
                                    <div class="text-white fw-medium flex-fill">{{ $fila['paciente'] }}</div>
                                    <div class="text-white-50 small d-none d-sm-block">{{ $fila['hora'] }} · {{ $fila['tratamiento'] }}</div>
                                    <span class="badge bg-{{ $fila['color'] }}-lt">{{ $fila['estado'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="card position-absolute shadow" style="bottom: -1.5rem; left: 1rem;">
                            <div class="card-body py-2 px-3 d-flex align-items-center gap-2">
                                <i class="ti ti-circle-check text-success fs-2"></i>
                                <div>
                                    <div class="fw-medium small">Cita confirmada</div>
                                    <div class="text-secondary" style="font-size: .75rem;">viaja en 3 minutos</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Módulos --}}
    <section id="modulos" class="py-6 bg-white">
        <div class="container-xl">
            <div class="text-center mb-5">
                <div class="text-uppercase text-brand fw-bold small tracking-wide">Módulos</div>
                <h2 class="h1 mt-2">Todo lo que tu clínica necesita, en un mismo lugar</h2>
                <p class="text-secondary fs-3 mx-auto" style="max-width: 46rem;">
                    Cada módulo trabaja conectado con los demás: la cita alimenta la historia clínica,
                    la historia alimenta el odontograma y el tratamiento alimenta la caja.
                </p>
            </div>

            <div class="row g-3">
                @foreach ($modulosPublicos as $modulo)
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="card os-module-card">
                            <div class="card-body">
                                <div class="os-module-icon mb-3"><i class="{{ $modulo['icono'] }}"></i></div>
                                <h3 class="card-title mb-1">{{ $modulo['etiqueta'] }}</h3>
                                <p class="text-secondary mb-0">{{ $modulo['texto'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Cómo funciona --}}
    <section id="como-funciona" class="py-6 bg-light-subtle" style="background-color: #f6faf9;">
        <div class="container-xl">
            <div class="text-center mb-5">
                <div class="text-uppercase text-brand fw-bold small">Cómo funciona</div>
                <h2 class="h1 mt-2">De la reserva al cobro en cuatro pasos</h2>
            </div>
            <div class="row g-4">
                @foreach ($pasos as $i => $paso)
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <span class="badge bg-brand mb-3">Paso {{ $i + 1 }}</span>
                                <h3 class="card-title">{{ $paso['titulo'] }}</h3>
                                <p class="text-secondary mb-0">{{ $paso['texto'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Beneficios --}}
    <section id="beneficios" class="py-6 bg-white">
        <div class="container-xl">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <div class="text-uppercase text-brand fw-bold small">Beneficios</div>
                    <h2 class="h1 mt-2">Menos planillas, más pacientes atendidos</h2>
                    <p class="text-secondary fs-3">
                        Roles y permisos granulares, historia clínica digital, odontograma interactivo
                        y reportes financieros listos para exportar en PDF.
                    </p>
                    <a href="{{ route('login') }}" class="btn btn-brand btn-lg mt-2">Entrar al sistema</a>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        @foreach ($beneficios as $beneficio)
                            <div class="col-md-6">
                                <div class="card h-100">
                                    <div class="card-body d-flex gap-3">
                                        <i class="{{ $beneficio['icono'] }} fs-1 text-brand"></i>
                                        <div>
                                            <h3 class="card-title mb-1">{{ $beneficio['titulo'] }}</h3>
                                            <p class="text-secondary mb-0">{{ $beneficio['texto'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Pie --}}
    <footer class="py-5" style="background: #0b3b38; color: #cbd5e1;">
        <div class="container-xl">
            <div class="row g-4 align-items-center">
                <div class="col-md">
                    <div class="d-flex align-items-center gap-2 text-white">
                        <span class="avatar avatar-sm bg-brand"><i class="ti ti-dental"></i></span>
                        <span class="fw-bold fs-3">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>
                    </div>
                    @if ($ajustes?->direccion)
                        <div class="mt-2 small">{{ $ajustes->direccion }}</div>
                    @endif
                    @if ($ajustes?->telefono)
                        <div class="small"><i class="ti ti-phone me-1"></i>{{ $ajustes->telefono }}</div>
                    @endif
                </div>
                <div class="col-md-auto small">
                    &copy; {{ date('Y') }} {{ $ajustes->nombre ?? 'OdontoSuite' }} · Hecho con Laravel
                </div>
            </div>
        </div>
    </footer>
@endsection
