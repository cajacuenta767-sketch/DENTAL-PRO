@extends('layouts.admin')

@section('pretitulo', 'Fidelización y Seguridad del Paciente')
@section('titulo', 'CRM Clínico · Seguimiento Post-Operatorio')
@section('subtitulo', 'Atención y control de evolución de pacientes intervenidos en las últimas 72 horas')

@section('acciones')
    <a href="{{ route('admin.citas.index') }}" class="btn btn-outline-secondary">
        <i class="ti ti-calendar-event me-1"></i>Ver Agenda de Citas
    </a>
@endsection

@section('contenido')
@if (session('exito'))
    <div class="alert alert-success alert-dismissible" role="alert">
        <i class="ti ti-check me-2"></i>{{ session('exito') }}
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="ti ti-heart-handshake me-2"></i>Pacientes atendidos recientemente (Últimos 3 días)</h3>
        <span class="badge bg-purple-lt">{{ $citas->total() }} pacientes en ventana post-operatoria</span>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-striped">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Tratamiento Realizado</th>
                    <th>Doctor Tratante</th>
                    <th>Fecha y Hora</th>
                    <th>Estado de Contacto</th>
                    <th>Último Contacto</th>
                    <th class="w-1 text-end">Acciones Rápidas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($citas as $cita)
                    @php
                        $tel = preg_replace('/[^0-9]/', '', $cita->paciente->telefono ?? '');
                        $mensaje = "Hola {$cita->paciente->nombres}, le saludamos de {$clinica->nombre}. Esperamos que se encuentre muy bien tras su atención de {$cita->tratamiento?->nombre} con el {$cita->doctor?->nombre_profesional}. ¿Presenta alguna molestia, inflamación o dolor? Estamos a su entera disposición.";
                        $waUrl = $tel ? 'https://wa.me/'.$tel.'?text='.urlencode($mensaje) : null;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('admin.pacientes.show', $cita->paciente) }}" class="fw-bold text-dark text-decoration-none">
                                {{ $cita->paciente->nombre_completo }}
                            </a>
                            <div class="small text-secondary">
                                <i class="ti ti-phone me-1"></i>{{ $cita->paciente->telefono ?: 'Sin teléfono' }}
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-blue-lt">{{ $cita->tratamiento?->nombre ?? 'Consulta General' }}</span>
                        </td>
                        <td>
                            <div class="small fw-medium">{{ $cita->doctor?->nombre_profesional }}</div>
                        </td>
                        <td>
                            <div>{{ $cita->fecha->format('d/m/Y') }}</div>
                            <div class="small text-secondary">{{ $cita->hora }}</div>
                        </td>
                        <td>
                            @if ($cita->postop_estado === 'CONTACTADO')
                                <span class="badge bg-success"><i class="ti ti-check me-1"></i>Contactado / Conforme</span>
                            @elseif ($cita->postop_estado === 'REQUIERE_REVISION')
                                <span class="badge bg-danger"><i class="ti ti-alert-triangle me-1"></i>Requiere Revisión</span>
                            @elseif ($cita->postop_estado === 'SIN_RESPUESTA')
                                <span class="badge bg-warning"><i class="ti ti-phone-off me-1"></i>Sin Respuesta</span>
                            @else
                                <span class="badge bg-secondary-lt"><i class="ti ti-clock me-1"></i>Pendiente de Contacto</span>
                            @endif
                        </td>
                        <td>
                            <div class="small text-secondary">
                                {{ $cita->postop_contactado_en ? $cita->postop_contactado_en->diffForHumans() : 'No contactado' }}
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($waUrl)
                                <a href="{{ $waUrl }}" target="_blank" class="btn btn-sm btn-outline-success" title="Enviar WhatsApp de seguimiento">
                                    <i class="ti ti-brand-whatsapp me-1"></i>WhatsApp
                                </a>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Registrar
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <form action="{{ route('admin.crm-postop.contactar', $cita) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="postop_estado" value="CONTACTADO">
                                    <button type="submit" class="dropdown-item text-success">
                                        <i class="ti ti-check me-2"></i>Marcar: Contactado (Evolución Favorable)
                                    </button>
                                </form>
                                <form action="{{ route('admin.crm-postop.contactar', $cita) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="postop_estado" value="REQUIERE_REVISION">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="ti ti-alert-triangle me-2"></i>Marcar: Presenta Molestia / Revisar
                                    </button>
                                </form>
                                <form action="{{ route('admin.crm-postop.contactar', $cita) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="postop_estado" value="SIN_RESPUESTA">
                                    <button type="submit" class="dropdown-item text-warning">
                                        <i class="ti ti-phone-off me-2"></i>Marcar: Sin Respuesta
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-secondary">
                            <i class="ti ti-heart-check fs-1 d-block mb-2 text-muted"></i>
                            No hay pacientes atendidos en los últimos 3 días pendientes de seguimiento.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($citas->hasPages())
        <div class="card-footer d-flex justify-content-end">
            {{ $citas->links() }}
        </div>
    @endif
</div>
@endsection
