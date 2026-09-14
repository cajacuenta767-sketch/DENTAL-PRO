@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', 'Presupuesto '.$presupuesto->codigo)
@section('subtitulo', $presupuesto->paciente->nombre_completo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.presupuestos.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        <a href="{{ route('admin.presupuestos.pdf', $presupuesto) }}" target="_blank" class="btn btn-outline-danger">
            <i class="ti ti-file-type-pdf me-1"></i>PDF
        </a>
        @can('presupuestos.editar')
            @if ($presupuesto->es_editable)
                <a href="{{ route('admin.presupuestos.edit', $presupuesto) }}" class="btn btn-primary">
                    <i class="ti ti-edit me-1"></i>Editar
                </a>
            @endif
        @endcan
        @can('pagos.crear')
            @if (in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'], true))
                @if ($porCobrar > 0)
                    <form method="POST" action="{{ route('admin.presupuestos.facturar', $presupuesto) }}">
                        @csrf
                        <button class="btn btn-success">
                            <i class="ti ti-cash me-1"></i>Cobrar lo ejecutado
                            <span class="badge bg-white text-success ms-2">{{ number_format($porCobrar, 2) }} {{ $ajustes->divisa }}</span>
                        </button>
                    </form>
                @else
                    <button type="button" class="btn btn-success" disabled
                            title="No hay tratamientos ejecutados pendientes de cobro">
                        <i class="ti ti-cash me-1"></i>Cobrar lo ejecutado
                    </button>
                @endif
            @endif
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-8">
        {{-- Flujo del presupuesto --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-{{ $presupuesto->color_estado }} fs-4 px-3 py-2">
                        {{ $presupuesto->estado_legible }}
                    </span>
                    <div class="flex-fill px-3" style="min-width: 12rem;">
                        <div class="progress">
                            <div class="progress-bar bg-{{ $presupuesto->color_estado }}"
                                 style="width: {{ $presupuesto->avance }}%">{{ $presupuesto->avance }}%</div>
                        </div>
                    </div>
                    @can('presupuestos.aprobar')
                        @php $transiciones = \App\Models\Presupuesto::TRANSICIONES[$presupuesto->estado] ?? []; @endphp
                        @if (! empty($transiciones))
                            <form method="POST" action="{{ route('admin.presupuestos.estado', $presupuesto) }}" class="d-flex gap-2">
                                @csrf @method('PATCH')
                                <select name="estado" class="form-select form-select-sm" style="width: auto;" required>
                                    <option value="">— Cambiar a… —</option>
                                    @foreach (\App\Models\Presupuesto::ESTADOS as $clave => $etiqueta)
                                        @if ($presupuesto->puedeTransitarA($clave))
                                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-primary">Cambiar</button>
                            </form>
                        @else
                            <span class="text-secondary small" title="El estado avanza automáticamente al ejecutar los tratamientos">
                                <i class="ti ti-lock me-1"></i>Sin cambios manuales
                            </span>
                        @endif
                    @endcan
                </div>
            </div>
        </div>

        {{-- Plan de tratamiento --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-list-check me-2"></i>Plan de tratamiento</h3>
                <span class="badge bg-secondary-lt ms-auto">
                    {{ $presupuesto->detalles->where('estado', 'EJECUTADO')->count() }} de
                    {{ $presupuesto->detalles->where('estado', '!=', 'ANULADO')->count() }} ejecutados
                </span>
            </div>
            @php
                $puedeEjecutar = in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'], true);
                $puedeAgendar = in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION'], true);
                $puedeReasignar = auth()->user()->can('presupuestos.editar');
                $porSesion = $presupuesto->detalles->groupBy(fn ($d) => max(1, (int) $d->sesion))->sortKeys();
                $maxSesion = min(50, max(5, (int) $porSesion->keys()->max() + 1));
                $columnas = $puedeReasignar ? 8 : 7;
                $numeroLinea = 0;
            @endphp
            <div class="table-responsive">
                <table class="table table-vcenter card-table" id="tabla-plan-sesiones">
                    <thead>
                        <tr>
                            <th style="width: 3rem;">#</th>
                            <th>Tratamiento</th>
                            <th class="text-center">Pieza</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Subtotal</th>
                            @if ($puedeReasignar)
                                <th class="text-center" style="width: 6rem;" title="Cambiar la línea de sesión">Sesión</th>
                            @endif
                            <th class="text-center" style="width: 12rem;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porSesion as $sesion => $lineas)
                            @php
                                $vigentes = $lineas->where('estado', '!=', 'ANULADO');
                                $ejecutadas = $vigentes->where('estado', 'EJECUTADO')->count();
                                $pendientes = $vigentes->count() - $ejecutadas;
                                $avanceSesion = $vigentes->count() ? (int) round($ejecutadas / $vigentes->count() * 100) : 0;
                                $citaSesion = $lineas->pluck('cita')->filter()->sortByDesc('fecha')->first();
                            @endphp
                            <tr class="bg-surface-secondary" data-sesion="{{ $sesion }}">
                                <th colspan="{{ $columnas }}" class="py-2">
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <span class="badge bg-primary"><i class="ti ti-calendar-event me-1"></i>Sesión {{ $sesion }}</span>
                                        <span class="text-secondary small fw-normal">
                                            {{ $ejecutadas }} de {{ $vigentes->count() }} ejecutados ·
                                            {{ number_format($vigentes->sum('subtotal'), 2) }} {{ $ajustes->divisa }}
                                        </span>
                                        <div class="progress" style="width: 8rem; height: .5rem;" title="Avance de la sesión: {{ $avanceSesion }}%">
                                            <div class="progress-bar bg-{{ $avanceSesion === 100 ? 'success' : 'primary' }}" style="width: {{ $avanceSesion }}%"></div>
                                        </div>
                                        <span class="small fw-normal {{ $avanceSesion === 100 ? 'text-success' : 'text-secondary' }}">{{ $avanceSesion }}%</span>
                                        @if ($citaSesion)
                                            @can('citas.ver')
                                                <a href="{{ route('admin.citas.show', $citaSesion) }}" class="badge bg-azure-lt fw-normal" title="Cita vinculada a esta sesión">
                                                    <i class="ti ti-calendar-check me-1"></i>{{ $citaSesion->fecha->format('d/m/Y') }}
                                                </a>
                                            @else
                                                <span class="badge bg-azure-lt fw-normal"><i class="ti ti-calendar-check me-1"></i>{{ $citaSesion->fecha->format('d/m/Y') }}</span>
                                            @endcan
                                        @endif
                                        @can('citas.crear')
                                            @if ($puedeAgendar && $pendientes > 0)
                                                <a href="{{ route('admin.presupuestos.agendar-sesion', ['presupuesto' => $presupuesto, 'sesion' => $sesion]) }}"
                                                   class="btn btn-sm btn-outline-primary ms-auto" title="Abre la agenda con los datos de esta sesión">
                                                    <i class="ti ti-calendar-plus me-1"></i>Agendar sesión
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </th>
                            </tr>
                        @foreach ($lineas as $detalle)
                            @php($numeroLinea++)
                            <tr class="{{ $detalle->estado === 'ANULADO' ? 'opacity-50' : '' }}">
                                <td class="text-secondary">{{ $numeroLinea }}</td>
                                <td>
                                    <div class="fw-medium">{{ $detalle->descripcion }}</div>
                                    @if ($detalle->fecha_ejecucion)
                                        <div class="text-secondary small">
                                            Ejecutado el {{ $detalle->fecha_ejecucion->format('d/m/Y') }}
                                        </div>
                                    @endif
                                    @if ($detalle->pago_id)
                                        @can('pagos.ver')
                                            <a href="{{ route('admin.pagos.show', $detalle->pago_id) }}" class="badge bg-success-lt mt-1" title="Ver recibo">
                                                <i class="ti ti-check me-1"></i>Cobrado
                                            </a>
                                        @else
                                            <span class="badge bg-success-lt mt-1"><i class="ti ti-check me-1"></i>Cobrado</span>
                                        @endcan
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($detalle->pieza_dental)
                                        <span class="badge bg-azure-lt">{{ $detalle->pieza_dental }}</span>
                                        @if ($detalle->cara)
                                            <div class="text-secondary small">{{ $detalle->cara }}</div>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detalle->cantidad }}</td>
                                <td class="text-end">{{ number_format($detalle->precio_unitario, 2) }}</td>
                                <td class="text-end fw-medium">{{ number_format($detalle->subtotal, 2) }}</td>
                                @if ($puedeReasignar)
                                    <td class="text-center">
                                        <select class="form-select form-select-sm" data-sesion-linea="{{ $detalle->id }}"
                                                data-actual="{{ $sesion }}" aria-label="Sesión de la línea"
                                                @disabled($detalle->estado === 'ANULADO')>
                                            @for ($s = 1; $s <= $maxSesion; $s++)
                                                <option value="{{ $s }}" @selected($s === $sesion)>{{ $s }}</option>
                                            @endfor
                                        </select>
                                    </td>
                                @endif
                                <td class="text-center">
                                    @can('presupuestos.ejecutar')
                                        @if (in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'], true))
                                            <form method="POST" action="{{ route('admin.presupuestos.ejecutar', $detalle) }}">
                                                @csrf @method('PATCH')
                                                <select name="estado" class="form-select form-select-sm border-{{ $detalle->color_estado }}"
                                                        onchange="this.form.submit()">
                                                    @foreach (\App\Models\PresupuestoDetalle::ESTADOS as $estado)
                                                        <option value="{{ $estado }}" @selected($detalle->estado === $estado)>
                                                            {{ ucfirst(mb_strtolower(str_replace('_', ' ', $estado))) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="badge bg-{{ $detalle->color_estado }}-lt">{{ $detalle->estado }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-{{ $detalle->color_estado }}-lt">{{ $detalle->estado }}</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($puedeReasignar && $presupuesto->detalles->isNotEmpty())
                <div class="card-body border-top py-2 text-secondary small">
                    <i class="ti ti-info-circle me-1"></i>
                    Cambia el número de sesión de una línea para reorganizar el plan; el cambio se guarda al instante.
                </div>
            @endif

            @unless (in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'], true))
                <div class="card-body border-top">
                    <div class="alert alert-info mb-0">
                        <i class="ti ti-info-circle me-1"></i>
                        Aprueba el presupuesto para poder marcar tratamientos como ejecutados.
                    </div>
                </div>
            @endunless
        </div>

        @if ($presupuesto->notas)
            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Notas</h3></div>
                <div class="card-body" style="white-space: pre-line;">{{ $presupuesto->notas }}</div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Resumen económico</h3></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Subtotal</span>
                    <span class="fw-medium">{{ number_format($presupuesto->subtotal, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Descuento</span>
                    <span class="text-warning">−{{ number_format($presupuesto->descuento, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">
                        Cobertura del seguro
                        @if ($presupuesto->paciente->aseguradora)
                            <div class="small">
                                {{ $presupuesto->paciente->aseguradora->nombre }}
                                ({{ number_format($presupuesto->paciente->aseguradora->porcentaje_cobertura, 0) }}%)
                            </div>
                        @endif
                    </span>
                    <span class="text-success">−{{ number_format($presupuesto->cobertura_seguro, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between border-top pt-2">
                    <span class="fw-medium">Total a pagar</span>
                    <span class="h2 mb-0 text-brand">{{ number_format($presupuesto->total, 2) }}</span>
                </div>
                <div class="text-secondary small text-end">{{ $ajustes->divisa }}</div>
                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                    <span class="text-secondary">Ejecutado sin cobrar</span>
                    <span class="fw-medium {{ $porCobrar > 0 ? 'text-warning' : 'text-secondary' }}">
                        {{ number_format($porCobrar, 2) }} {{ $ajustes->divisa }}
                    </span>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h3 class="card-title">Datos del presupuesto</h3></div>
            <div class="card-body">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Paciente</div>
                        <div class="datagrid-content">
                            <a href="{{ route('admin.pacientes.show', $presupuesto->paciente) }}" class="text-brand">
                                {{ $presupuesto->paciente->nombre_completo }}
                            </a>
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Obra social</div>
                        <div class="datagrid-content">
                            {{ $presupuesto->paciente->aseguradora?->nombre ?? 'Particular' }}
                            @if ($presupuesto->paciente->numero_afiliado)
                                <div class="text-secondary small">Afiliado {{ $presupuesto->paciente->numero_afiliado }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Doctor</div>
                        <div class="datagrid-content">{{ $presupuesto->doctor?->nombre_profesional ?? '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Fecha</div>
                        <div class="datagrid-content">{{ $presupuesto->fecha->format('d/m/Y') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Válido hasta</div>
                        <div class="datagrid-content">
                            <span class="{{ $presupuesto->vence_el->isPast() ? 'text-danger' : '' }}">
                                {{ $presupuesto->vence_el->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                    @if ($presupuesto->odontograma)
                        <div class="datagrid-item">
                            <div class="datagrid-title">Odontograma de origen</div>
                            <div class="datagrid-content">
                                <a href="{{ route('admin.odontogramas.index', $presupuesto->paciente) }}" class="text-brand">
                                    {{ $presupuesto->odontograma->fecha->format('d/m/Y') }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if ($puedeReasignar)
@push('scripts')
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const url = @json(route('admin.presupuestos.sesiones', $presupuesto));

    document.querySelectorAll('[data-sesion-linea]').forEach((select) => {
        select.addEventListener('change', async () => {
            const anterior = select.dataset.actual;
            select.disabled = true;

            try {
                const respuesta = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ sesiones: { [select.dataset.sesionLinea]: Number(select.value) } }),
                });

                if (!respuesta.ok) throw new Error(`HTTP ${respuesta.status}`);

                // Se recarga para reagrupar las líneas por sesión.
                window.location.reload();
            } catch (error) {
                select.value = anterior;
                select.disabled = false;
                alert('No se pudo cambiar la sesión de la línea. Intenta de nuevo.');
            }
        });
    });
})();
</script>
@endpush
@endif
@endsection
