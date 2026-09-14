@extends('layouts.admin')

@section('pretitulo', 'Operación')
@section('titulo', $insumo->exists ? 'Editar Insumo' : 'Nuevo Insumo')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <form method="POST" action="{{ $insumo->exists ? route('admin.inventario.update', $insumo) : route('admin.inventario.store') }}">
            @csrf
            @if ($insumo->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <x-campo nombre="codigo" etiqueta="Código" requerido>
                                <input type="text" id="codigo" name="codigo" class="form-control"
                                       value="{{ old('codigo', $insumo->codigo) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="nombre" etiqueta="Nombre del insumo" requerido>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="{{ old('nombre', $insumo->nombre) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="categoria" etiqueta="Categoría" requerido>
                                <select id="categoria" name="categoria" class="form-select" required>
                                    @foreach (\App\Models\Insumo::CATEGORIAS as $categoria)
                                        <option value="{{ $categoria }}" @selected(old('categoria', $insumo->categoria) === $categoria)>{{ $categoria }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>

                    @if (($sucursales ?? collect())->count() > 1)
                    <x-campo nombre="sucursal_id" etiqueta="Sede" ayuda="Vacío = insumo común a todas las sedes.">
                        <select id="sucursal_id" name="sucursal_id" class="form-select">
                            <option value="">— Todas las sedes —</option>
                            @foreach ($sucursales as $sede)
                                <option value="{{ $sede->id }}" @selected(old('sucursal_id', $insumo->sucursal_id) == $sede->id)>{{ $sede->nombre }}</option>
                            @endforeach
                        </select>
                    </x-campo>
                    @endif

                    <x-campo nombre="descripcion" etiqueta="Descripción">
                        <input type="text" id="descripcion" name="descripcion" class="form-control"
                               value="{{ old('descripcion', $insumo->descripcion) }}">
                    </x-campo>

                    <div class="row">
                        <div class="col-md-3">
                            <x-campo nombre="unidad_medida" etiqueta="Unidad" requerido>
                                <select id="unidad_medida" name="unidad_medida" class="form-select" required>
                                    @foreach (\App\Models\Insumo::UNIDADES as $unidad)
                                        <option value="{{ $unidad }}" @selected(old('unidad_medida', $insumo->unidad_medida) === $unidad)>{{ $unidad }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="stock_actual" etiqueta="Existencias iniciales"
                                     :ayuda="$insumo->exists ? 'Solo cambian mediante movimientos.' : 'Se registra como la primera entrada del kardex.'">
                                <input type="number" id="stock_actual" name="stock_actual" class="form-control"
                                       step="0.01" min="0" value="{{ old('stock_actual', $insumo->stock_actual) }}"
                                       {{ $insumo->exists ? 'readonly' : '' }}>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="stock_minimo" etiqueta="Existencias mínimas" requerido
                                     ayuda="Dispara la alerta de reposición.">
                                <input type="number" id="stock_minimo" name="stock_minimo" class="form-control"
                                       step="0.01" min="0" value="{{ old('stock_minimo', $insumo->stock_minimo) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="costo_unitario" etiqueta="Costo unitario" requerido>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                                    <input type="number" id="costo_unitario" name="costo_unitario" class="form-control"
                                           step="0.01" min="0" value="{{ old('costo_unitario', $insumo->costo_unitario) }}" required>
                                </div>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="proveedor" etiqueta="Proveedor">
                                <input type="text" id="proveedor" name="proveedor" class="form-control"
                                       value="{{ old('proveedor', $insumo->proveedor) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="ubicacion" etiqueta="Ubicación física" ayuda="Estante, gaveta o refrigerador.">
                                <input type="text" id="ubicacion" name="ubicacion" class="form-control"
                                       value="{{ old('ubicacion', $insumo->ubicacion) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="fecha_vencimiento" etiqueta="Fecha de vencimiento">
                                <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" class="form-control"
                                       value="{{ old('fecha_vencimiento', $insumo->fecha_vencimiento?->format('Y-m-d')) }}">
                            </x-campo>
                        </div>
                    </div>

                    <label class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" class="form-check-input"
                               {{ old('activo', $insumo->activo ?? true) ? 'checked' : '' }}>
                        <span class="form-check-label">Insumo activo</span>
                    </label>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.inventario.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
