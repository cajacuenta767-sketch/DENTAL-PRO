@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Pago en línea')

@push('head')
<style>
    .os-pasarela { border: 2px dashed var(--tblr-border-color); }
    .os-monto { font-size: 2.25rem; font-weight: 700; letter-spacing: -.02em; }
</style>
@endpush

@section('acciones')
    <a href="{{ route('portal.pagos') }}" class="btn btn-outline-secondary">
        <i class="ti ti-arrow-left me-1"></i>Mis pagos
    </a>
@endsection

@section('contenido')
@php
    $colores = ['PENDIENTE' => 'warning', 'PAGADO' => 'success', 'FALLIDO' => 'danger', 'CANCELADO' => 'secondary'];
    $iconos = ['PENDIENTE' => 'ti ti-clock', 'PAGADO' => 'ti ti-circle-check', 'FALLIDO' => 'ti ti-alert-circle', 'CANCELADO' => 'ti ti-circle-x'];
    $color = $colores[$intento->estado] ?? 'secondary';
    $simbolo = $ajustes->simbolo_divisa ?? '';
@endphp

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar avatar-xl bg-{{ $color }}-lt text-{{ $color }} mb-3">
                    <i class="{{ $iconos[$intento->estado] ?? 'ti ti-cash' }} fs-1"></i>
                </span>

                @if ($intento->estado === 'PAGADO')
                    <h2 class="mb-1">¡Pago recibido!</h2>
                    <p class="text-secondary">
                        Tu pago se acreditó al recibo <code>{{ $pago->codigo_recibo }}</code>
                        el {{ $intento->pagado_en?->format('d/m/Y H:i') }}. Gracias.
                    </p>
                @elseif ($intento->estado === 'PENDIENTE')
                    <h2 class="mb-1">Pago pendiente</h2>
                    <p class="text-secondary">
                        @if ($simulador)
                            Estás en la pasarela de pruebas: ningún cobro real se realiza aquí.
                        @else
                            Todavía no recibimos la confirmación de la pasarela. Si ya pagaste, esta página
                            se actualizará en unos minutos; puedes volver a consultarla desde <em>Mis pagos</em>.
                        @endif
                    </p>
                @elseif ($intento->estado === 'CANCELADO')
                    <h2 class="mb-1">Pago cancelado</h2>
                    <p class="text-secondary">No se realizó ningún cobro. Puedes intentarlo de nuevo cuando quieras.</p>
                @else
                    <h2 class="mb-1">El pago no se pudo completar</h2>
                    <p class="text-secondary">La pasarela rechazó la operación. No se realizó ningún cobro.</p>
                @endif

                <div class="os-monto text-{{ $color }}">{{ $simbolo }} {{ number_format((float) $intento->monto, 2) }}</div>
                <span class="badge bg-{{ $color }}">{{ ucfirst(mb_strtolower($intento->estado)) }}</span>
            </div>

            <div class="card-body border-top">
                <div class="datagrid">
                    <div class="datagrid-item">
                        <div class="datagrid-title">Recibo</div>
                        <div class="datagrid-content"><code>{{ $pago->codigo_recibo }}</code></div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Referencia</div>
                        <div class="datagrid-content">{{ $intento->referencia ?: '—' }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Proveedor</div>
                        <div class="datagrid-content">{{ ucfirst($intento->proveedor) }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Iniciado</div>
                        <div class="datagrid-content">{{ $intento->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Saldo actual del recibo</div>
                        <div class="datagrid-content {{ $pago->monto_saldo > 0 ? 'text-warning fw-bold' : 'text-success' }}">
                            {{ $simbolo }} {{ number_format((float) $pago->monto_saldo, 2) }}
                        </div>
                    </div>
                    <div class="datagrid-item">
                        <div class="datagrid-title">Estado del recibo</div>
                        <div class="datagrid-content">
                            <span class="badge bg-{{ $pago->color_estado }}">{{ ucfirst(mb_strtolower((string) $pago->estado)) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                @if ($intento->estado === 'PAGADO')
                    <a href="{{ route('portal.pagos.recibo', $pago) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">
                        <i class="ti ti-file-type-pdf me-1"></i>Descargar recibo
                    </a>
                @elseif ($intento->estado !== 'PENDIENTE' && $pago->monto_saldo > 0 && $ajustes->pagos_online_activos)
                    <form method="POST" action="{{ route('portal.pagos.pagar', $pago) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-credit-card me-1"></i>Intentar de nuevo
                        </button>
                    </form>
                @endif
                <a href="{{ route('portal.pagos') }}" class="btn">Volver a mis pagos</a>
            </div>
        </div>

        @if ($simulador)
            <div class="card os-pasarela">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-test-pipe me-2 text-warning"></i>Pasarela simulada</h3>
                    <span class="badge bg-warning-lt ms-auto">Entorno de pruebas</span>
                </div>
                <div class="card-body">
                    <p class="text-secondary">
                        Esta pantalla reemplaza a la pasarela real. Elige cómo termina la operación:
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ $simulador['exito'] }}" class="btn btn-success">
                            <i class="ti ti-credit-card me-1"></i>Pagar {{ $simbolo }} {{ number_format((float) $intento->monto, 2) }}
                        </a>
                        <a href="{{ $simulador['cancelacion'] }}" class="btn btn-outline-danger">
                            <i class="ti ti-x me-1"></i>Cancelar
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
