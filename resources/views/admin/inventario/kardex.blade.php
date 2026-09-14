@extends('layouts.admin')

@section('pretitulo', 'Operación')
@section('titulo', 'Kardex General')

@section('acciones')
    <a href="{{ route('admin.inventario.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver al inventario</a>
@endsection

@section('contenido')
<div class="card">
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <select name="insumo_id" class="form-select">
                    <option value="">— Todos los insumos —</option>
                    @foreach ($insumos as $insumo)
                        <option value="{{ $insumo->id }}" @selected(request('insumo_id') == $insumo->id)>
                            {{ $insumo->codigo }} · {{ $insumo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="tipo" class="form-select">
                    <option value="">— Todos los tipos —</option>
                    @foreach (\App\Models\MovimientoInventario::TIPOS as $tipo)
                        <option value="{{ $tipo }}" @selected(request('tipo') === $tipo)>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-primary"><i class="ti ti-filter"></i></button>
                <a href="{{ route('admin.inventario.kardex') }}" class="btn btn-outline-secondary"><i class="ti ti-x"></i></a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Insumo</th>
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
                        <td>
                            <a href="{{ route('admin.inventario.show', $movimiento->insumo) }}" class="text-brand">
                                {{ $movimiento->insumo->nombre }}
                            </a>
                            <div class="text-secondary small">{{ $movimiento->insumo->codigo }}</div>
                        </td>
                        <td><span class="badge bg-{{ $movimiento->color }}-lt">{{ $movimiento->tipo }}</span></td>
                        <td class="text-end fw-medium text-{{ $movimiento->signo > 0 ? 'success' : 'danger' }}">
                            {{ $movimiento->signo > 0 ? '+' : '−' }}{{ $movimiento->cantidad_absoluta }}
                        </td>
                        <td class="text-end">{{ (float) $movimiento->stock_resultante }}</td>
                        <td class="text-secondary small">
                            {{ $movimiento->motivo ?: '—' }}
                            @if ($movimiento->referencia)<div>Ref. {{ $movimiento->referencia }}</div>@endif
                        </td>
                        <td class="text-secondary small">{{ $movimiento->usuario?->nombre ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-vacio icono="ti ti-list-search" titulo="Sin movimientos"
                                     texto="Ningún movimiento coincide con los filtros aplicados." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($movimientos->hasPages())
        <div class="card-footer">{{ $movimientos->links() }}</div>
    @endif
</div>
@endsection
