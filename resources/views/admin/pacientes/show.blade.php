@extends('layouts.admin')

@section('pretitulo', 'Clínica')
@section('titulo', $paciente->nombre_completo)
@section('subtitulo', $paciente->tipo_documento.' '.$paciente->numero_documento)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.pacientes.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        @can('citas.crear')
            <a href="{{ route('admin.citas.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-outline-primary">
                <i class="ti ti-calendar-plus me-1"></i>Agendar cita
            </a>
        @endcan
        @can('presupuestos.crear')
            <a href="{{ route('admin.presupuestos.create', ['paciente_id' => $paciente->id]) }}" class="btn btn-outline-success">
                <i class="ti ti-file-invoice me-1"></i>Presupuestar
            </a>
        @endcan
        @can('pacientes.editar')
            <a href="{{ route('admin.pacientes.edit', $paciente) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Editar</a>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Cabecera de datos de contacto y cobertura --}}
<div class="row row-cards mb-3">
    <div class="col-md-4">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small text-uppercase">Teléfono</div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-phone text-secondary"></i>
                <span class="fw-medium">{{ $paciente->telefono ?: 'No registra' }}</span>
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small text-uppercase">Obra social</div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-shield-heart text-secondary"></i>
                <span class="fw-medium">{{ $paciente->aseguradora?->nombre ?: 'Particular' }}</span>
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small text-uppercase">N° de afiliado</div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-id-badge-2 text-secondary"></i>
                <span class="fw-medium">{{ $paciente->numero_afiliado ?: '—' }}</span>
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small text-uppercase">Documento</div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-credit-card text-secondary"></i>
                <span class="fw-medium">{{ $paciente->tipo_documento }} {{ $paciente->numero_documento }}</span>
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card card-sm"><div class="card-body">
            <div class="text-secondary small text-uppercase">Email</div>
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-mail text-secondary"></i>
                <span class="fw-medium text-truncate">{{ $paciente->email ?: 'No registra' }}</span>
            </div>
        </div></div>
    </div>
</div>

@include('admin.pacientes._pestanas', ['paciente' => $paciente, 'activa' => 'ficha'])

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                @if ($paciente->fotografia)
                    <span class="avatar avatar-xl mb-3" style="background-image: url({{ Storage::url($paciente->fotografia) }})"></span>
                @else
                    <span class="avatar avatar-xl bg-blue-lt mb-3">
                        {{ mb_substr($paciente->nombres, 0, 1) }}{{ mb_substr($paciente->apellidos, 0, 1) }}
                    </span>
                @endif
                <h3 class="mb-1">{{ $paciente->nombre_completo }}</h3>
                <div class="text-secondary">
                    {{ $paciente->edad !== null ? $paciente->edad.' años' : 'Edad no registrada' }}
                    · {{ ['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'][$paciente->genero] ?? '' }}
                </div>
                <span class="badge bg-{{ $paciente->activo ? 'success' : 'secondary' }}-lt mt-2">
                    {{ $paciente->activo ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
            <div class="card-body border-top">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Teléfono</div>
                        <div class="datagrid-content">{{ $paciente->telefono ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Correo</div>
                        <div class="datagrid-content">{{ $paciente->email ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Grupo sanguíneo</div>
                        <div class="datagrid-content">{{ $paciente->grupo_sanguineo ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Dirección</div>
                        <div class="datagrid-content">{{ $paciente->direccion ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Contacto de emergencia</div>
                        <div class="datagrid-content">
                            {{ $paciente->contacto_emergencia ?: '—' }}
                            @if ($paciente->telefono_emergencia)
                                <div class="text-secondary small">{{ $paciente->telefono_emergencia }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-heartbeat me-2"></i>Antecedentes</h3></div>
            <div class="card-body">
                @foreach ([
                    'Alergias' => $paciente->alergias,
                    'Enfermedades' => $paciente->enfermedades,
                    'Medicación' => $paciente->medicamentos,
                    'Hábitos' => $paciente->habitos,
                    'Antecedentes' => $paciente->antecedentes,
                ] as $titulo => $valor)
                    <div class="mb-2">
                        <div class="text-secondary small text-uppercase">{{ $titulo }}</div>
                        <div>{{ $valor ?: 'Sin registro' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row row-cards mb-3">
            <div class="col-sm-4">
                <x-kpi titulo="Citas totales" :valor="$totalCitas" icono="ti ti-calendar-event" color="primary" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Total pagado" :valor="number_format($totalPagado, 2).' '.$ajustes->divisa" icono="ti ti-cash" color="success" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Saldo pendiente" :valor="number_format($saldo, 2).' '.$ajustes->divisa" icono="ti ti-alert-circle"
                       :color="$saldo > 0 ? 'danger' : 'secondary'" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar-event me-2"></i>Últimas citas</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Fecha</th><th>Doctor</th><th>Tratamiento</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($paciente->citas as $cita)
                            <tr>
                                <td>{{ $cita->fecha_hora }}</td>
                                <td class="text-secondary">{{ $cita->doctor->nombre_profesional }}</td>
                                <td class="text-secondary">{{ $cita->tratamiento->nombre }}</td>
                                <td class="text-center"><span class="badge bg-{{ $cita->color_estado }}-lt">{{ $cita->estado_legible }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Este paciente aún no tiene citas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-notes-medical me-2"></i>Historia clínica</h3>
                        @can('historiales.ver')
                            <a href="{{ route('admin.historiales.index', $paciente) }}" class="btn btn-sm btn-link ms-auto">Ver todo</a>
                        @endcan
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($paciente->historiales as $historial)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-medium">{{ $historial->fecha->format('d/m/Y') }}</span>
                                    <span class="text-secondary small">{{ $historial->doctor->nombre_profesional }}</span>
                                </div>
                                <div class="text-secondary small text-truncate">{{ $historial->diagnostico ?: $historial->motivo_consulta }}</div>
                            </div>
                        @empty
                            <div class="list-group-item text-secondary text-center py-4">Sin consultas registradas.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title"><i class="ti ti-dental me-2"></i>Odontogramas</h3>
                        @can('odontogramas.ver')
                            <a href="{{ route('admin.odontogramas.index', $paciente) }}" class="btn btn-sm btn-link ms-auto">Ver todo</a>
                        @endcan
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse ($paciente->odontogramas as $odontograma)
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-medium">{{ $odontograma->fecha->format('d/m/Y') }}</div>
                                    <div class="text-secondary small">{{ $odontograma->tipo }}</div>
                                </div>
                                <span class="badge bg-orange-lt">{{ $odontograma->piezas_afectadas }} piezas con hallazgo</span>
                            </div>
                        @empty
                            <div class="list-group-item text-secondary text-center py-4">Sin odontogramas registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-receipt me-2"></i>Recibos emitidos</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Recibo</th><th>Fecha</th><th class="text-end">Total</th><th class="text-end">Saldo</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($paciente->pagos as $pago)
                            <tr>
                                <td>
                                    @can('pagos.ver')
                                        <a href="{{ route('admin.pagos.show', $pago) }}" class="text-brand">{{ $pago->codigo_recibo }}</a>
                                    @else
                                        {{ $pago->codigo_recibo }}
                                    @endcan
                                </td>
                                <td class="text-secondary">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                                <td class="text-end">{{ number_format($pago->monto_total, 2) }}</td>
                                <td class="text-end {{ $pago->monto_saldo > 0 ? 'text-danger' : 'text-secondary' }}">
                                    {{ number_format($pago->monto_saldo, 2) }}
                                </td>
                                <td class="text-center"><span class="badge bg-{{ $pago->color_estado }}-lt">{{ $pago->estado }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">Sin recibos emitidos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
