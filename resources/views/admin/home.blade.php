@extends('layouts.admin')

@section('pretitulo', mb_strtoupper($ajustes->nombre))
@section('titulo', 'Panel de Control')
@section('subtitulo', ucfirst(now()->translatedFormat('l, d \d\e F \d\e Y')))

@section('acciones')
    <div class="btn-list">
        @can('pacientes.crear')
            <a href="{{ route('admin.pacientes.create') }}" class="btn btn-outline-primary">
                <i class="ti ti-user-plus me-1"></i>Nuevo Paciente
            </a>
        @endcan
        @can('citas.ver')
            <a href="{{ route('admin.citas.index') }}" class="btn btn-primary">
                <i class="ti ti-calendar-event me-1"></i>Bandeja de Citas
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Indicadores --}}
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Citas de Hoy" :valor="$kpis['citasHoy']" icono="ti ti-calendar-event" color="primary"
               :pie="'<span class=\'text-warning\'>'.$kpis['citasHoyPendientes'].' pend.</span> ·
                     <span class=\'text-azure\'>'.$kpis['citasHoyConfirmadas'].' conf.</span> ·
                     <span class=\'text-success\'>'.$kpis['citasHoyAtendidas'].' aten.</span>'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Pacientes Registrados" :valor="$kpis['pacientes']" icono="ti ti-users" color="success"
               :pie="'+'.$kpis['pacientesNuevos'].' nuevos este mes'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Ingresos en Caja (Mes)" :valor="number_format($kpis['ingresosMes'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="yellow" :pie="$kpis['recibosMes'].' pagos registrados'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Equipo Médico" :valor="$kpis['doctores']" icono="ti ti-stethoscope" color="azure"
               :pie="'Doctores · '.$kpis['especialidades'].' especialidades'" />
    </div>
</div>

<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Tratamientos" :valor="$kpis['tratamientos']" icono="ti ti-dental" color="purple"
               :pie="$kpis['tratamientosActivos'].' activos en catálogo'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Especialidades" :valor="$kpis['especialidades']" icono="ti ti-heartbeat" color="teal"
               pie="En servicio clínico" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Horarios de Atención" :valor="$kpis['horarios']" icono="ti ti-clock-hour-4" color="indigo"
               :pie="$kpis['horariosActivos'].' turnos activos'" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Usuarios del Sistema" :valor="$kpis['usuarios']" icono="ti ti-shield-lock" color="cyan"
               :pie="$kpis['roles'].' roles · '.$kpis['permisos'].' permisos'" />
    </div>
</div>

{{-- Gráficas --}}
<div class="row row-cards mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-chart-area-line me-2"></i>Rendimiento de Citas (Últimos 6 Meses)</h3>
            </div>
            <div class="card-body">
                <div id="grafica-rendimiento" style="min-height: 320px;"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-chart-donut me-2"></i>Estado Global de Citas</h3></div>
            <div class="card-body">
                <div id="grafica-estados" style="min-height: 260px;"></div>
                <div class="row text-center mt-3">
                    @foreach (['COMPLETADA' => 'Completadas', 'CONFIRMADA' => 'Confirmadas', 'PENDIENTE' => 'Pendientes'] as $clave => $etiqueta)
                        <div class="col-4">
                            <div class="h3 mb-0">{{ $estadoGlobal['detalle'][$clave] ?? 0 }}</div>
                            <div class="text-secondary small">{{ $etiqueta }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Listados --}}
<div class="row row-cards">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-calendar-event me-2"></i>Próximas citas</h3>
                @can('citas.ver')
                    <a href="{{ route('admin.citas.index') }}" class="btn btn-sm btn-link ms-auto">Ver todas</a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Paciente</th><th>Doctor</th><th>Fecha</th><th class="text-center">Estado</th></tr></thead>
                    <tbody>
                        @forelse ($proximasCitas as $cita)
                            <tr>
                                <td>
                                    <div class="fw-medium">{{ $cita->paciente->nombre_completo }}</div>
                                    <div class="text-secondary small">{{ $cita->tratamiento->nombre }}</div>
                                </td>
                                <td class="text-secondary">{{ $cita->doctor->nombre_profesional }}</td>
                                <td>{{ $cita->fecha_hora }}</td>
                                <td class="text-center"><span class="badge bg-{{ $cita->color_estado }}-lt">{{ $cita->estado_legible }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No hay citas próximas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-award me-2"></i>Tratamientos más solicitados</h3></div>
            <div class="list-group list-group-flush">
                @forelse ($topTratamientos as $fila)
                    <div class="list-group-item d-flex align-items-center gap-2">
                        <span class="badge" style="background-color: {{ $fila->tratamiento?->especialidad?->color ?? '#adb5bd' }}">&nbsp;</span>
                        <div class="flex-fill text-truncate">{{ $fila->tratamiento?->nombre ?? 'Sin tratamiento' }}</div>
                        <span class="badge bg-secondary-lt">{{ $fila->total }} citas</span>
                    </div>
                @empty
                    <div class="list-group-item text-secondary text-center py-4">Aún no hay citas registradas.</div>
                @endforelse
            </div>
        </div>

        @can('pagos.ver')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-receipt me-2"></i>Últimos cobros</h3>
                    <a href="{{ route('admin.pagos.index') }}" class="btn btn-sm btn-link ms-auto">Ir a caja</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($ultimosPagos as $pago)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-fill">
                                <div class="fw-medium">{{ $pago->paciente->nombre_completo }}</div>
                                <div class="text-secondary small">{{ $pago->codigo_recibo }} · {{ $pago->metodo_pago }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-medium">{{ number_format($pago->monto_pagado, 2) }}</div>
                                <span class="badge bg-{{ $pago->color_estado }}-lt">{{ $pago->estado }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary text-center py-4">Sin cobros registrados.</div>
                    @endforelse
                </div>
            </div>
        @endcan
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rendimiento = @json($rendimiento);
    const estados = @json($estadoGlobal);

    new ApexCharts(document.getElementById('grafica-rendimiento'), {
        chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Total Citas Agendadas', data: rendimiento.agendadas },
            { name: 'Citas Completadas', data: rendimiento.completadas },
        ],
        colors: ['#4299e1', '#2fb344'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
        xaxis: { categories: rendimiento.etiquetas, tooltip: { enabled: false } },
        yaxis: { min: 0, forceNiceScale: true },
        legend: { position: 'top', horizontalAlign: 'right' },
        grid: { strokeDashArray: 4 },
    }).render();

    new ApexCharts(document.getElementById('grafica-estados'), {
        chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
        series: estados.series,
        labels: estados.etiquetas,
        colors: estados.colores,
        legend: { position: 'bottom' },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        total: { show: true, label: 'Total Citas', formatter: () => estados.total },
                    },
                },
            },
        },
    }).render();
});
</script>
@endpush
@endsection
