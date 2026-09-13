@extends('layouts.admin')

@section('pretitulo', 'Administración')
@section('titulo', 'Cita '.$cita->token)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.citas.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        @can('historiales.crear')
            @unless ($cita->historial)
                <a href="{{ route('admin.historiales.create', ['paciente' => $cita->paciente_id, 'cita_id' => $cita->id]) }}"
                   class="btn btn-outline-azure">
                    <i class="ti ti-notes-medical me-1"></i>Registrar consulta
                </a>
            @endunless
        @endcan
        @can('pagos.crear')
            @unless ($cita->esta_pagada)
                <a href="{{ route('admin.pagos.create', ['cita_id' => $cita->id]) }}" class="btn btn-outline-orange">
                    <i class="ti ti-cash me-1"></i>Cobrar
                </a>
            @endunless
        @endcan
        @can('citas.editar')
            <a href="{{ route('admin.citas.edit', $cita) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Editar</a>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar-event me-2"></i>Detalle de la cita</h3>
                <span class="badge bg-{{ $cita->color_estado }} ms-auto">{{ $cita->estado_legible }}</span>
            </div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Código</div>
                        <div class="datagrid-content font-monospace">{{ $cita->token }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha y hora</div>
                        <div class="datagrid-content">{{ $cita->fecha_hora }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Paciente</div>
                        <div class="datagrid-content">
                            <a href="{{ route('admin.pacientes.show', $cita->paciente) }}" class="text-brand">
                                {{ $cita->paciente->nombre_completo }}
                            </a>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Doctor</div>
                        <div class="datagrid-content">{{ $cita->doctor->nombre_profesional }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Especialidad</div>
                        <div class="datagrid-content">{{ $cita->doctor->especialidad->nombre }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Tratamiento</div>
                        <div class="datagrid-content">{{ $cita->tratamiento->nombre }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Duración</div>
                        <div class="datagrid-content">{{ $cita->tratamiento->duracion }} minutos</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Precio de referencia</div>
                        <div class="datagrid-content">{{ number_format($cita->tratamiento->precio, 2) }} {{ $ajustes->divisa }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Origen</div>
                        <div class="datagrid-content">{{ $cita->origen === 'ONLINE' ? 'Reserva en línea' : 'Recepción' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Recordatorio</div>
                        <div class="datagrid-content">
                            {{ $cita->recordatorio_enviado_en?->format('d/m/Y H:i') ?? 'No enviado' }}
                        </div>
                    </div>
                </div>

                @if ($cita->motivo)
                    <div class="mt-3">
                        <div class="text-secondary small text-uppercase">Motivo de consulta</div>
                        <div>{{ $cita->motivo }}</div>
                    </div>
                @endif

                @if ($cita->observacion)
                    <div class="mt-3">
                        <div class="text-secondary small text-uppercase">Observaciones</div>
                        <div>{{ $cita->observacion }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-receipt me-2"></i>Cobros de esta cita</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Recibo</th><th>Método</th><th class="text-end">Total</th><th class="text-end">Pagado</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($cita->pagos as $pago)
                            <tr>
                                <td>
                                    @can('pagos.ver')
                                        <a href="{{ route('admin.pagos.show', $pago) }}" class="text-brand">{{ $pago->codigo_recibo }}</a>
                                    @else
                                        {{ $pago->codigo_recibo }}
                                    @endcan
                                </td>
                                <td><span class="badge bg-azure-lt">{{ $pago->metodo_pago }}</span></td>
                                <td class="text-end">{{ number_format($pago->monto_total, 2) }}</td>
                                <td class="text-end">{{ number_format($pago->monto_pagado, 2) }}</td>
                                <td class="text-center"><span class="badge bg-{{ $pago->color_estado }}-lt">{{ $pago->estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Esta cita aún no tiene cobros registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Paciente</h3></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    @if ($cita->paciente->fotografia)
                        <span class="avatar avatar-lg" style="background-image: url({{ Storage::url($cita->paciente->fotografia) }})"></span>
                    @else
                        <span class="avatar avatar-lg bg-blue-lt">
                            {{ mb_substr($cita->paciente->nombres, 0, 1) }}{{ mb_substr($cita->paciente->apellidos, 0, 1) }}
                        </span>
                    @endif
                    <div>
                        <div class="fw-medium">{{ $cita->paciente->nombre_completo }}</div>
                        <div class="text-secondary small">{{ $cita->paciente->tipo_documento }} {{ $cita->paciente->numero_documento }}</div>
                    </div>
                </div>
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Teléfono</div>
                        <div class="datagrid-content">{{ $cita->paciente->telefono ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Correo</div>
                        <div class="datagrid-content">{{ $cita->paciente->email ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Alergias</div>
                        <div class="datagrid-content">{{ $cita->paciente->alergias ?: 'Sin registro' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Registros clínicos</h3></div>
            <div class="list-group list-group-flush">
                <div class="list-group-item d-flex align-items-center gap-2">
                    <i class="ti ti-notes-medical text-azure"></i>
                    <div class="flex-fill">Historia clínica</div>
                    @if ($cita->historial)
                        @can('historiales.ver')
                            <a href="{{ route('admin.historiales.index', $cita->paciente) }}" class="btn btn-sm btn-outline-azure">Ver</a>
                        @endcan
                    @else
                        <span class="badge bg-secondary-lt">Sin registrar</span>
                    @endif
                </div>
                <div class="list-group-item d-flex align-items-center gap-2">
                    <i class="ti ti-dental text-purple"></i>
                    <div class="flex-fill">Odontograma</div>
                    @if ($cita->odontograma)
                        @can('odontogramas.ver')
                            <a href="{{ route('admin.odontogramas.index', $cita->paciente) }}" class="btn btn-sm btn-outline-purple">Ver</a>
                        @endcan
                    @else
                        @can('odontogramas.crear')
                            <a href="{{ route('admin.odontogramas.create', ['paciente' => $cita->paciente_id, 'cita_id' => $cita->id]) }}"
                               class="btn btn-sm btn-outline-purple">Crear</a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
