@extends('layouts.admin')

@section('pretitulo', 'Catálogos')
@section('titulo', $aseguradora->exists ? 'Editar Aseguradora' : 'Nueva Aseguradora')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="{{ $aseguradora->exists ? route('admin.aseguradoras.update', $aseguradora) : route('admin.aseguradoras.store') }}">
            @csrf
            @if ($aseguradora->exists) @method('PUT') @endif

            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7">
                            <x-campo nombre="nombre" etiqueta="Nombre de la aseguradora" requerido>
                                <input type="text" id="nombre" name="nombre" class="form-control"
                                       value="{{ old('nombre', $aseguradora->nombre) }}" required>
                            </x-campo>
                        </div>
                        <div class="col-md-5">
                            <x-campo nombre="codigo" etiqueta="Código interno">
                                <input type="text" id="codigo" name="codigo" class="form-control"
                                       value="{{ old('codigo', $aseguradora->codigo) }}">
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="tipo" etiqueta="Tipo" requerido>
                                <select id="tipo" name="tipo" class="form-select" required>
                                    @foreach (\App\Models\Aseguradora::TIPOS as $tipo)
                                        <option value="{{ $tipo }}" @selected(old('tipo', $aseguradora->tipo) === $tipo)>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="porcentaje_cobertura" etiqueta="Cobertura" requerido
                                     ayuda="Se aplica automáticamente en los presupuestos.">
                                <div class="input-group">
                                    <input type="number" id="porcentaje_cobertura" name="porcentaje_cobertura" class="form-control"
                                           step="0.01" min="0" max="100"
                                           value="{{ old('porcentaje_cobertura', $aseguradora->porcentaje_cobertura) }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="tope_anual" etiqueta="Tope anual" ayuda="Déjalo vacío si no hay tope.">
                                <div class="input-group">
                                    <span class="input-group-text">{{ $ajustes->simbolo_divisa }}</span>
                                    <input type="number" id="tope_anual" name="tope_anual" class="form-control"
                                           step="0.01" min="0" value="{{ old('tope_anual', $aseguradora->tope_anual) }}">
                                </div>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="contacto" etiqueta="Persona de contacto">
                                <input type="text" id="contacto" name="contacto" class="form-control"
                                       value="{{ old('contacto', $aseguradora->contacto) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="text" id="telefono" name="telefono" class="form-control"
                                       value="{{ old('telefono', $aseguradora->telefono) }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="email" etiqueta="Correo">
                                <input type="email" id="email" name="email" class="form-control"
                                       value="{{ old('email', $aseguradora->email) }}">
                            </x-campo>
                        </div>
                    </div>

                    <x-campo nombre="observaciones" etiqueta="Observaciones del convenio">
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3">{{ old('observaciones', $aseguradora->observaciones) }}</textarea>
                    </x-campo>

                    <label class="form-check form-switch">
                        <input type="checkbox" name="activo" value="1" class="form-check-input"
                               {{ old('activo', $aseguradora->activo ?? true) ? 'checked' : '' }}>
                        <span class="form-check-label">Convenio vigente</span>
                    </label>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="{{ route('admin.aseguradoras.index') }}" class="btn btn-link">Cancelar</a>
                    <button type="submit" class="btn btn-primary ms-auto">
                        <i class="ti ti-device-floppy me-1"></i>Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
