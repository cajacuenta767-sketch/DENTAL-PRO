@extends('layouts.admin')

@section('pretitulo', 'Inteligencia y Analítica Clínica')
@section('titulo', 'Centro de Reportes y Estadísticas')

@section('contenido')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><i class="ti ti-calendar me-1"></i>Fecha inicio</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label"><i class="ti ti-calendar me-1"></i>Fecha fin</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label"><i class="ti ti-bolt me-1"></i>Accesos rápidos</label>
                <div class="btn-list">
                    <a href="{{ route('admin.reportes.index', ['desde' => now()->toDateString(), 'hasta' => now()->toDateString()]) }}"
                       class="btn btn-sm btn-outline-secondary">Hoy</a>
                    <a href="{{ route('admin.reportes.index', ['desde' => now()->startOfWeek()->toDateString(), 'hasta' => now()->endOfWeek()->toDateString()]) }}"
                       class="btn btn-sm btn-outline-secondary">Semana</a>
                    <a href="{{ route('admin.reportes.index', ['desde' => now()->startOfMonth()->toDateString(), 'hasta' => now()->endOfMonth()->toDateString()]) }}"
                       class="btn btn-sm btn-outline-secondary">Este Mes</a>
                    <a href="{{ route('admin.reportes.index', ['desde' => now()->startOfYear()->toDateString(), 'hasta' => now()->endOfYear()->toDateString()]) }}"
                       class="btn btn-sm btn-outline-secondary">Este Año</a>
                </div>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="ti ti-filter me-1"></i>Filtrar</button>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-bordered mb-3" role="tablist">
    <li class="nav-item">
        <a href="#tab-financiero" class="nav-link active" data-bs-toggle="tab" role="tab">
            <i class="ti ti-coin me-1"></i>1. Financiero &amp; Caja
        </a>
    </li>
    <li class="nav-item">
        <a href="#tab-citas" class="nav-link" data-bs-toggle="tab" role="tab">
            <i class="ti ti-calendar-stats me-1"></i>2. Citas &amp; Productividad
        </a>
    </li>
    <li class="nav-item">
        <a href="#tab-padron" class="nav-link" data-bs-toggle="tab" role="tab">
            <i class="ti ti-users me-1"></i>3. Padrón de Pacientes
        </a>
    </li>
    <li class="nav-item">
        <a href="#tab-tratamientos" class="nav-link" data-bs-toggle="tab" role="tab">
            <i class="ti ti-dental me-1"></i>4. Tratamientos &amp; Rentabilidad
        </a>
    </li>
</ul>

<div class="tab-content">
    {{-- 1. Financiero --}}
    <div class="tab-pane active show" id="tab-financiero" role="tabpanel">
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Total Ingresos Recaudados" :valor="number_format($finTotal, 2).' '.$ajustes->divisa"
                       icono="ti ti-coin" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Saldos por Cobrar" :valor="number_format($finSaldos, 2).' '.$ajustes->divisa"
                       icono="ti ti-alert-circle" color="danger" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Efectivo vs Digital"
                       :valor="number_format($finEfectivo, 0).' / '.number_format($finDigital, 0)"
                       icono="ti ti-arrows-exchange" color="azure" :pie="$ajustes->divisa" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Recibos Emitidos" :valor="$finRecibos" icono="ti ti-receipt" color="indigo" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <input type="hidden" name="desde" value="{{ $desde->format('Y-m-d') }}">
                    <input type="hidden" name="hasta" value="{{ $hasta->format('Y-m-d') }}">
                    <div class="col-md-3">
                        <select name="doctor_id" class="form-select">
                            <option value="">— Todos los Doctores —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(($filtros['doctor_id'] ?? null) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="metodo" class="form-select">
                            <option value="">— Todos los Métodos —</option>
                            @foreach (\App\Models\Pago::METODOS as $metodo)
                                <option value="{{ $metodo }}" @selected(($filtros['metodo'] ?? null) === $metodo)>{{ $metodo }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="estado" class="form-select">
                            <option value="">— Todos los Estados —</option>
                            @foreach (\App\Models\Pago::ESTADOS as $estado)
                                <option value="{{ $estado }}" @selected(($filtros['estado'] ?? null) === $estado)>{{ $estado }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-primary w-100"><i class="ti ti-filter"></i></button>
                    </div>
                    <div class="col-md-2">
                        @can('reportes.exportar')
                            <a href="{{ route('admin.reportes.exportar', array_merge(['seccion' => 'financiero'], request()->query())) }}"
                               class="btn btn-danger w-100">
                                <i class="ti ti-file-type-pdf me-1"></i>Exportar PDF
                            </a>
                        @endcan
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Recaudación por método</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Método</th><th class="text-center">Recibos</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                @forelse ($finPorMetodo as $fila)
                                    <tr>
                                        <td><span class="badge bg-azure-lt">{{ $fila->metodo_pago }}</span></td>
                                        <td class="text-center">{{ $fila->recibos }}</td>
                                        <td class="text-end fw-medium">{{ number_format($fila->total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary py-4">Sin movimientos en el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Recibos del período</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr><th>Recibo</th><th>Fecha</th><th>Paciente</th><th>Doctor</th>
                                    <th class="text-center">Método</th><th class="text-center">Estado</th>
                                    <th class="text-end">Pagado</th><th class="text-end">Saldo</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($finListado as $pago)
                                    <tr>
                                        <td class="font-monospace small">
                                            <a href="{{ route('admin.pagos.show', $pago) }}" class="text-brand">{{ $pago->codigo_recibo }}</a>
                                        </td>
                                        <td class="text-secondary small">{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <div>{{ $pago->paciente->nombre_completo }}</div>
                                            <div class="text-secondary small">Doc. {{ $pago->paciente->numero_documento }}</div>
                                        </td>
                                        <td class="text-secondary small">{{ $pago->doctor?->nombre_profesional ?? '—' }}</td>
                                        <td class="text-center"><span class="badge bg-azure-lt">{{ $pago->metodo_pago }}</span></td>
                                        <td class="text-center"><span class="badge bg-{{ $pago->color_estado }}-lt">{{ $pago->estado }}</span></td>
                                        <td class="text-end">{{ number_format($pago->monto_pagado, 2) }}</td>
                                        <td class="text-end {{ $pago->monto_saldo > 0 ? 'text-danger' : 'text-secondary' }}">
                                            {{ number_format($pago->monto_saldo, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-secondary py-4">Sin recibos en el rango seleccionado.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Citas y productividad --}}
    <div class="tab-pane" id="tab-citas" role="tabpanel">
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Citas Agendadas" :valor="$citTotal" icono="ti ti-calendar-event" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Citas Completadas" :valor="$citCompletadas" icono="ti ti-circle-check" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Citas Canceladas" :valor="$citCanceladas" icono="ti ti-calendar-x" color="danger" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Tasa de Asistencia" :valor="$citTasaAsistencia.'%'" icono="ti ti-percentage" color="azure" />
            </div>
        </div>

        @can('reportes.exportar')
            <div class="mb-3 text-end">
                <a href="{{ route('admin.reportes.exportar', array_merge(['seccion' => 'citas'], request()->query())) }}"
                   class="btn btn-danger"><i class="ti ti-file-type-pdf me-1"></i>Exportar PDF</a>
            </div>
        @endcan

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Productividad por doctor</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Doctor</th><th>Especialidad</th><th class="text-center">Agendadas</th>
                                <th class="text-center">Completadas</th><th class="text-center">Efectividad</th></tr></thead>
                            <tbody>
                                @forelse ($citPorDoctor as $fila)
                                    <tr>
                                        <td>{{ $fila->doctor?->nombre_profesional ?? '—' }}</td>
                                        <td>
                                            <span class="badge" style="background-color: {{ $fila->doctor?->especialidad?->color }}20; color: {{ $fila->doctor?->especialidad?->color }}">
                                                {{ $fila->doctor?->especialidad?->nombre ?? '—' }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $fila->total }}</td>
                                        <td class="text-center text-success">{{ $fila->completadas }}</td>
                                        <td class="text-center">
                                            {{ $fila->total > 0 ? round($fila->completadas / $fila->total * 100, 1) : 0 }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">Sin citas en el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Reparto por estado</h3></div>
                    <div class="card-body">
                        <div id="grafica-reporte-estados" style="min-height: 260px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Padrón --}}
    <div class="tab-pane" id="tab-padron" role="tabpanel">
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Pacientes en Padrón" :valor="$pacTotal" icono="ti ti-users" color="primary" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Nuevos en el Período" :valor="$pacNuevos" icono="ti ti-user-plus" color="success" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Pacientes Activos" :valor="$pacActivos" icono="ti ti-user-check" color="azure" />
            </div>
            <div class="col-sm-6 col-xl-3">
                <x-kpi titulo="Con Saldo Pendiente" :valor="$pacConSaldo->count()" icono="ti ti-alert-circle" color="danger" />
            </div>
        </div>

        @can('reportes.exportar')
            <div class="mb-3 text-end">
                <a href="{{ route('admin.reportes.exportar', array_merge(['seccion' => 'padron'], request()->query())) }}"
                   class="btn btn-danger"><i class="ti ti-file-type-pdf me-1"></i>Exportar PDF</a>
            </div>
        @endcan

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Pacientes con saldo pendiente</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Paciente</th><th>Documento</th><th>Teléfono</th><th class="text-end">Saldo</th></tr></thead>
                            <tbody>
                                @forelse ($pacConSaldo as $paciente)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.pacientes.show', $paciente) }}" class="text-brand">
                                                {{ $paciente->nombre_completo }}
                                            </a>
                                        </td>
                                        <td class="text-secondary">{{ $paciente->numero_documento }}</td>
                                        <td class="text-secondary">{{ $paciente->telefono ?: '—' }}</td>
                                        <td class="text-end text-danger fw-medium">{{ number_format($paciente->saldo_total, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-secondary py-4">Ningún paciente tiene saldo pendiente.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Pacientes más frecuentes del período</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Paciente</th><th class="text-center">Citas</th><th class="text-center">Edad</th></tr></thead>
                            <tbody>
                                @forelse ($pacFrecuentes as $paciente)
                                    <tr>
                                        <td>{{ $paciente->nombre_completo }}</td>
                                        <td class="text-center">{{ $paciente->citas_count }}</td>
                                        <td class="text-center text-secondary">{{ $paciente->edad !== null ? $paciente->edad : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-secondary py-4">Sin datos en el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. Tratamientos --}}
    <div class="tab-pane" id="tab-tratamientos" role="tabpanel">
        <div class="row row-cards mb-3">
            <div class="col-sm-6 col-xl-4">
                <x-kpi titulo="Total Facturado" :valor="number_format($traFacturado, 2).' '.$ajustes->divisa"
                       icono="ti ti-coin" color="success" />
            </div>
            <div class="col-sm-6 col-xl-4">
                <x-kpi titulo="Unidades Vendidas" :valor="$traUnidades" icono="ti ti-package" color="azure" />
            </div>
            <div class="col-sm-6 col-xl-4">
                <x-kpi titulo="Conceptos Distintos" :valor="$traLineas->count()" icono="ti ti-list" color="indigo" />
            </div>
        </div>

        @can('reportes.exportar')
            <div class="mb-3 text-end">
                <a href="{{ route('admin.reportes.exportar', array_merge(['seccion' => 'tratamientos'], request()->query())) }}"
                   class="btn btn-danger"><i class="ti ti-file-type-pdf me-1"></i>Exportar PDF</a>
            </div>
        @endcan

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Ranking de tratamientos facturados</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th style="width:3rem;">#</th><th>Concepto</th>
                                <th class="text-center">Unidades</th><th class="text-end">Facturado</th>
                                <th class="text-end">% del total</th></tr></thead>
                            <tbody>
                                @forelse ($traLineas as $linea)
                                    <tr>
                                        <td class="text-secondary">{{ $loop->iteration }}</td>
                                        <td>{{ $linea->descripcion }}</td>
                                        <td class="text-center">{{ $linea->unidades }}</td>
                                        <td class="text-end fw-medium">{{ number_format($linea->facturado, 2) }}</td>
                                        <td class="text-end text-secondary">
                                            {{ $traFacturado > 0 ? round($linea->facturado / $traFacturado * 100, 1) : 0 }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-secondary py-4">Sin facturación en el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header"><h3 class="card-title">Facturación por especialidad</h3></div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead><tr><th>Especialidad</th><th class="text-end">Facturado</th></tr></thead>
                            <tbody>
                                @forelse ($traPorEspecialidad as $fila)
                                    <tr>
                                        <td>
                                            <span class="badge me-1" style="background-color: {{ $fila->color }}">&nbsp;</span>
                                            {{ $fila->nombre }}
                                        </td>
                                        <td class="text-end fw-medium">{{ number_format($fila->facturado, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-secondary py-4">Sin datos en el rango.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const porEstado = @json($citPorEstado);
    const paleta = {
        COMPLETADA: '#2fb344', CONFIRMADA: '#4299e1', PENDIENTE: '#f76707',
        EN_CURSO: '#f59f00', CANCELADA: '#d63939',
    };

    if (!porEstado.length) return;

    new ApexCharts(document.getElementById('grafica-reporte-estados'), {
        chart: { type: 'pie', height: 260, fontFamily: 'inherit' },
        series: porEstado.map((f) => Number(f.total)),
        labels: porEstado.map((f) => f.estado.replace('_', ' ')),
        colors: porEstado.map((f) => paleta[f.estado] ?? '#adb5bd'),
        legend: { position: 'bottom' },
    }).render();
});
</script>
@endpush
@endsection
