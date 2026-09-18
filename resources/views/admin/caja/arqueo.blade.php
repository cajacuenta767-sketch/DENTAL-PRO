@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', 'Arqueo de caja')
@section('subtitulo', $fecha->translatedFormat('l d \d\e F \d\e Y').($sucursal ? ' · '.$sucursal->nombre : ''))

@push('head')
<style>
    .arqueo-total { font-size: 1.4rem; font-weight: 600; }
    .arqueo-diferencia { font-size: 1.6rem; font-weight: 700; }
    .arqueo-diferencia.positiva { color: var(--tblr-success); }
    .arqueo-diferencia.negativa { color: var(--tblr-danger); }
</style>
@endpush

@section('acciones')
    <a href="{{ route('admin.caja.index') }}" class="btn btn-link"><i class="ti ti-arrow-left me-1"></i>Volver</a>
@endsection

@section('contenido')
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Día a arquear</label>
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" max="{{ now()->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Turno</label>
                <select name="turno" class="form-select">
                    @foreach ($turnos as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected($turno === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-primary"><i class="ti ti-refresh me-1"></i>Cargar resumen</button>
            </div>
        </form>
    </div>
</div>

@if ($cierreExistente)
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="ti ti-lock fs-2"></i>
        <div>
            La caja de este día (turno <strong>{{ $turnos[$cierreExistente->turno] ?? $cierreExistente->turno }}</strong>) ya fue cerrada
            @if ($cierreExistente->usuario) por {{ $cierreExistente->usuario->nombre }} @endif
            a las {{ $cierreExistente->cerrado_en?->format('H:i') }}.
            <a href="{{ route('admin.caja.show', $cierreExistente) }}" class="alert-link">Ver el cierre.</a>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="row row-cards mb-3">
            <div class="col-sm-4">
                <x-kpi titulo="Total cobrado" :valor="number_format($resumen['total'], 2).' '.$ajustes->divisa" icono="ti ti-coin" color="success" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Egresos caja chica" :valor="number_format($resumen['egresos_total'] ?? 0, 2).' '.$ajustes->divisa" icono="ti ti-arrow-down-right" color="danger"
                       :pie="(number_format($resumen['egresos_efectivo'] ?? 0, 2)).' en efectivo'" />
            </div>
            <div class="col-sm-4">
                <x-kpi titulo="Efectivo neto" :valor="number_format($resumen['efectivo_neto'] ?? $resumen['efectivo'], 2).' '.$ajustes->divisa" icono="ti ti-cash" color="azure"
                       :pie="'Cobros: '.number_format($resumen['efectivo'], 2)" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-credit-card me-2"></i>Cobrado por método de pago</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Método</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @foreach ($resumen['por_metodo'] as $metodo => $monto)
                            <tr>
                                <td><span class="badge bg-azure-lt">{{ $metodo }}</span></td>
                                <td class="text-end {{ $monto > 0 ? 'fw-medium' : 'text-secondary' }}" data-metodo="{{ $metodo }}">{{ number_format($monto, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th>Total Cobrado</th><th class="text-end">{{ number_format($resumen['total'], 2) }} {{ $ajustes->divisa }}</th></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="ti ti-user-dollar me-2"></i>Cobrado por cajero</h3></div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead><tr><th>Cajero</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($resumen['por_cajero'] as $cajero => $monto)
                            <tr><td>{{ $cajero }}</td><td class="text-end fw-medium">{{ number_format($monto, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-secondary py-4">Sin cobros registrados en el día.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="ti ti-cash-register me-2"></i>Cerrar caja</h3>
                <span class="badge bg-primary-lt">{{ $turnos[$turno] ?? $turno }}</span>
            </div>
            <form method="POST" action="{{ route('admin.caja.cerrar') }}">
                @csrf
                <input type="hidden" name="fecha" value="{{ $fecha->format('Y-m-d') }}">
                <input type="hidden" name="turno" value="{{ $turno }}">
                <div class="card-body">
                    <x-campo nombre="fondo_inicial" etiqueta="Fondo inicial" requerido
                             ayuda="Efectivo con el que abrió la caja. Se propone el fondo del cierre anterior.">
                        <input type="number" step="0.01" min="0" id="fondo_inicial" name="fondo_inicial" class="form-control"
                               value="{{ old('fondo_inicial', number_format($fondoSugerido, 2, '.', '')) }}" required {{ $cierreExistente ? 'disabled' : '' }}>
                    </x-campo>

                    <div class="card bg-muted-lt mb-3">
                        <div class="card-body py-2 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span>(+) Fondo inicial:</span>
                                <span id="desglose-fondo">0.00 {{ $ajustes->divisa }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>(+) Ingresos en efectivo:</span>
                                <span class="text-success">+{{ number_format($resumen['efectivo'], 2) }} {{ $ajustes->divisa }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>(-) Egresos en efectivo caja chica:</span>
                                <span class="text-danger">-{{ number_format($resumen['egresos_efectivo'] ?? 0, 2) }} {{ $ajustes->divisa }}</span>
                            </div>
                            <div class="border-top pt-1 d-flex justify-content-between fw-bold">
                                <span>(=) Efectivo esperado en caja:</span>
                                <span id="desglose-esperado">0.00 {{ $ajustes->divisa }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase">Efectivo total esperado a contar</div>
                        <div class="arqueo-total" id="arqueo-esperado" data-efectivo-neto="{{ $resumen['efectivo_neto'] ?? $resumen['efectivo'] }}">
                            {{ number_format($fondoSugerido + ($resumen['efectivo_neto'] ?? $resumen['efectivo']), 2) }} {{ $ajustes->divisa }}
                        </div>
                    </div>

                    <x-campo nombre="efectivo_contado" etiqueta="Efectivo contado" requerido ayuda="Lo que hay físicamente en el cajón de dinero.">
                        <input type="number" step="0.01" min="0" id="efectivo_contado" name="efectivo_contado" class="form-control form-control-lg"
                               value="{{ old('efectivo_contado') }}" required {{ $cierreExistente ? 'disabled' : '' }}>
                    </x-campo>

                    <div class="mb-3">
                        <div class="text-secondary small text-uppercase">Diferencia (Sobrante / Faltante)</div>
                        <div class="arqueo-diferencia" id="arqueo-diferencia">—</div>
                    </div>

                    <x-campo nombre="observaciones" etiqueta="Observaciones">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3" {{ $cierreExistente ? 'disabled' : '' }}>{{ old('observaciones') }}</textarea>
                    </x-campo>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary w-100" {{ $cierreExistente ? 'disabled' : '' }}
                            onclick="return confirm('¿Cerrar la caja del {{ $fecha->format('d/m/Y') }} turno {{ $turnos[$turno] ?? $turno }}? No podrás volver a cerrarla.')">
                        <i class="ti ti-lock me-1"></i>Cerrar caja ({{ $turnos[$turno] ?? $turno }})
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fondo = document.getElementById('fondo_inicial');
    const contado = document.getElementById('efectivo_contado');
    const esperadoEl = document.getElementById('arqueo-esperado');
    const diferenciaEl = document.getElementById('arqueo-diferencia');
    const desgloseFondo = document.getElementById('desglose-fondo');
    const desgloseEsperado = document.getElementById('desglose-esperado');
    const efectivoNeto = Number(esperadoEl.dataset.efectivoNeto || 0);
    const divisa = @json($ajustes->divisa);
    const fmt = (n) => n.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const recalcular = () => {
        const valFondo = Number(fondo.value || 0);
        const esperado = valFondo + efectivoNeto;
        
        if (desgloseFondo) desgloseFondo.textContent = `${fmt(valFondo)} ${divisa}`;
        if (desgloseEsperado) desgloseEsperado.textContent = `${fmt(esperado)} ${divisa}`;
        esperadoEl.textContent = `${fmt(esperado)} ${divisa}`;

        if (contado.value === '') { diferenciaEl.textContent = '—'; diferenciaEl.className = 'arqueo-diferencia'; return; }

        const diferencia = Number(contado.value) - esperado;
        diferenciaEl.textContent = `${diferencia > 0 ? '+' : ''}${fmt(diferencia)} ${divisa}`;
        diferenciaEl.className = 'arqueo-diferencia ' + (diferencia < -0.004 ? 'negativa' : (diferencia > 0.004 ? 'positiva' : ''));
    };

    fondo.addEventListener('input', recalcular);
    contado.addEventListener('input', recalcular);
    recalcular();
});
</script>
@endpush
@endsection
