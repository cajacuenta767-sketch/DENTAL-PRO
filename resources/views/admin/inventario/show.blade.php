@extends('layouts.admin')

@section('pretitulo', 'Operación')
@section('titulo', $insumo->nombre)
@section('subtitulo', $insumo->codigo)

@section('acciones')
    <div class="btn-list">
        <a href="{{ route('admin.inventario.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
        @can('inventario.editar')
            <a href="{{ route('admin.inventario.edit', $insumo) }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>Editar</a>
        @endcan
    </div>
@endsection

@section('contenido')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-secondary small text-uppercase">Existencias actuales</div>
                <div class="h1 mb-0 text-{{ $insumo->color_stock }}">
                    {{ (float) $insumo->stock_actual }}
                </div>
                <div class="text-secondary">{{ $insumo->unidad_medida }}</div>
                <div class="mt-2">
                    <span class="badge bg-{{ $insumo->color_stock }}-lt text-uppercase">{{ $insumo->nivel_stock }}</span>
                </div>
            </div>
            <div class="card-body border-top">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Mínimo</div>
                        <div class="datagrid-content">{{ (float) $insumo->stock_minimo }} {{ $insumo->unidad_medida }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Costo unitario</div>
                        <div class="datagrid-content">{{ number_format($insumo->costo_unitario, 2) }} {{ $ajustes->divisa }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Valorizado</div>
                        <div class="datagrid-content">{{ number_format($insumo->valorizado, 2) }} {{ $ajustes->divisa }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Consumo del mes</div>
                        <div class="datagrid-content">{{ $consumoMes }} {{ $insumo->unidad_medida }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Proveedor</div>
                        <div class="datagrid-content">{{ $insumo->proveedor ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Ubicación</div>
                        <div class="datagrid-content">{{ $insumo->ubicacion ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Vencimiento</div>
                        <div class="datagrid-content">
                            @if ($insumo->fecha_vencimiento)
                                <span class="{{ $insumo->esta_vencido ? 'text-danger' : '' }}">
                                    {{ $insumo->fecha_vencimiento->format('d/m/Y') }}
                                </span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('inventario.movimientos')
            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-arrows-exchange me-2"></i>Registrar movimiento</h3></div>
                <form method="POST" action="{{ route('admin.inventario.movimiento', $insumo) }}">
                    @csrf
                    <div class="card-body">
                        <x-campo nombre="tipo" etiqueta="Tipo de movimiento" requerido>
                            <select id="tipo" name="tipo" class="form-select" required>
                                <option value="ENTRADA">Entrada — compra o devolución</option>
                                <option value="SALIDA">Salida — consumo clínico</option>
                                <option value="AJUSTE">Ajuste — fija el saldo por inventario físico</option>
                                <option value="MERMA">Merma — vencimiento o rotura</option>
                            </select>
                        </x-campo>

                        <x-campo nombre="cantidad" etiqueta="Cantidad" requerido
                                 ayuda="En un ajuste, indica el saldo final contado.">
                            <div class="input-group">
                                <input type="number" id="cantidad" name="cantidad" class="form-control"
                                       step="0.01" min="0" required>
                                <span class="input-group-text">{{ $insumo->unidad_medida }}</span>
                            </div>
                        </x-campo>

                        <x-campo nombre="costo_unitario" etiqueta="Costo unitario" ayuda="En una entrada actualiza el costo del insumo.">
                            <div class="input-group">
                                <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                                <input type="number" id="costo_unitario" name="costo_unitario" class="form-control"
                                       step="0.01" min="0" value="{{ $insumo->costo_unitario }}">
                            </div>
                        </x-campo>

                        <x-campo nombre="referencia" etiqueta="Referencia" ayuda="N° de factura de compra o remisión.">
                            <input type="text" id="referencia" name="referencia" class="form-control">
                        </x-campo>

                        <x-campo nombre="motivo" etiqueta="Motivo">
                            <textarea id="motivo" name="motivo" class="form-control" rows="2"></textarea>
                        </x-campo>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary w-100"><i class="ti ti-plus me-1"></i>Registrar movimiento</button>
                    </div>
                </form>
            </div>
        @endcan
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ti ti-list-details me-2"></i>Kardex del insumo</h3>
                <span class="badge bg-secondary-lt ms-auto">{{ $movimientos->total() }} movimientos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Saldo</th>
                            <th>Motivo / referencia</th>
                            <th>Registró</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $movimiento)
                            <tr>
                                <td class="text-secondary small">{{ $movimiento->fecha->format('d/m/Y H:i') }}</td>
                                <td><span class="badge bg-{{ $movimiento->color }}-lt">{{ $movimiento->tipo }}</span></td>
                                <td class="text-end fw-medium text-{{ $movimiento->signo > 0 ? 'success' : 'danger' }}">
                                    {{ $movimiento->signo > 0 ? '+' : '−' }}{{ (float) $movimiento->cantidad }}
                                </td>
                                <td class="text-end">{{ (float) $movimiento->stock_resultante }}</td>
                                <td class="text-secondary small">
                                    {{ $movimiento->motivo ?: '—' }}
                                    @if ($movimiento->referencia)
                                        <div>Ref. {{ $movimiento->referencia }}</div>
                                    @endif
                                </td>
                                <td class="text-secondary small">{{ $movimiento->usuario?->nombre ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">Este insumo aún no tiene movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movimientos->hasPages())
                <div class="card-footer">{{ $movimientos->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
