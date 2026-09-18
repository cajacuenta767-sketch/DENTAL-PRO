@extends('layouts.admin')

@section('pretitulo', 'Laboratorio')
@section('titulo', 'Catálogo de Laboratorios Dentales Asociados')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.laboratorio.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>Volver a órdenes
        </a>
        @can('laboratorio.crear')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-nuevo-lab">
                <i class="ti ti-plus me-1"></i>Nuevo laboratorio
            </button>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table">
            <thead>
                <tr>
                    <th>Laboratorio</th>
                    <th>Contacto</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Especialidades</th>
                    <th class="text-center">Órdenes</th>
                    <th>Estado</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($laboratorios as $lab)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $lab->nombre }}</div>
                            @if ($lab->direccion)
                                <div class="text-secondary small"><i class="ti ti-map-pin me-1"></i>{{ $lab->direccion }}</div>
                            @endif
                        </td>
                        <td>{{ $lab->contacto ?: '—' }}</td>
                        <td>
                            @if ($lab->telefono)
                                <a href="tel:{{ $lab->telefono }}" class="text-reset"><i class="ti ti-phone me-1"></i>{{ $lab->telefono }}</a>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($lab->email)
                                <a href="mailto:{{ $lab->email }}" class="text-reset"><i class="ti ti-mail me-1"></i>{{ $lab->email }}</a>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($lab->especialidades)
                                <span class="badge bg-blue-lt">{{ $lab->especialidades }}</span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary-lt">{{ $lab->ordenes_count }}</span>
                        </td>
                        <td>
                            <span class="badge bg-{{ $lab->activo ? 'success' : 'secondary' }}-lt">
                                {{ $lab->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('laboratorio.editar')
                                    <button type="button" class="btn btn-sm btn-icon btn-ghost-primary"
                                            data-bs-toggle="modal" data-bs-target="#modal-editar-lab-{{ $lab->id }}" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                @endcan
                                @can('laboratorio.eliminar')
                                    @if ($lab->ordenes_count === 0)
                                        <form action="{{ route('admin.laboratorio.catalogo.destroy', $lab) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar este laboratorio?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-ghost-danger" title="Eliminar">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>

                    {{-- Modal editar laboratorio --}}
                    @can('laboratorio.editar')
                    <div class="modal modal-blur fade" id="modal-editar-lab-{{ $lab->id }}" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <form action="{{ route('admin.laboratorio.catalogo.update', $lab) }}" method="POST" class="modal-content">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Laboratorio</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Nombre del laboratorio</label>
                                        <input type="text" name="nombre" class="form-control" value="{{ $lab->nombre }}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Persona de contacto / Protesista</label>
                                        <input type="text" name="contacto" class="form-control" value="{{ $lab->contacto }}">
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label class="form-label">Teléfono / WhatsApp</label>
                                            <input type="text" name="telefono" class="form-control" value="{{ $lab->telefono }}">
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label class="form-label">Correo electrónico</label>
                                            <input type="email" name="email" class="form-control" value="{{ $lab->email }}">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Dirección del taller</label>
                                        <input type="text" name="direccion" class="form-control" value="{{ $lab->direccion }}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Especialidades</label>
                                        <input type="text" name="especialidades" class="form-control" value="{{ $lab->especialidades }}"
                                               placeholder="Ej: Fija Zirconia, Removible, Ortodoncia">
                                    </div>
                                    <label class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="activo" value="1" @checked($lab->activo)>
                                        <span class="form-check-label">Laboratorio activo</span>
                                    </label>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary ms-auto">Guardar cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endcan
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-secondary">
                            No hay laboratorios protésicos registrados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal nuevo laboratorio --}}
@can('laboratorio.crear')
<div class="modal modal-blur fade" id="modal-nuevo-lab" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form action="{{ route('admin.laboratorio.catalogo.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-building-warehouse me-2"></i>Registrar Nuevo Laboratorio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required">Nombre del laboratorio</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Laboratorio Dental CeramiDent" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Persona de contacto / Protesista</label>
                    <input type="text" name="contacto" class="form-control" placeholder="Ej: TPD. Carlos Méndez">
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Teléfono / WhatsApp</label>
                        <input type="text" name="telefono" class="form-control" placeholder="+54 9 11 ...">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" placeholder="laboratorio@ejemplo.com">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dirección del taller</label>
                    <input type="text" name="direccion" class="form-control" placeholder="Calle, número, ciudad">
                </div>
                <div class="mb-3">
                    <label class="form-label">Especialidades</label>
                    <input type="text" name="especialidades" class="form-control" placeholder="Ej: Cerámica Zirconia, E.max, Prótesis Flexible, Alineadores">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary ms-auto">
                    <i class="ti ti-check me-1"></i>Registrar laboratorio
                </button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
