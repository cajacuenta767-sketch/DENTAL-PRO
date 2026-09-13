@extends('layouts.publico')

@section('titulo', 'Tu cita está reservada')

@section('contenido')
<nav class="navbar py-3" style="background: #0b3b38;">
    <div class="container-xl">
        <span class="navbar-brand d-flex align-items-center gap-2 text-white mb-0">
            <span class="avatar avatar-sm bg-brand"><i class="ti ti-dental"></i></span>
            <span class="fw-bold fs-3">{{ $ajustes->nombre }}</span>
        </span>
    </div>
</nav>

<section class="py-6 bg-white">
    <div class="container-xl">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <span class="avatar avatar-xl bg-green-lt text-green mb-4"><i class="ti ti-circle-check fs-1"></i></span>
                <h1 class="h1">¡Tu cita quedó reservada!</h1>
                <p class="fs-3 text-secondary">
                    Guarda tu código y preséntalo en recepción el día de tu atención.
                </p>

                <div class="card my-4 text-start">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="text-secondary small text-uppercase">Código de cita</div>
                            <div class="h1 font-monospace text-brand mb-0">{{ $cita->token }}</div>
                        </div>

                        <div class="datagrid">
                            <div class="datagrid-item">
                                <div class="datagrid-title">Paciente</div>
                                <div class="datagrid-content">{{ $cita->paciente->nombre_completo }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Fecha y hora</div>
                                <div class="datagrid-content">{{ $cita->fecha_hora }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Profesional</div>
                                <div class="datagrid-content">{{ $cita->doctor->nombre_profesional }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Especialidad</div>
                                <div class="datagrid-content">{{ $cita->doctor->especialidad->nombre }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Motivo</div>
                                <div class="datagrid-content">{{ $cita->tratamiento->nombre }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Duración estimada</div>
                                <div class="datagrid-content">{{ $cita->tratamiento->duracion }} minutos</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-flex align-items-start gap-2 text-secondary small">
                            <i class="ti ti-info-circle fs-3"></i>
                            <div>
                                Tu reserva quedó <strong>pendiente de confirmación</strong>. Te contactaremos
                                para confirmarla. Si necesitas cambiarla o cancelarla, comunícate con la clínica
                                @if ($ajustes->telefono) al <strong>{{ $ajustes->telefono }}</strong> @endif
                                con la mayor anticipación posible.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-list justify-content-center">
                    <a href="{{ route('reservas.formulario', $token) }}" class="btn btn-brand">
                        <i class="ti ti-calendar-plus me-1"></i>Reservar otra cita
                    </a>
                    <button type="button" class="btn" onclick="window.print()">
                        <i class="ti ti-printer me-1"></i>Imprimir comprobante
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="py-4" style="background: #0b3b38; color: #cbd5e1;">
    <div class="container-xl small">
        &copy; {{ date('Y') }} {{ $ajustes->nombre }}
        @if ($ajustes->direccion) · {{ $ajustes->direccion }} @endif
    </div>
</footer>
@endsection
