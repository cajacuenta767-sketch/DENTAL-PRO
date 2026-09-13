@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', 'Presupuestos y Planes de Tratamiento')

@section('acciones')
    @can('presupuestos.crear')
        <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nuevo Presupuesto
        </a>
    @endcan
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Presupuestos abiertos" :valor="$totales['abiertos']" icono="ti ti-file-invoice" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Monto en cartera" :valor="number_format($totales['montoAbierto'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="warning" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Aprobados" :valor="$totales['aprobados']" icono="ti ti-circle-check" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Tasa de cierre" :valor="$totales['tasaCierre'].'%'" icono="ti ti-percentage" color="azure"
               pie="de los presentados" />
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Código, paciente o documento">
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select">
                    <option value="">— Todos los estados —</option>
                    @foreach (\App\Models\Presupuesto::ESTADOS as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('estado') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="doctor_id" class="form-select">
                    <option value="">— Todos los doctores —</option>
                    @foreach ($doctores as $doctor)
                        <option value="{{ $doctor->id }}" @selected(request('doctor_id') == $doctor->id)>
                            {{ $doctor->nombre_profesional }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-filter"></i></button>
                <a href="{{ route('admin.presupuestos.index') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>Doctor</th>
                    <th class="text-center">Tratamientos</th>
                    <th class="text-center">Avance</th>
                    <th class="text-end">Cobertura</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($presupuestos as $presupuesto)
                    <tr>
                        <td>
                            <div class="font-monospace small">{{ $presupuesto->codigo }}</div>
                            <div class="text-secondary small">{{ $presupuesto->fecha->format('d/m/Y') }}</div>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $presupuesto->paciente->nombre_completo }}</div>
                            @if ($presupuesto->paciente->aseguradora)
                                <div class="text-secondary small">
                                    <i class="ti ti-shield-heart me-1"></i>{{ $presupuesto->paciente->aseguradora->nombre }}
                                </div>
                            @endif
                        </td>
                        <td class="text-secondary small">{{ $presupuesto->doctor?->nombre_profesional ?? '—' }}</td>
                        <td class="text-center">{{ $presupuesto->detalles_count }}</td>
                        <td class="text-center" style="min-width: 8rem;">
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-{{ $presupuesto->color_estado }}"
                                     style="width: {{ $presupuesto->avance }}%"></div>
                            </div>
                            <div class="text-secondary small mt-1">{{ $presupuesto->avance }}%</div>
                        </td>
                        <td class="text-end text-success">
                            {{ $presupuesto->cobertura_seguro > 0 ? '−'.number_format($presupuesto->cobertura_seguro, 2) : '—' }}
                        </td>
                        <td class="text-end fw-medium">{{ number_format($presupuesto->total, 2) }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $presupuesto->color_estado }}-lt">{{ $presupuesto->estado_legible }}</span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.presupuestos.show', $presupuesto) }}" class="btn btn-sm btn-outline-secondary" title="Ver plan">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <a href="{{ route('admin.presupuestos.pdf', $presupuesto) }}" target="_blank"
                                   class="btn btn-sm btn-outline-danger" title="PDF">
                                    <i class="ti ti-file-type-pdf"></i>
                                </a>
                                @can('presupuestos.editar')
                                    @if ($presupuesto->es_editable)
                                        <a href="{{ route('admin.presupuestos.edit', $presupuesto) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </a>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <x-vacio icono="ti ti-file-off" titulo="Sin presupuestos"
                                     texto="Arma el primer plan de tratamiento desde el odontograma o desde cero." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($presupuestos->hasPages())
        <div class="card-footer">{{ $presupuestos->links() }}</div>
    @endif
</div>
@endsection
