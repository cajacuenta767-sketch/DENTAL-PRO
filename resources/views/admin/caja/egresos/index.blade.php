@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', 'Egresos de Caja Chica')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.caja.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Cierre de caja
        </a>
        @can('caja.cerrar')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-egreso">
                <i class="ti ti-plus me-1"></i>Registrar egreso
            </button>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Tarjetas KPI --}}
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm border-danger">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="subheader text-danger">Egresos de hoy</div>
                </div>
                <div class="h2 mb-0 text-danger">${{ number_format($egresosHoy, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="subheader">Egresos del mes</div>
                </div>
                <div class="h2 mb-0">${{ number_format($egresosMes, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="subheader">Registros visibles</div>
                </div>
                <div class="h2 mb-0">{{ $egresos->total() }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Filtros --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.egresos.index') }}" class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde') }}" placeholder="Desde">
            </div>
            <div class="col-md-3">
                <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta') }}" placeholder="Hasta">
            </div>
            <div class="col-md-3">
                <select name="categoria" class="form-select form-select-sm">
                    <option value="">— Todas las categorías —</option>
                    @foreach ($categorias as $clave => $meta)
                        <option value="{{ $clave }}" @selected(request('categoria') === $clave)>{{ $meta['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">— Todos los estados —</option>
                    <option value="REGISTRADO" @selected(request('estado') === 'REGISTRADO')>Vigentes</option>
                    <option value="ANULADO" @selected(request('estado') === 'ANULADO')>Anulados</option>
                </select>
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100" title="Filtrar"><i class="ti ti-filter"></i></button>
                @if (request()->hasAny(['desde', 'hasta', 'categoria', 'estado']))
                    <a href="{{ route('admin.egresos.index') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar"><i class="ti ti-x"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Listado de egresos --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Categoría</th>
                    <th>Método</th>
                    <th>Comprobante</th>
                    <th>Usuario</th>
                    <th class="text-end">Monto</th>
                    <th>Estado</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($egresos as $egreso)
                    <tr class="{{ $egreso->estado === 'ANULADO' ? 'text-muted opacity-75 table-light' : '' }}">
                        <td>{{ $egreso->fecha->format('d/m/Y') }}</td>
                        <td>
                            <div class="fw-bold">{{ $egreso->concepto }}</div>
                            @if ($egreso->observaciones)
                                <div class="text-secondary small">{{ $egreso->observaciones }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $egreso->color_categoria }}-lt">
                                <i class="{{ $egreso->icono_categoria }} me-1"></i>{{ $egreso->categoria_legible }}
                            </span>
                        </td>
                        <td><span class="badge bg-secondary-lt">{{ $egreso->metodo_pago }}</span></td>
                        <td>
                            @if ($egreso->comprobante_numero)
                                <div>{{ $egreso->comprobante_tipo ?: 'Doc' }}: {{ $egreso->comprobante_numero }}</div>
                            @endif
                            @if ($egreso->comprobante_archivo)
                                <a href="{{ Storage::url($egreso->comprobante_archivo) }}" target="_blank" class="small text-primary">
                                    <i class="ti ti-file-download me-1"></i>Ver adjunto
                                </a>
                            @elseif(!$egreso->comprobante_numero)
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="small">{{ $egreso->usuario->nombre ?? 'Sistema' }}</td>
                        <td class="text-end fw-bold text-danger">
                            - ${{ number_format((float)$egreso->monto, 2) }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $egreso->estado === 'REGISTRADO' ? 'success' : 'danger' }}-lt">
                                {{ $egreso->estado }}
                            </span>
                        </td>
                        <td>
                            @if ($egreso->estado === 'REGISTRADO')
                                <div class="btn-list flex-nowrap">
                                    @can('caja.cerrar')
                                        <button type="button" class="btn btn-sm btn-icon btn-ghost-primary"
                                                data-bs-toggle="modal" data-bs-target="#modal-editar-egreso-{{ $egreso->id }}" title="Editar">
                                            <i class="ti ti-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-icon btn-ghost-danger"
                                                data-bs-toggle="modal" data-bs-target="#modal-anular-egreso-{{ $egreso->id }}" title="Anular">
                                            <i class="ti ti-ban"></i>
                                        </button>
                                    @endcan
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Modal editar egreso --}}
                    @can('caja.cerrar')
                    <div class="modal modal-blur fade" id="modal-editar-egreso-{{ $egreso->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <form action="{{ route('admin.egresos.update', $egreso) }}" method="POST" enctype="multipart/form-data" class="modal-content">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Egreso de Caja Chica</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required">Monto ($)</label>
                                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control" value="{{ $egreso->monto }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required">Fecha</label>
                                            <input type="date" name="fecha" class="form-control" value="{{ $egreso->fecha->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label required">Concepto</label>
                                        <input type="text" name="concepto" class="form-control" value="{{ $egreso->concepto }}" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required">Categoría</label>
                                            <select name="categoria" class="form-select" required>
                                                @foreach ($categorias as $k => $m)
                                                    <option value="{{ $k }}" @selected($egreso->categoria === $k)>{{ $m['nombre'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required">Método</label>
                                            <select name="metodo_pago" class="form-select" required>
                                                @foreach (\App\Models\EgresoCaja::METODOS as $met)
                                                    <option value="{{ $met }}" @selected($egreso->metodo_pago === $met)>{{ $met }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tipo comprobante</label>
                                            <input type="text" name="comprobante_tipo" class="form-control" value="{{ $egreso->comprobante_tipo }}" placeholder="Ej: Ticket, Factura">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">N° comprobante</label>
                                            <input type="text" name="comprobante_numero" class="form-control" value="{{ $egreso->comprobante_numero }}">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Archivo comprobante (reemplazar)</label>
                                        <input type="file" name="comprobante_archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Observaciones</label>
                                        <textarea name="observaciones" class="form-control" rows="2">{{ $egreso->observaciones }}</textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Actualizar egreso</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Modal anular egreso --}}
                    <div class="modal modal-blur fade" id="modal-anular-egreso-{{ $egreso->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
                            <form action="{{ route('admin.egresos.anular', $egreso) }}" method="POST" class="modal-content">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h5 class="modal-title">Anular Egreso</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="small text-secondary">¿Deseas anular el egreso por <strong>${{ number_format((float)$egreso->monto, 2) }}</strong> ({{ $egreso->concepto }})?</p>
                                    <div class="mb-3">
                                        <label class="form-label required">Motivo de anulación</label>
                                        <input type="text" name="motivo_anulacion" class="form-control" placeholder="Ej: Error en monto / duplicado" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger ms-auto">Confirmar anulación</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endcan
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-secondary">
                            No se encontraron egresos de caja chica registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($egresos->hasPages())
        <div class="card-footer d-flex align-items-center">
            {{ $egresos->links() }}
        </div>
    @endif
</div>

{{-- Modal nuevo egreso --}}
@can('caja.cerrar')
<div class="modal modal-blur fade" id="modal-nuevo-egreso" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('admin.egresos.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-receipt-refund me-2"></i>Registrar Egreso de Caja Chica</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Monto ($)</label>
                        <input type="number" step="0.01" min="0.01" name="monto" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">Concepto</label>
                    <input type="text" name="concepto" class="form-control" placeholder="Ej: Compra de agua mineral y café" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Categoría</label>
                        <select name="categoria" class="form-select" required>
                            @foreach ($categorias as $k => $m)
                                <option value="{{ $k }}">{{ $m['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Método</label>
                        <select name="metodo_pago" class="form-select" required>
                            @foreach (\App\Models\EgresoCaja::METODOS as $met)
                                <option value="{{ $met }}">{{ $met }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo comprobante</label>
                        <input type="text" name="comprobante_tipo" class="form-control" placeholder="Ej: Ticket, Recibo">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">N° comprobante</label>
                        <input type="text" name="comprobante_numero" class="form-control" placeholder="N°">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Foto o PDF del comprobante</label>
                    <input type="file" name="comprobante_archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="mb-3">
                    <label class="form-label">Observaciones adicionales</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Detalles u observaciones"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary ms-auto">
                    <i class="ti ti-check me-1"></i>Registrar egreso
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
