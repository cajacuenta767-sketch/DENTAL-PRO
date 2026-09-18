@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Auditoría del Sistema')

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Eventos de hoy" :valor="$totales['hoy']" icono="ti ti-activity" color="primary" pie="registrados" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Inicios de sesión hoy" :valor="$totales['ingresos']" icono="ti ti-login" color="azure" pie="accesos correctos" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Accesos fallidos (24 h)" :valor="$totales['fallidos']" icono="ti ti-shield-x"
               :color="$totales['fallidos'] > 0 ? 'warning' : 'secondary'" pie="intentos rechazados" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Eliminaciones (7 días)" :valor="$totales['eliminaciones']" icono="ti ti-trash"
               :color="$totales['eliminaciones'] > 0 ? 'danger' : 'secondary'" pie="registros borrados" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-primary text-white">
        <h3 class="card-title"><i class="ti ti-filter me-2"></i>Filtros de Búsqueda</h3>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Texto de la descripción...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <select name="usuario_id" class="form-select">
                    <option value="">— Todos los usuarios —</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected(request('usuario_id') == $usuario->id)>{{ $usuario->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Acción</label>
                <select name="accion" class="form-select">
                    <option value="">— Todas —</option>
                    @foreach ($acciones as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('accion') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Registro</label>
                <select name="modelo" class="form-select">
                    <option value="">— Todos —</option>
                    @foreach ($modelos as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected(request('modelo') === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
                @error('desde')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
                @error('hasta')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-primary flex-fill"><i class="ti ti-search me-1"></i>Filtrar</button>
                <a href="{{ route('admin.auditoria.index') }}" class="btn btn-outline-secondary" title="Limpiar">
                    <i class="ti ti-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="ti ti-history me-2"></i>Registro de actividad</h3>
        <span class="badge bg-secondary-lt ms-auto">{{ $eventos->total() }} eventos</span>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 10rem;">Fecha</th>
                    <th>Usuario</th>
                    <th class="text-center">Acción</th>
                    <th>Registro</th>
                    <th>IP</th>
                    <th class="w-1">Cambios</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($eventos as $evento)
                    <tr>
                        <td>
                            <div>{{ $evento->created_at->format('d/m/Y') }}</div>
                            <div class="text-secondary small">{{ $evento->created_at->format('H:i:s') }}</div>
                        </td>
                        <td>
                            @if ($evento->usuario)
                                <div class="fw-medium">{{ $evento->usuario->nombre }}</div>
                            @else
                                <span class="text-secondary">Sistema / anónimo</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $evento->color }}-lt text-{{ $evento->color }}">{{ $evento->accion_legible }}</span>
                        </td>
                        <td>
                            @if ($evento->modelo)
                                <div class="fw-medium">
                                    {{ $evento->modelo_legible }}
                                    @if ($evento->modelo_id)<span class="text-secondary">#{{ $evento->modelo_id }}</span>@endif
                                </div>
                            @endif
                            @if ($evento->descripcion)
                                <div class="text-secondary small">{{ $evento->descripcion }}</div>
                            @elseif (! $evento->modelo)
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="font-monospace small text-secondary">{{ $evento->ip ?: '—' }}</td>
                        <td>
                            @if (! empty($evento->cambios))
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                                        data-bs-target="#cambios-{{ $evento->id }}" aria-expanded="false"
                                        title="Ver cambios">
                                    <i class="ti ti-list-details me-1"></i>{{ count($evento->cambios) }}
                                </button>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                    </tr>
                    @if (! empty($evento->cambios))
                        <tr class="collapse" id="cambios-{{ $evento->id }}">
                            <td colspan="6" class="p-0" style="background: var(--tblr-bg-surface-secondary);">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 25%;">Campo</th>
                                                <th>Antes</th>
                                                <th>Después</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($evento->cambios as $campo => $cambio)
                                                @php
                                                    $antes = is_array($cambio) ? ($cambio['antes'] ?? null) : null;
                                                    $despues = is_array($cambio) ? ($cambio['despues'] ?? $cambio) : $cambio;
                                                    $formatear = fn ($v) => is_null($v) ? '—' : (is_bool($v) ? ($v ? 'Sí' : 'No') : (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)));
                                                @endphp
                                                <tr>
                                                    <td class="font-monospace small">{{ $campo }}</td>
                                                    <td class="text-danger small" style="white-space: pre-wrap;">{{ $formatear($antes) }}</td>
                                                    <td class="text-success small" style="white-space: pre-wrap;">{{ $formatear($despues) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6">
                            <x-vacio icono="ti ti-history-off" titulo="Sin eventos"
                                     texto="Ningún evento de auditoría coincide con los filtros aplicados." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($eventos->hasPages())
        <div class="card-footer">{{ $eventos->links() }}</div>
    @endif
</div>
@endsection
