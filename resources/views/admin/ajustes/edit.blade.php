@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Ajustes de la Clínica')

@section('contenido')
<form method="POST" action="{{ route('admin.ajustes.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-building-hospital me-2"></i>Datos generales</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <x-campo nombre="nombre" etiqueta="Nombre de la clínica" requerido>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="{{ old('nombre', $ajuste->nombre) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="nit" etiqueta="NIT / RUC">
                                <input type="text" id="nit" name="nit" class="form-control" value="{{ old('nit', $ajuste->nit) }}">
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="descripcion" etiqueta="Lema o descripción breve">
                        <input type="text" id="descripcion" name="descripcion" class="form-control"
                               value="{{ old('descripcion', $ajuste->descripcion) }}">
                    </x-campo>

                    <x-campo nombre="direccion" etiqueta="Dirección">
                        <textarea id="direccion" name="direccion" class="form-control" rows="2">{{ old('direccion', $ajuste->direccion) }}</textarea>
                    </x-campo>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control" value="{{ old('telefono', $ajuste->telefono) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="whatsapp" etiqueta="WhatsApp">
                                <input type="text" id="whatsapp" name="whatsapp" class="form-control" value="{{ old('whatsapp', $ajuste->whatsapp) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="email" etiqueta="Correo de contacto">
                                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $ajuste->email) }}">
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="web" etiqueta="Sitio web">
                                <input type="url" id="web" name="web" class="form-control" placeholder="https://" value="{{ old('web', $ajuste->web) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="facebook" etiqueta="Facebook">
                                <input type="url" id="facebook" name="facebook" class="form-control" placeholder="https://" value="{{ old('facebook', $ajuste->facebook) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="instagram" etiqueta="Instagram">
                                <input type="url" id="instagram" name="instagram" class="form-control" placeholder="https://" value="{{ old('instagram', $ajuste->instagram) }}">
                            </x-campo>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-adjustments me-2"></i>Parámetros de operación</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <x-campo nombre="divisa" etiqueta="Divisa" requerido>
                                <input type="text" id="divisa" name="divisa" class="form-control" value="{{ old('divisa', $ajuste->divisa) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="simbolo_divisa" etiqueta="Símbolo" requerido>
                                <input type="text" id="simbolo_divisa" name="simbolo_divisa" class="form-control"
                                       value="{{ old('simbolo_divisa', $ajuste->simbolo_divisa) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="minutos_intervalo_cita" etiqueta="Intervalo de cita" requerido ayuda="Minutos entre cupos de la agenda.">
                                <input type="number" id="minutos_intervalo_cita" name="minutos_intervalo_cita" class="form-control"
                                       min="5" max="180" step="5" value="{{ old('minutos_intervalo_cita', $ajuste->minutos_intervalo_cita) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-3">
                            <x-campo nombre="horas_recordatorio" etiqueta="Recordatorio (horas)" requerido ayuda="Anticipación del aviso automático.">
                                <input type="number" id="horas_recordatorio" name="horas_recordatorio" class="form-control"
                                       min="1" max="168" value="{{ old('horas_recordatorio', $ajuste->horas_recordatorio) }}" required>
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="terminos_recibo" etiqueta="Texto al pie del recibo" ayuda="Aparece impreso en cada comprobante de pago.">
                        <textarea id="terminos_recibo" name="terminos_recibo" class="form-control" rows="3">{{ old('terminos_recibo', $ajuste->terminos_recibo) }}</textarea>
                    </x-campo>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-bell-ringing me-2"></i>Recordatorios y portal</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="recordatorio_canal" etiqueta="Canal del recordatorio" requerido
                                     ayuda="WhatsApp y SMS requieren configurar MENSAJERIA_PROVEEDOR en el servidor.">
                                <select id="recordatorio_canal" name="recordatorio_canal" class="form-select" required>
                                    @foreach ($canales as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('recordatorio_canal', $ajuste->recordatorio_canal ?? 'correo') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Proveedor de mensajería</label>
                                <div class="form-control-plaintext">
                                    <span class="badge bg-{{ $proveedorMensajeria === 'log' ? 'secondary' : 'success' }}-lt text-uppercase">{{ $proveedorMensajeria }}</span>
                                    <small class="text-secondary ms-2">
                                        @if ($proveedorMensajeria === 'log')
                                            Los mensajes solo se escriben en el log (modo desarrollo).
                                        @elseif ($proveedorMensajeria === 'twilio')
                                            Twilio: envía SMS y WhatsApp.
                                        @else
                                            WhatsApp Cloud API de Meta: solo WhatsApp (sin SMS).
                                        @endif
                                    </small>
                                </div>
                                <small class="form-hint">Se define con la variable <code>MENSAJERIA_PROVEEDOR</code> del archivo <code>.env</code>.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-check form-switch mb-2">
                                <input type="checkbox" name="pagos_online_activos" value="1" class="form-check-input"
                                       {{ old('pagos_online_activos', $ajuste->pagos_online_activos) ? 'checked' : '' }}>
                                <span class="form-check-label">Pagos en línea desde el portal</span>
                                <span class="form-check-description">Permite al paciente pagar sus citas desde el enlace público.</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-check form-switch mb-2">
                                <input type="checkbox" name="portal_reservas_activas" value="1" class="form-check-input"
                                       {{ old('portal_reservas_activas', $ajuste->portal_reservas_activas) ? 'checked' : '' }}>
                                <span class="form-check-label">Reservas desde el portal</span>
                                <span class="form-check-description">Habilita la reserva de citas desde el portal del paciente.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-photo me-2"></i>Logotipo</h3></div>
                <div class="card-body text-center">
                    @if ($ajuste->logo)
                        <img src="{{ Storage::url($ajuste->logo) }}" alt="Logo" class="img-fluid mb-3" style="max-height: 120px;">
                    @else
                        <div class="avatar avatar-xl bg-brand mb-3"><i class="ti ti-dental fs-1"></i></div>
                    @endif
                    <x-campo nombre="logo_archivo" etiqueta="Reemplazar logotipo" ayuda="PNG o JPG, máximo 2 MB.">
                        <input type="file" id="logo_archivo" name="logo_archivo" class="form-control" accept="image/*">
                    </x-campo>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-device-floppy me-1"></i>Guardar ajustes
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@if (Route::has('admin.ajustes.mensajeria.probar'))
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ti ti-send me-2"></i>Probar envío de mensajes</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ajustes.mensajeria.probar') }}">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-5">
                                <x-campo nombre="numero" etiqueta="Número de destino" requerido ayuda="Con o sin prefijo de país; los números de 8 dígitos usan el prefijo configurado.">
                                    <input type="text" id="numero" name="numero" class="form-control" placeholder="70000000"
                                           value="{{ old('numero', $ajuste->whatsapp) }}" required>
                                </x-campo>
                            </div>
                            <div class="col-md-3">
                                <x-campo nombre="canal" etiqueta="Canal" requerido>
                                    <select id="canal" name="canal" class="form-select" required>
                                        <option value="whatsapp" @selected(old('canal', 'whatsapp') === 'whatsapp')>WhatsApp</option>
                                        <option value="sms" @selected(old('canal') === 'sms')>SMS</option>
                                    </select>
                                </x-campo>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="ti ti-send me-1"></i>Enviar mensaje de prueba
                                    </button>
                                </div>
                            </div>
                        </div>
                        <small class="form-hint">Se envía con el proveedor <strong>{{ $proveedorMensajeria }}</strong>; con el proveedor <code>log</code> el mensaje solo aparece en <code>storage/logs</code>.</small>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
