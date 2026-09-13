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
                            <x-campo nombre="horas_recordatorio" etiqueta="Recordatorio (horas)" requerido ayuda="Anticipación del correo automático.">
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
@endsection
