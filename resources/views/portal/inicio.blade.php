@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Hola, ' . $paciente->nombres)

@section('acciones')
    <div class="btn-list">
        @if ($ajustes->portal_reservas_activas)
            <a href="{{ route('portal.reservar') }}" class="btn btn-primary">
                <i class="ti ti-calendar-plus me-1"></i>Reservar cita
            </a>
        @endif
        <a href="{{ route('portal.citas') }}" class="btn btn-outline-primary">
            <i class="ti ti-calendar-event me-1"></i>Ver todas mis citas
        </a>
    </div>
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <x-kpi titulo="Próximas citas" :valor="$proximas->count()" icono="ti ti-calendar-event" color="primary" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-kpi titulo="Saldo pendiente"
               :valor="($ajustes->simbolo_divisa ?? '') . ' ' . number_format((float) $saldo, 2)"
               icono="ti ti-cash"
               :color="$saldo > 0 ? 'warning' : 'success'" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-kpi titulo="Presupuestos abiertos" :valor="$presupuestosAbiertos" icono="ti ti-file-invoice" color="info" />
    </div>
    <div class="col-sm-6 col-lg-3">
        <x-kpi titulo="Documentos emitidos" :valor="$documentos" icono="ti ti-file-text" color="secondary" />
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar-event me-2 text-primary"></i>Próximas citas</h3>
                <div class="card-actions">
                    <a href="{{ route('portal.citas') }}" class="btn btn-sm btn-link">Ver historial</a>
                </div>
            </div>
            @if ($proximas->isEmpty())
                <div class="card-body">
                    <x-vacio icono="ti ti-calendar-off" titulo="No tienes citas programadas"
                             texto="Cuando la clínica agende una cita para ti, aparecerá aquí." />
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach ($proximas as $cita)
                        <div class="list-group-item">
                            <div class="row align-items-center g-2">
                                <div class="col-auto">
                                    <span class="avatar bg-{{ $cita->color_estado }}-lt text-{{ $cita->color_estado }}">
                                        <i class="ti ti-clock"></i>
                                    </span>
                                </div>
                                <div class="col">
                                    <div class="fw-bold">{{ $cita->fecha_hora }}</div>
                                    <div class="text-secondary small">
                                        <i class="ti ti-stethoscope me-1"></i>{{ $cita->doctor?->nombre_profesional ?? 'Por asignar' }}
                                        @if ($cita->doctor?->especialidad)
                                            <span class="text-secondary">· {{ $cita->doctor->especialidad->nombre }}</span>
                                        @endif
                                    </div>
                                    @if ($cita->tratamiento)
                                        <div class="text-secondary small">
                                            <i class="ti ti-dental me-1"></i>{{ $cita->tratamiento->nombre }}
                                        </div>
                                    @endif
                                </div>
                                <div class="col-auto">
                                    <span class="badge bg-{{ $cita->color_estado }}">{{ $cita->estado_legible }}</span>
                                </div>
                                @if ($cita->cancelable_por_paciente)
                                    <div class="col-auto">
                                        <form method="POST" action="{{ route('portal.citas.cancelar', $cita) }}"
                                              data-confirmar="¿Seguro que deseas cancelar la cita del {{ $cita->fecha_hora }}?">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancelar cita">
                                                <i class="ti ti-x me-1"></i>Cancelar
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-building-hospital me-2 text-primary"></i>{{ $ajustes->nombre ?? 'La clínica' }}</h3>
            </div>
            <div class="card-body">
                <p class="text-secondary">
                    ¿Necesitas reprogramar una cita o tienes alguna consulta? Comunícate con nosotros.
                </p>
                <div class="datagrid">
                    @if ($ajustes->telefono)
                        <div class="datagrid-item">
                            <div class="datagrid-title">Teléfono</div>
                            <div class="datagrid-content">
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ajustes->telefono) }}" class="text-reset">
                                    <i class="ti ti-phone me-1"></i>{{ $ajustes->telefono }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @if ($ajustes->direccion)
                        <div class="datagrid-item">
                            <div class="datagrid-title">Dirección</div>
                            <div class="datagrid-content"><i class="ti ti-map-pin me-1"></i>{{ $ajustes->direccion }}</div>
                        </div>
                    @endif
                    @if ($ajustes->email)
                        <div class="datagrid-item">
                            <div class="datagrid-title">Correo</div>
                            <div class="datagrid-content">
                                <a href="mailto:{{ $ajustes->email }}" class="text-reset"><i class="ti ti-mail me-1"></i>{{ $ajustes->email }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @if ($ajustes->whatsapp)
                <div class="card-footer">
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $ajustes->whatsapp) }}" target="_blank" rel="noopener"
                       class="btn btn-success w-100">
                        <i class="ti ti-brand-whatsapp me-1"></i>Escribir por WhatsApp
                    </a>
                </div>
            @endif
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title mb-3">Accesos rápidos</h3>
                <div class="list-group list-group-flush">
                    <a href="{{ route('portal.documentos') }}" class="list-group-item list-group-item-action">
                        <i class="ti ti-file-text me-2 text-secondary"></i>Mis documentos clínicos
                    </a>
                    <a href="{{ route('portal.presupuestos') }}" class="list-group-item list-group-item-action">
                        <i class="ti ti-file-invoice me-2 text-secondary"></i>Mis presupuestos
                    </a>
                    <a href="{{ route('portal.pagos') }}" class="list-group-item list-group-item-action">
                        <i class="ti ti-cash me-2 text-secondary"></i>Mis pagos y recibos
                    </a>
                    <a href="{{ route('perfil.edit') }}" class="list-group-item list-group-item-action">
                        <i class="ti ti-user me-2 text-secondary"></i>Mi perfil y seguridad
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
