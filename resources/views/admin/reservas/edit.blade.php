@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Turnos Online')

@section('acciones')
    <a href="{{ route('admin.ajustes.edit') }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i>Volver a Configuración
    </a>
@endsection

@section('contenido')
<form method="POST" action="{{ route('admin.reservas.update') }}">
    @csrf @method('PUT')

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-4">
                <label class="form-check form-switch form-switch-lg mb-0">
                    <input type="checkbox" name="reservas_online" value="1" class="form-check-input"
                           onchange="this.form.submit()"
                           {{ old('reservas_online', $ajuste->reservas_online) ? 'checked' : '' }}>
                    <span class="form-check-label fw-medium">Reservas en línea</span>
                </label>
                <span class="badge bg-{{ $ajuste->reservas_online ? 'success' : 'secondary' }} ms-auto text-uppercase">
                    {{ $ajuste->reservas_online ? 'Activo' : 'Inactivo' }}
                </span>
            </div>

            @if ($ajuste->reservas_online && $url)
                <div class="row g-3">
                    <div class="col-lg-4">
                        <div class="card card-sm h-100">
                            <div class="card-body text-center">
                                <div class="fw-medium mb-3">QR listo para mostrar</div>
                                <img src="{{ $qr }}" alt="Código QR de reservas" class="img-fluid border rounded p-2 bg-white"
                                     style="max-width: 240px;">
                                <div class="text-secondary small text-uppercase mt-3">Publicación activa</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card card-sm h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="fw-medium">URL pública</span>
                                    <i class="ti ti-qrcode ms-auto fs-3 text-secondary"></i>
                                </div>

                                <div class="input-group mb-3">
                                    <input type="text" class="form-control font-monospace" value="{{ $url }}" readonly
                                           id="url-reservas" style="font-size: .8rem;">
                                </div>

                                <div class="btn-list">
                                    <button type="button" class="btn btn-primary" data-os-copiar="#url-reservas">
                                        <i class="ti ti-copy me-1"></i>Copiar URL
                                    </button>
                                    <a href="{{ route('admin.reservas.qr') }}" class="btn">
                                        <i class="ti ti-download me-1"></i>Descargar QR
                                    </a>
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="btn">
                                        <i class="ti ti-external-link me-1"></i>Abrir página
                                    </a>
                                </div>

                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="ti ti-info-circle me-1"></i>
                                    Imprime el QR y colócalo en recepción o compártelo en tus redes.
                                    Las reservas entran como citas <strong>pendientes</strong> para que confirmes desde el panel.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <x-vacio icono="ti ti-qrcode" titulo="Reservas en línea desactivadas"
                         texto="Activa el interruptor para generar el enlace público y el código QR que compartirás con tus pacientes." />
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Reglas de la agenda pública</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="reservas_minimo_horas" etiqueta="Anticipación mínima" requerido
                                     ayuda="Horas que deben faltar para que un cupo sea reservable.">
                                <div class="input-group">
                                    <input type="number" id="reservas_minimo_horas" name="reservas_minimo_horas"
                                           class="form-control" min="0" max="168"
                                           value="{{ old('reservas_minimo_horas', $ajuste->reservas_minimo_horas) }}" required>
                                    <span class="input-group-text">horas</span>
                                </div>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="reservas_anticipacion_dias" etiqueta="Anticipación máxima" requerido
                                     ayuda="Hasta cuántos días adelante se puede reservar.">
                                <div class="input-group">
                                    <input type="number" id="reservas_anticipacion_dias" name="reservas_anticipacion_dias"
                                           class="form-control" min="1" max="180"
                                           value="{{ old('reservas_anticipacion_dias', $ajuste->reservas_anticipacion_dias) }}" required>
                                    <span class="input-group-text">días</span>
                                </div>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="reservas_mensaje" etiqueta="Mensaje para el paciente"
                             ayuda="Se muestra en la página pública antes del formulario.">
                        <textarea id="reservas_mensaje" name="reservas_mensaje" class="form-control" rows="3">{{ old('reservas_mensaje', $ajuste->reservas_mensaje) }}</textarea>
                    </x-campo>
                </div>
                <div class="card-footer d-flex gap-2">
                    @if ($ajuste->reservas_token)
                        <button type="submit" form="form-regenerar" class="btn btn-outline-danger"
                                onclick="return confirm('Se generará un enlace nuevo y el QR actual dejará de funcionar. ¿Continuar?')">
                            <i class="ti ti-refresh me-1"></i>Regenerar enlace
                        </button>
                    @endif
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar reglas
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-world me-2"></i>Reservas recibidas</h3>
                    <span class="badge bg-primary-lt ms-auto">{{ $reservasRecibidas }} en total</span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($ultimasReservas as $cita)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <div class="flex-fill">
                                    <div class="fw-medium">{{ $cita->paciente->nombre_completo }}</div>
                                    <div class="text-secondary small">
                                        {{ $cita->fecha_hora }} · {{ $cita->tratamiento->nombre }}
                                    </div>
                                </div>
                                <span class="badge bg-{{ $cita->color_estado }}-lt">{{ $cita->estado_legible }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary text-center py-4">
                            Todavía no has recibido reservas desde el enlace público.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</form>

@if ($ajuste->reservas_token)
    <form method="POST" action="{{ route('admin.reservas.regenerar') }}" id="form-regenerar" class="d-none">
        @csrf
    </form>
@endif

@push('scripts')
<script>
document.querySelectorAll('[data-os-copiar]').forEach((boton) => {
    boton.addEventListener('click', async () => {
        const campo = document.querySelector(boton.dataset.osCopiar);
        const original = boton.innerHTML;

        try {
            await navigator.clipboard.writeText(campo.value);
        } catch {
            // Navegadores sin portapapeles: seleccionamos para copiar a mano.
            campo.select();
            document.execCommand('copy');
        }

        boton.innerHTML = '<i class="ti ti-check me-1"></i>Copiado';
        setTimeout(() => { boton.innerHTML = original; }, 2000);
    });
});
</script>
@endpush
@endsection
