@extends('layouts.admin')

@section('pretitulo', 'Catálogos Clínicos')
@section('titulo', 'Vademécum Farmacológico Odontológico')

@section('acciones')
    <div class="btn-list">
        @can('vademecum.crear')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-medicamento">
                <i class="ti ti-plus me-1"></i>Nuevo medicamento
            </button>
        @endcan
    </div>
@endsection

@section('contenido')
{{-- Filtros y buscador --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.vademecum.index') }}" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Principio activo, nombre comercial, concentración..." value="{{ request('q') }}">
                </div>
            </div>
            <div class="col-md-4">
                <select name="familia" class="form-select form-select-sm">
                    <option value="">— Todas las familias farmacológicas —</option>
                    @foreach ($familias as $clave => $meta)
                        <option value="{{ $clave }}" @selected(request('familia') === $clave)>{{ $meta['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ti ti-filter me-1"></i>Filtrar</button>
                @if (request()->hasAny(['q', 'familia']))
                    <a href="{{ route('admin.vademecum.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ti ti-x"></i></a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Listado de medicamentos --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr>
                    <th>Principio Activo y Comercial</th>
                    <th>Presentación / Concentración</th>
                    <th>Familia</th>
                    <th>Posología sugerida (Adultos)</th>
                    <th>Contraindicaciones y Alergias</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($medicamentos as $med)
                    <tr>
                        <td>
                            <div class="fw-bold fs-3 text-primary">{{ $med->principio_activo }}</div>
                            @if ($med->nombre_comercial)
                                <div class="text-secondary small">{{ $med->nombre_comercial }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt">{{ $med->concentracion }}</span>
                            <div class="text-secondary small mt-1">{{ $med->presentacion }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $med->color_familia }}-lt">
                                {{ $med->familia_legible }}
                            </span>
                        </td>
                        <td class="small" style="max-width: 250px;">
                            <div class="fw-semibold text-truncate">{{ $med->posologia_adulto ?: 'Consultar prospecto' }}</div>
                            @if ($med->posologia_pediatrica)
                                <div class="text-muted text-truncate"><small>Ped: {{ $med->posologia_pediatrica }}</small></div>
                            @endif
                        </td>
                        <td class="small" style="max-width: 260px;">
                            @if ($med->contraindicaciones)
                                <div class="text-danger"><i class="ti ti-alert-circle me-1"></i>{{ Str::limit($med->contraindicaciones, 70) }}</div>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('vademecum.editar')
                                    <button type="button" class="btn btn-sm btn-icon btn-ghost-primary"
                                            data-bs-toggle="modal" data-bs-target="#modal-editar-med-{{ $med->id }}" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                @endcan
                                @can('vademecum.eliminar')
                                    <form action="{{ route('admin.vademecum.destroy', $med) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar este fármaco del vademécum?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Eliminar">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>

                    {{-- Modal editar fármaco --}}
                    @can('vademecum.editar')
                    <div class="modal modal-blur fade" id="modal-editar-med-{{ $med->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <form action="{{ route('admin.vademecum.update', $med) }}" method="POST" class="modal-content">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Fármaco: {{ $med->principio_activo }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label required">Principio activo</label>
                                            <input type="text" name="principio_activo" class="form-control" value="{{ $med->principio_activo }}" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Nombre comercial de referencia</label>
                                            <input type="text" name="nombre_comercial" class="form-control" value="{{ $med->nombre_comercial }}">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required">Presentación</label>
                                            <input type="text" name="presentacion" class="form-control" value="{{ $med->presentacion }}" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required">Concentración</label>
                                            <input type="text" name="concentracion" class="form-control" value="{{ $med->concentracion }}" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label required">Familia farmacológica</label>
                                            <select name="familia" class="form-select" required>
                                                @foreach ($familias as $clave => $meta)
                                                    <option value="{{ $clave }}" @selected($med->familia === $clave)>{{ $meta['nombre'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Posología sugerida para adultos</label>
                                        <textarea name="posologia_adulto" class="form-control" rows="2">{{ $med->posologia_adulto }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Posología pediátrica</label>
                                        <textarea name="posologia_pediatrica" class="form-control" rows="2">{{ $med->posologia_pediatrica }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-danger">Contraindicaciones y Alergias cruzadas</label>
                                        <textarea name="contraindicaciones" class="form-control" rows="2">{{ $med->contraindicaciones }}</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Advertencias clínicas</label>
                                        <textarea name="advertencias" class="form-control" rows="2">{{ $med->advertencias }}</textarea>
                                    </div>
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" value="1" @checked($med->activo)>
                                        <span class="form-check-label">Medicamento activo en recetas</span>
                                    </label>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Actualizar fármaco</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endcan
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            No se encontraron medicamentos en el vademécum.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($medicamentos->hasPages())
        <div class="card-footer d-flex align-items-center">
            {{ $medicamentos->links() }}
        </div>
    @endif
</div>

{{-- Modal nuevo fármaco --}}
@can('vademecum.crear')
<div class="modal modal-blur fade" id="modal-nuevo-medicamento" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form action="{{ route('admin.vademecum.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-pill me-2"></i>Agregar Medicamento al Vademécum</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Principio activo</label>
                        <input type="text" name="principio_activo" class="form-control" placeholder="Ej: Amoxicilina" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre comercial de referencia</label>
                        <input type="text" name="nombre_comercial" class="form-control" placeholder="Ej: Clavulox, Amoxidal">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label required">Presentación</label>
                        <input type="text" name="presentacion" class="form-control" placeholder="Ej: Comprimidos, Cápsulas" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label required">Concentración</label>
                        <input type="text" name="concentracion" class="form-control" placeholder="Ej: 500 mg, 1 g" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label required">Familia farmacológica</label>
                        <select name="familia" class="form-select" required>
                            @foreach ($familias as $clave => $meta)
                                <option value="{{ $clave }}">{{ $meta['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Posología sugerida para adultos</label>
                    <textarea name="posologia_adulto" class="form-control" rows="2" placeholder="Ej: 1 comprimido cada 8 horas durante 7 días"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Posología pediátrica</label>
                    <textarea name="posologia_pediatrica" class="form-control" rows="2" placeholder="Ej: 50 mg/kg/día dividido cada 8 horas"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-danger">Contraindicaciones y Alergias cruzadas</label>
                    <textarea name="contraindicaciones" class="form-control" rows="2" placeholder="Ej: Hipersensibilidad a penicilinas, antecedentes de anafilaxia"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Advertencias clínicas</label>
                    <textarea name="advertencias" class="form-control" rows="2" placeholder="Ej: Ingerir junto con las comidas"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary ms-auto">
                    <i class="ti ti-check me-1"></i>Guardar medicamento
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
