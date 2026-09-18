@extends('layouts.publico')

@section('titulo', $resultado === 'confirmada' ? 'Cita confirmada' : 'Confirmación de cita')

@push('head')
<style>
    .confirmacion-encabezado { background: #0b3b38; }
    .confirmacion-pie { background: #0b3b38; color: #cbd5e1; }
    .confirmacion-contacto a { color: inherit; text-decoration: none; }
    .confirmacion-contacto a:hover { text-decoration: underline; }
</style>
@endpush

@section('contenido')
@php
    $mensajes = [
        'confirmada' => ['icono' => 'ti ti-circle-check', 'color' => 'green', 'titulo' => '¡Gracias, tu cita quedó confirmada!', 'texto' => 'Te esperamos en la clínica. Si no puedes asistir, avísanos con anticipación para ofrecer el cupo a otro paciente.'],
        'cancelada' => ['icono' => 'ti ti-calendar-off', 'color' => 'red', 'titulo' => 'Esta cita fue cancelada', 'texto' => 'Ya no es posible confirmarla. Si deseas reprogramarla, comunícate con la clínica.'],
        'atendida' => ['icono' => 'ti ti-clipboard-check', 'color' => 'azure', 'titulo' => 'Esta cita ya fue atendida', 'texto' => 'No hace falta confirmarla. Gracias por tu visita.'],
        'pasada' => ['icono' => 'ti ti-clock-exclamation', 'color' => 'orange', 'titulo' => 'La fecha de esta cita ya pasó', 'texto' => 'No es posible confirmarla. Si necesitas una nueva cita, comunícate con la clínica.'],
    ];
    $mensaje = $mensajes[$resultado];
@endphp

<nav class="navbar py-3 confirmacion-encabezado">
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
                <span class="avatar avatar-xl bg-{{ $mensaje['color'] }}-lt text-{{ $mensaje['color'] }} mb-4"><i class="{{ $mensaje['icono'] }} fs-1"></i></span>
                <h1 class="h1">{{ $mensaje['titulo'] }}</h1>
                <p class="fs-3 text-secondary">{{ $mensaje['texto'] }}</p>

                <div class="card my-4 text-start">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="text-secondary small text-uppercase">Código de cita</div>
                            <div class="h1 font-monospace text-brand mb-0">{{ $cita->token }}</div>
                            @if ($resultado === 'confirmada' && $cita->confirmada_en)
                                <span class="badge bg-green-lt mt-2">Confirmada el {{ $cita->confirmada_en->format('d/m/Y H:i') }}</span>
                            @endif
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
                                <div class="datagrid-content">{{ $cita->doctor->especialidad?->nombre ?? '—' }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Tratamiento</div>
                                <div class="datagrid-content">{{ $cita->tratamiento->nombre }}</div>
                            </div>
                            <div class="datagrid-item">
                                <div class="datagrid-title">Duración estimada</div>
                                <div class="datagrid-content">{{ $cita->duracion_minutos }} minutos</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer confirmacion-contacto">
                        <div class="text-secondary small text-uppercase mb-2">Contacto de la clínica</div>
                        <div class="d-flex flex-wrap gap-3">
                            @if ($ajustes->telefono)
                                <a href="tel:{{ preg_replace('/\s+/', '', $ajustes->telefono) }}"><i class="ti ti-phone me-1"></i>{{ $ajustes->telefono }}</a>
                            @endif
                            @if ($ajustes->whatsapp)
                                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $ajustes->whatsapp) }}" target="_blank" rel="noopener"><i class="ti ti-brand-whatsapp me-1"></i>{{ $ajustes->whatsapp }}</a>
                            @endif
                            @if ($ajustes->email)
                                <a href="mailto:{{ $ajustes->email }}"><i class="ti ti-mail me-1"></i>{{ $ajustes->email }}</a>
                            @endif
                            @if ($ajustes->direccion)
                                <span><i class="ti ti-map-pin me-1"></i>{{ $ajustes->direccion }}</span>
                            @endif
                            @if (! $ajustes->telefono && ! $ajustes->whatsapp && ! $ajustes->email && ! $ajustes->direccion)
                                <span class="text-secondary">Consulta los datos de contacto en tu correo de confirmación.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <button type="button" class="btn" onclick="window.print()">
                    <i class="ti ti-printer me-1"></i>Imprimir comprobante
                </button>
            </div>
        </div>
    </div>
</section>

<footer class="py-4 confirmacion-pie">
    <div class="container-xl small">
        &copy; {{ date('Y') }} {{ $ajustes->nombre }}
        @if ($ajustes->direccion) · {{ $ajustes->direccion }} @endif
    </div>
</footer>
@endsection
