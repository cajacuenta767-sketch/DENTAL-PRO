@extends('layouts.admin')

@section('pretitulo', 'Operación')
@section('titulo', 'Inventario de Insumos')

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.inventario.kardex') }}" class="btn btn-outline-secondary">
            <i class="ti ti-list-details me-1"></i>Kardex
        </a>
        @can('inventario.crear')
            <a href="{{ route('admin.inventario.create') }}" class="btn btn-primary">
                <i class="ti ti-plus me-1"></i>Nuevo Insumo
            </a>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row row-cards mb-3">
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Artículos activos" :valor="$resumen['articulos']" icono="ti ti-package" color="primary" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Inventario valorizado" :valor="number_format($resumen['valorizado'], 2).' '.$ajustes->divisa"
               icono="ti ti-coin" color="success" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Bajo el mínimo" :valor="$resumen['bajoMinimo']" icono="ti ti-alert-triangle"
               :color="$resumen['bajoMinimo'] > 0 ? 'danger' : 'secondary'" pie="requieren reposición" />
    </div>
    <div class="col-sm-6 col-xl-3">
        <x-kpi titulo="Por vencer (90 días)" :valor="$resumen['porVencer']" icono="ti ti-calendar-x"
               :color="$resumen['porVencer'] > 0 ? 'warning' : 'secondary'" />
    </div>
</div>

@if ($alertas->isNotEmpty())
    <div class="card mb-3 border-warning">
        <div class="card-header">
            <h3 class="card-title text-warning"><i class="ti ti-alert-triangle me-2"></i>Insumos que necesitan reposición</h3>
            <a href="{{ route('admin.inventario.index', ['alerta' => 'minimo']) }}" class="btn btn-sm btn-link ms-auto">Ver todos</a>
        </div>
        <div class="card-body">
            <div class="row g-2">
                @foreach ($alertas as $alerta)
                    <div class="col-md-6 col-xl-3">
                        <a href="{{ route('admin.inventario.show', $alerta) }}"
                           class="card card-sm text-decoration-none h-100">
                            <div class="card-body">
                                <div class="text-truncate fw-medium">{{ $alerta->nombre }}</div>
                                <div class="text-secondary small">
                                    Quedan <span class="text-{{ $alerta->color_stock }} fw-medium">{{ (float) $alerta->stock_actual }}</span>
                                    de {{ (float) $alerta->stock_minimo }} {{ $alerta->unidad_medida }}
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header"><h3 class="card-title">Catálogo de insumos</h3></div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Nombre, código o proveedor">
            </div>
            @if (($sucursales ?? collect())->count() > 1 && ! $sucursalActiva)
                <div class="col-md-2">
                    <select name="sucursal_id" class="form-select">
                        <option value="">— Todas las sedes —</option>
                        @foreach ($sucursales as $sede)
                            <option value="{{ $sede->id }}" @selected(request('sucursal_id') == $sede->id)>{{ $sede->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <select name="categoria" class="form-select">
                    <option value="">— Todas las categorías —</option>
                    @foreach (\App\Models\Insumo::CATEGORIAS as $categoria)
                        <option value="{{ $categoria }}" @selected(request('categoria') === $categoria)>{{ $categoria }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="alerta" class="form-select">
                    <option value="">— Sin filtro —</option>
                    <option value="minimo" @selected(request('alerta') === 'minimo')>Bajo mínimo</option>
                    <option value="vencer" @selected(request('alerta') === 'vencer')>Por vencer</option>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-search"></i></button>
                <a href="{{ route('admin.inventario.index') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Categoría</th>
                    @if ($sedes->count() > 1)
                        <th>Sede</th>
                    @endif
                    <th class="text-center">Existencias</th>
                    <th class="text-center">Mínimo</th>
                    <th class="text-end">Costo unit.</th>
                    <th class="text-end">Valorizado</th>
                    <th class="text-center">Vencimiento</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($insumos as $insumo)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $insumo->nombre }}</div>
                            <div class="text-secondary small">
                                {{ $insumo->codigo }}
                                @if ($insumo->proveedor) · {{ $insumo->proveedor }} @endif
                            </div>
                        </td>
                        <td><span class="badge bg-azure-lt">{{ $insumo->categoria }}</span></td>
                        @if ($sedes->count() > 1)
                            <td>
                                @if ($sede = $sedes->get($insumo->sucursal_id))
                                    <span class="badge" style="background-color: {{ $sede->color }}20; color: {{ $sede->color }}">{{ $sede->nombre }}</span>
                                @else
                                    <span class="text-secondary small">Todas</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-center">
                            <span class="badge bg-{{ $insumo->color_stock }}">
                                {{ (float) $insumo->stock_actual }} {{ $insumo->unidad_medida }}
                            </span>
                        </td>
                        <td class="text-center text-secondary">{{ (float) $insumo->stock_minimo }}</td>
                        <td class="text-end">{{ number_format($insumo->costo_unitario, 2) }}</td>
                        <td class="text-end fw-medium">{{ number_format($insumo->valorizado, 2) }}</td>
                        <td class="text-center">
                            @if ($insumo->fecha_vencimiento)
                                <span class="badge bg-{{ $insumo->esta_vencido ? 'danger' : 'secondary' }}-lt">
                                    {{ $insumo->fecha_vencimiento->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                <a href="{{ route('admin.inventario.show', $insumo) }}" class="btn btn-sm btn-outline-secondary" title="Ver kardex">
                                    <i class="ti ti-eye"></i>
                                </a>
                                @can('inventario.editar')
                                    <a href="{{ route('admin.inventario.edit', $insumo) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $sedes->count() > 1 ? 9 : 8 }}">
                            <x-vacio icono="ti ti-package-off" titulo="Sin insumos"
                                     texto="Registra el catálogo de insumos para controlar tus existencias." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($insumos->hasPages())
        <div class="card-footer">{{ $insumos->links() }}</div>
    @endif
</div>
@endsection
