@extends('layouts.admin')

@section('pretitulo', 'Administración Financiera')
@section('titulo', $pago->exists ? 'Editar Recibo '.$pago->codigo_recibo : 'Registrar Nuevo Pago')

@section('contenido')
<form method="POST" action="{{ $pago->exists ? route('admin.pagos.update', $pago) : route('admin.pagos.store') }}" id="form-pago">
    @csrf
    @if ($pago->exists) @method('PUT') @endif

    <input type="hidden" name="cita_id" value="{{ old('cita_id', $pago->cita_id) }}">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-user me-2"></i>Datos del cobro</h3></div>
                <div class="card-body">
                    @if ($cita)
                        <div class="alert alert-info">
                            <i class="ti ti-info-circle me-1"></i>
                            Cobro asociado a la cita <strong>{{ $cita->token }}</strong>
                            del {{ $cita->fecha->format('d/m/Y') }} · {{ $cita->tratamiento->nombre }}.
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                                <x-selector-paciente nombre="paciente_id" :seleccionado="$pago->paciente_id" requerido />
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="doctor_id" etiqueta="Doctor responsable">
                                <select id="doctor_id" name="doctor_id" class="form-select">
                                    <option value="">— Sin asignar —</option>
                                    @foreach ($doctores as $doctor)
                                        <option value="{{ $doctor->id }}" @selected(old('doctor_id', $pago->doctor_id) == $doctor->id)>
                                            {{ $doctor->nombre_profesional }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-list-details me-2"></i>Detalle del recibo</h3>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-os-agregar-linea>
                        <i class="ti ti-plus me-1"></i>Agregar línea
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="tabla-detalles">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Tratamiento</th>
                                <th>Descripción</th>
                                <th style="width: 8rem;">Cantidad</th>
                                <th style="width: 10rem;">Precio unit.</th>
                                <th style="width: 9rem;" class="text-end">Subtotal</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total del recibo</th>
                                <th class="text-end h3 mb-0" id="total-recibo">0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @error('detalles')<div class="card-body text-danger">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-cash-register me-2"></i>Cobro</h3></div>
                <div class="card-body">
                    <x-campo nombre="metodo_pago" etiqueta="Método de pago" requerido>
                        <select id="metodo_pago" name="metodo_pago" class="form-select" required>
                            @foreach (\App\Models\Pago::METODOS as $metodo)
                                <option value="{{ $metodo }}" @selected(old('metodo_pago', $pago->metodo_pago) === $metodo)>{{ $metodo }}</option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="monto_pagado" etiqueta="Monto recibido" requerido
                             ayuda="Si es menor al total, el recibo queda parcial.">
                        <div class="input-group">
                            <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                            <input type="number" id="monto_pagado" name="monto_pagado" class="form-control"
                                   step="0.01" min="0" value="{{ old('monto_pagado', $pago->monto_pagado ?? 0) }}" required>
                        </div>
                    </x-campo>

                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-outline-success w-100" data-os-pago-total>
                            <i class="ti ti-check me-1"></i>Cobrar el total
                        </button>
                    </div>

                    @if (($sucursales ?? collect())->count() > 1)
                    <x-campo nombre="sucursal_id" etiqueta="Sede" ayuda="Sede donde se realiza el cobro.">
                        <select id="sucursal_id" name="sucursal_id" class="form-select">
                            <option value="">— Sin sede específica —</option>
                            @foreach ($sucursales as $sede)
                                <option value="{{ $sede->id }}" @selected(old('sucursal_id', $pago->sucursal_id) == $sede->id)>{{ $sede->nombre }}</option>
                            @endforeach
                        </select>
                    </x-campo>
                    @endif

                    <x-campo nombre="fecha_pago" etiqueta="Fecha y hora del pago" requerido>
                        <input type="datetime-local" id="fecha_pago" name="fecha_pago" class="form-control"
                               value="{{ old('fecha_pago', ($pago->fecha_pago ?? now())->format('Y-m-d\TH:i')) }}" required>
                    </x-campo>

                    <x-campo nombre="notas" etiqueta="Notas del recibo">
                        <textarea id="notas" name="notas" class="form-control" rows="3">{{ old('notas', $pago->notas) }}</textarea>
                    </x-campo>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Total</span>
                            <span id="resumen-total" class="fw-medium">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Pagado</span>
                            <span id="resumen-pagado" class="fw-medium text-success">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Saldo</span>
                            <span id="resumen-saldo" class="h3 mb-0 text-danger">0.00</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.pagos.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>{{ $pago->exists ? 'Guardar cambios' : 'Emitir recibo' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="plantilla-linea">
    <tr data-linea>
        <td>
            <select class="form-select form-select-sm" data-campo="tratamiento_id">
                <option value="">— Libre —</option>
                @foreach ($tratamientos as $tratamiento)
                    <option value="{{ $tratamiento->id }}" data-precio="{{ $tratamiento->precio }}"
                            data-nombre="{{ $tratamiento->nombre }}">
                        {{ $tratamiento->nombre }}
                    </option>
                @endforeach
            </select>
        </td>
        <td><input type="text" class="form-control form-control-sm" data-campo="descripcion" required></td>
        <td><input type="number" class="form-control form-control-sm" data-campo="cantidad" min="1" max="999" value="1" required></td>
        <td><input type="number" class="form-control form-control-sm" data-campo="precio_unitario" step="0.01" min="0" value="0" required></td>
        <td class="text-end fw-medium" data-subtotal>0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" data-os-quitar><i class="ti ti-trash"></i></button></td>
    </tr>
</template>

@push('scripts')
<script>
(() => {
    const cuerpo = document.querySelector('#tabla-detalles tbody');
    const plantilla = document.getElementById('plantilla-linea');
    const montoPagado = document.getElementById('monto_pagado');
    const previos = @json(old('detalles', $detallesPrevios));

    let indice = 0;

    function formatear(n) {
        return Number(n || 0).toFixed(2);
    }

    function recalcular() {
        let total = 0;

        cuerpo.querySelectorAll('[data-linea]').forEach((fila) => {
            const cantidad = Number(fila.querySelector('[data-campo="cantidad"]').value || 0);
            const precio = Number(fila.querySelector('[data-campo="precio_unitario"]').value || 0);
            const subtotal = cantidad * precio;

            fila.querySelector('[data-subtotal]').textContent = formatear(subtotal);
            total += subtotal;
        });

        const pagado = Number(montoPagado.value || 0);

        document.getElementById('total-recibo').textContent = formatear(total);
        document.getElementById('resumen-total').textContent = formatear(total);
        document.getElementById('resumen-pagado').textContent = formatear(pagado);
        document.getElementById('resumen-saldo').textContent = formatear(Math.max(0, total - pagado));

        cuerpo.dataset.total = total;
    }

    function agregarLinea(datos = {}) {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const n = indice++;

        fila.querySelectorAll('[data-campo]').forEach((campo) => {
            const nombre = campo.dataset.campo;
            campo.name = `detalles[${n}][${nombre}]`;
            if (datos[nombre] !== undefined && datos[nombre] !== null) campo.value = datos[nombre];
        });

        // Elegir un tratamiento rellena descripción y precio de catálogo.
        fila.querySelector('[data-campo="tratamiento_id"]').addEventListener('change', (e) => {
            const opcion = e.target.selectedOptions[0];
            if (!opcion.value) return;
            fila.querySelector('[data-campo="descripcion"]').value = opcion.dataset.nombre;
            fila.querySelector('[data-campo="precio_unitario"]').value = opcion.dataset.precio;
            recalcular();
        });

        fila.querySelectorAll('[data-campo="cantidad"], [data-campo="precio_unitario"]')
            .forEach((campo) => campo.addEventListener('input', recalcular));

        fila.querySelector('[data-os-quitar]').addEventListener('click', () => {
            fila.remove();
            if (!cuerpo.querySelector('[data-linea]')) agregarLinea();
            recalcular();
        });

        cuerpo.appendChild(fila);
        recalcular();
    }

    document.querySelector('[data-os-agregar-linea]').addEventListener('click', () => agregarLinea());
    montoPagado.addEventListener('input', recalcular);

    document.querySelector('[data-os-pago-total]').addEventListener('click', () => {
        montoPagado.value = formatear(cuerpo.dataset.total || 0);
        recalcular();
    });

    if (previos && Object.keys(previos).length) {
        Object.values(previos).forEach((linea) => agregarLinea(linea));
    } else {
        agregarLinea();
    }
})();
</script>
@endpush
@endsection
