@extends('layouts.admin')

@section('pretitulo', 'Finanzas')
@section('titulo', $presupuesto->exists ? 'Editar Presupuesto '.$presupuesto->codigo : 'Nuevo Presupuesto')

@section('contenido')
<form method="POST" action="{{ $presupuesto->exists ? route('admin.presupuestos.update', $presupuesto) : route('admin.presupuestos.store') }}">
    @csrf
    @if ($presupuesto->exists) @method('PUT') @endif
    <input type="hidden" name="odontograma_id" value="{{ old('odontograma_id', $presupuesto->odontograma_id) }}">

    <div class="row g-3">
        <div class="col-lg-8">
            @if ($presupuesto->odontograma_id && ! $presupuesto->exists)
                <div class="alert alert-info">
                    <i class="ti ti-dental me-1"></i>
                    El plan se precargó con los hallazgos del odontograma. Ajusta, agrega o quita líneas antes de guardar.
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-list-check me-2"></i>Plan de tratamiento</h3>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-os-agregar-linea>
                        <i class="ti ti-plus me-1"></i>Agregar tratamiento
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="tabla-plan">
                        <thead>
                            <tr>
                                <th style="width: 26%;">Tratamiento</th>
                                <th>Descripción</th>
                                <th style="width: 6rem;">Pieza</th>
                                <th style="width: 8rem;">Cara</th>
                                <th style="width: 5rem;">Cant.</th>
                                <th style="width: 8rem;">Precio</th>
                                <th style="width: 8rem;" class="text-end">Subtotal</th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-end">Subtotal del plan</th>
                                <th class="text-end h3 mb-0" id="subtotal-plan">0.00</th>
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
                <div class="card-header"><h3 class="card-title">Datos del presupuesto</h3></div>
                <div class="card-body">
                    <x-campo nombre="paciente_id" etiqueta="Paciente" requerido>
                        <select id="paciente_id" name="paciente_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($pacientes as $paciente)
                                <option value="{{ $paciente->id }}"
                                        data-cobertura="{{ $paciente->aseguradora?->porcentaje_cobertura ?? 0 }}"
                                        data-aseguradora="{{ $paciente->aseguradora?->nombre ?? '' }}"
                                        @selected(old('paciente_id', $presupuesto->paciente_id) == $paciente->id)>
                                    {{ $paciente->nombre_completo }} · {{ $paciente->numero_documento }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="doctor_id" etiqueta="Doctor responsable">
                        <select id="doctor_id" name="doctor_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach ($doctores as $doctor)
                                <option value="{{ $doctor->id }}" @selected(old('doctor_id', $presupuesto->doctor_id) == $doctor->id)>
                                    {{ $doctor->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </x-campo>

                    <div class="row">
                        <div class="col-7">
                            <x-campo nombre="fecha" etiqueta="Fecha" requerido>
                                <input type="date" id="fecha" name="fecha" class="form-control"
                                       value="{{ old('fecha', $presupuesto->fecha?->format('Y-m-d') ?? now()->toDateString()) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-5">
                            <x-campo nombre="validez_dias" etiqueta="Validez" ayuda="Días.">
                                <input type="number" id="validez_dias" name="validez_dias" class="form-control"
                                       min="1" max="365" value="{{ old('validez_dias', $presupuesto->validez_dias ?? 30) }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="descuento" etiqueta="Descuento">
                        <div class="input-group">
                            <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                            <input type="number" id="descuento" name="descuento" class="form-control"
                                   step="0.01" min="0" value="{{ old('descuento', $presupuesto->descuento ?? 0) }}">
                        </div>
                    </x-campo>

                    <x-campo nombre="estado" etiqueta="Estado" requerido>
                        <select id="estado" name="estado" class="form-select" required>
                            @foreach (\App\Models\Presupuesto::ESTADOS as $clave => $etiqueta)
                                @if (in_array($clave, ['BORRADOR', 'PRESENTADO'], true) || $presupuesto->estado === $clave)
                                    <option value="{{ $clave }}" @selected(old('estado', $presupuesto->estado) === $clave)>{{ $etiqueta }}</option>
                                @endif
                            @endforeach
                        </select>
                    </x-campo>

                    <x-campo nombre="notas" etiqueta="Notas para el paciente">
                        <textarea id="notas" name="notas" class="form-control" rows="3">{{ old('notas', $presupuesto->notas) }}</textarea>
                    </x-campo>

                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Subtotal</span>
                            <span id="resumen-subtotal" class="fw-medium">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary">Descuento</span>
                            <span id="resumen-descuento" class="text-warning">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-secondary" id="etiqueta-cobertura">Cobertura del seguro</span>
                            <span id="resumen-cobertura" class="text-success">0.00</span>
                        </div>
                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-medium">Total estimado</span>
                            <span id="resumen-total" class="h3 mb-0 text-brand">0.00</span>
                        </div>
                        <div class="text-secondary small mt-2">
                            El importe definitivo se recalcula al guardar con la cobertura vigente.
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.presupuestos.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="plantilla-plan">
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
        <td><input type="text" class="form-control form-control-sm" data-campo="pieza_dental" maxlength="10" placeholder="16"></td>
        <td>
            <select class="form-select form-select-sm" data-campo="cara">
                <option value="">—</option>
                @foreach (\App\Models\Odontograma::CARAS as $cara)
                    <option value="{{ $cara }}">{{ ucfirst($cara) }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm" data-campo="cantidad" min="1" max="999" value="1" required></td>
        <td><input type="number" class="form-control form-control-sm" data-campo="precio_unitario" step="0.01" min="0" value="0" required></td>
        <td class="text-end fw-medium" data-subtotal>0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" data-os-quitar><i class="ti ti-trash"></i></button></td>
    </tr>
</template>

@push('scripts')
<script>
(() => {
    const cuerpo = document.querySelector('#tabla-plan tbody');
    const plantilla = document.getElementById('plantilla-plan');
    const descuento = document.getElementById('descuento');
    const paciente = document.getElementById('paciente_id');
    const previos = @json(old('detalles', $detallesPrevios));

    let indice = 0;

    const fmt = (n) => Number(n || 0).toFixed(2);

    function recalcular() {
        let subtotal = 0;

        cuerpo.querySelectorAll('[data-linea]').forEach((fila) => {
            const cantidad = Number(fila.querySelector('[data-campo="cantidad"]').value || 0);
            const precio = Number(fila.querySelector('[data-campo="precio_unitario"]').value || 0);
            const parcial = cantidad * precio;
            fila.querySelector('[data-subtotal]').textContent = fmt(parcial);
            subtotal += parcial;
        });

        const desc = Number(descuento.value || 0);
        const neto = Math.max(0, subtotal - desc);

        const opcion = paciente.selectedOptions[0];
        const porcentaje = Number(opcion?.dataset.cobertura || 0);
        const cubierto = Math.round(neto * porcentaje) / 100;

        document.getElementById('subtotal-plan').textContent = fmt(subtotal);
        document.getElementById('resumen-subtotal').textContent = fmt(subtotal);
        document.getElementById('resumen-descuento').textContent = '−' + fmt(desc);
        document.getElementById('resumen-cobertura').textContent = '−' + fmt(cubierto);
        document.getElementById('resumen-total').textContent = fmt(Math.max(0, neto - cubierto));

        const aseguradora = opcion?.dataset.aseguradora;
        document.getElementById('etiqueta-cobertura').textContent = aseguradora
            ? `Cobertura ${aseguradora} (${porcentaje}%)`
            : 'Cobertura del seguro';
    }

    function agregarLinea(datos = {}) {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        const n = indice++;

        fila.querySelectorAll('[data-campo]').forEach((campo) => {
            const nombre = campo.dataset.campo;
            campo.name = `detalles[${n}][${nombre}]`;
            if (datos[nombre] !== undefined && datos[nombre] !== null) campo.value = datos[nombre];
        });

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
    descuento.addEventListener('input', recalcular);
    paciente.addEventListener('change', recalcular);

    if (previos && Object.keys(previos).length) {
        Object.values(previos).forEach((linea) => agregarLinea(linea));
    } else {
        agregarLinea();
    }
})();
</script>
@endpush
@endsection
