@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Sucursales')
@section('subtitulo', 'Sedes de la clínica')

@section('acciones')
    @can('sucursales.crear')
        <a href="{{ route('admin.sucursales.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nueva Sede
        </a>
    @endcan
@endsection

@push('head')
<style>
    .os-sede-color { width: .75rem; height: .75rem; border-radius: 50%; display: inline-block; flex: none; }
</style>
@endpush

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Sedes registradas" :valor="$totales['sedes']" icono="ti ti-building-hospital" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Activas" :valor="$totales['activas']" icono="ti ti-circle-check" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Usuarios ligados a una sede" :valor="$totales['usuariosAtados']" icono="ti ti-users" color="azure" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Citas sin sede" :valor="$totales['citasSinSede']" icono="ti ti-calendar-question"
               :color="$totales['citasSinSede'] > 0 ? 'warning' : 'secondary'" />
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Listado de sedes</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre o código de la sede">
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select">
                    <option value="">— Todas —</option>
                    <option value="activa" @selected(request('estado') === 'activa')>Activas</option>
                    <option value="inactiva" @selected(request('estado') === 'inactiva')>Inactivas</option>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-search me-1"></i>Buscar</button>
                <a href="{{ route('admin.sucursales.index') }}" class="btn btn-outline-secondary" title="Limpiar"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Sede</th>
                    <th>Contacto</th>
                    <th class="text-center">Usuarios</th>
                    <th class="text-center">Horarios</th>
                    <th class="text-center">Citas</th>
                    <th class="text-center">Recibos</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sucursales as $sede)
                    <tr class="{{ $sede->activo ? '' : 'opacity-75' }}">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="os-sede-color" style="background-color: {{ $sede->color }}"></span>
                                <div>
                                    <div class="fw-medium">
                                        {{ $sede->nombre }}
                                        @if ($sede->principal)
                                            <span class="badge bg-primary-lt ms-1" title="Sede principal"><i class="ti ti-star me-1"></i>Principal</span>
                                        @endif
                                    </div>
                                    <div class="text-secondary small">
                                        <span class="font-monospace">{{ $sede->codigo }}</span>
                                        @if ($sede->direccion) · {{ $sede->direccion }} @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-secondary small">
                            @if ($sede->telefono)<div><i class="ti ti-phone me-1"></i>{{ $sede->telefono }}</div>@endif
                            @if ($sede->email)<div><i class="ti ti-mail me-1"></i>{{ $sede->email }}</div>@endif
                            @if (! $sede->telefono && ! $sede->email)—@endif
                        </td>
                        <td class="text-center">{{ $sede->usuarios_count }}</td>
                        <td class="text-center">{{ $sede->horarios_count }}</td>
                        <td class="text-center">{{ $sede->citas_count }}</td>
                        <td class="text-center">{{ $sede->pagos_count }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $sede->activo ? 'success' : 'secondary' }}-lt">
                                {{ $sede->activo ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('sucursales.editar')
                                    <a href="{{ route('admin.sucursales.edit', $sede) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('sucursales.eliminar')
                                    <form method="POST" action="{{ route('admin.sucursales.destroy', $sede) }}"
                                          data-confirmar="¿Eliminar la sede {{ $sede->nombre }}? Los usuarios, horarios e insumos ligados quedarán sin sede.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-vacio icono="ti ti-building-hospital" titulo="Sin sedes"
                                     texto="Registra las sedes de la clínica para etiquetar agenda, caja e inventario por sucursal." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($sucursales->hasPages())
        <div class="card-footer">{{ $sucursales->links() }}</div>
    @endif
</div>
@endsection
