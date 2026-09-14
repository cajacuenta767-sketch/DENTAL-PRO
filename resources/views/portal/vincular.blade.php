@extends('layouts.portal')

@section('pretitulo', 'Portal del paciente')
@section('titulo', 'Completa tu ficha')

@section('contenido')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="alert alert-info">
            <div class="d-flex">
                <div class="me-2"><i class="ti ti-info-circle fs-2"></i></div>
                <div>
                    <h4 class="alert-title">Aún no encontramos tu ficha de paciente</h4>
                    <div class="text-secondary">
                        Si ya eres paciente de {{ $ajustes->nombre ?? 'la clínica' }} y tu ficha tiene registrado
                        el mismo correo con el que iniciaste sesión (<strong>{{ $usuario->email }}</strong>),
                        se vinculará automáticamente al guardar. Si es tu primera vez, completa tus datos
                        y crearemos tu ficha.
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('portal.vincular.guardar') }}" novalidate>
            @csrf
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-user-plus me-2 text-primary"></i>Datos personales</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-campo nombre="nombres" etiqueta="Nombres" requerido>
                                <input type="text" id="nombres" name="nombres" class="form-control @error('nombres') is-invalid @enderror"
                                       value="{{ old('nombres') }}" maxlength="150" autocomplete="given-name" autofocus required>
                            </x-campo>
                        </div>
                        <div class="col-md-6">
                            <x-campo nombre="apellidos" etiqueta="Apellidos" requerido>
                                <input type="text" id="apellidos" name="apellidos" class="form-control @error('apellidos') is-invalid @enderror"
                                       value="{{ old('apellidos') }}" maxlength="150" autocomplete="family-name" required>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="tipo_documento" etiqueta="Tipo de documento" requerido>
                                <select id="tipo_documento" name="tipo_documento" class="form-select @error('tipo_documento') is-invalid @enderror" required>
                                    @foreach (['CI' => 'Cédula de identidad (CI)', 'DNI' => 'DNI', 'PASAPORTE' => 'Pasaporte', 'CE' => 'Carnet de extranjería (CE)'] as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('tipo_documento', 'CI') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-8">
                            <x-campo nombre="numero_documento" etiqueta="Número de documento" requerido
                                     ayuda="Con este número verificamos si ya existe una ficha a tu nombre.">
                                <input type="text" id="numero_documento" name="numero_documento"
                                       class="form-control @error('numero_documento') is-invalid @enderror"
                                       value="{{ old('numero_documento') }}" maxlength="20" required>
                            </x-campo>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <x-campo nombre="fecha_nacimiento" etiqueta="Fecha de nacimiento">
                                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento"
                                       class="form-control @error('fecha_nacimiento') is-invalid @enderror"
                                       value="{{ old('fecha_nacimiento') }}" max="{{ now()->subDay()->toDateString() }}">
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="genero" etiqueta="Género" requerido>
                                <select id="genero" name="genero" class="form-select @error('genero') is-invalid @enderror" required>
                                    <option value="" disabled @selected(old('genero') === null)>Selecciona…</option>
                                    @foreach (['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'] as $valor => $etiqueta)
                                        <option value="{{ $valor }}" @selected(old('genero') === $valor)>{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <div class="col-md-4">
                            <x-campo nombre="telefono" etiqueta="Teléfono">
                                <input type="tel" id="telefono" name="telefono" class="form-control @error('telefono') is-invalid @enderror"
                                       value="{{ old('telefono', $usuario->telefono) }}" maxlength="50" autocomplete="tel">
                            </x-campo>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" value="{{ $usuario->email }}" disabled>
                        <small class="form-hint">Se toma de tu cuenta verificada; puedes cambiarlo desde <a href="{{ route('perfil.edit') }}">Mi perfil</a>.</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <button type="submit" form="form-logout" class="btn btn-link text-secondary px-0">Cerrar sesión</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-link me-1"></i>Vincular mi ficha
                    </button>
                </div>
            </div>
        </form>

        <form id="form-logout" method="POST" action="{{ route('logout') }}" class="d-none">
            @csrf
        </form>
    </div>
</div>
@endsection
