@extends('layouts.admin')

@section('pretitulo', 'Bioseguridad y Esterilización')
@section('titulo', 'Nuevo Ciclo de Esterilización')
@section('subtitulo', 'Registro obligatorio de parámetros físicos y controles biológicos de autoclave')

@section('acciones')
    <a href="{{ route('admin.esterilizacion.index') }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i>Volver al listado
    </a>
@endsection

@section('contenido')
<form action="{{ route('admin.esterilizacion.store') }}" method="POST">
    @csrf
    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-cpu me-2"></i>Parámetros del Ciclo de Autoclave</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label required">Autoclave / Esterilizador</label>
                            <input type="text" name="autoclave_nombre" value="{{ old('autoclave_nombre', 'Autoclave Clase B - Principal') }}" class="form-control @error('autoclave_nombre') is-invalid @enderror" required>
                            @error('autoclave_nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Número de Ciclo</label>
                            <input type="number" name="numero_ciclo" value="{{ old('numero_ciclo', $ciclo->numero_ciclo) }}" min="1" class="form-control @error('numero_ciclo') is-invalid @enderror" required>
                            @error('numero_ciclo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Fecha del Ciclo</label>
                            <input type="date" name="fecha" value="{{ old('fecha', $ciclo->fecha ? $ciclo->fecha->format('Y-m-d') : now()->toDateString()) }}" class="form-control @error('fecha') is-invalid @enderror" required>
                            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hora Inicio</label>
                            <input type="time" name="hora_inicio" value="{{ old('hora_inicio', $ciclo->hora_inicio ?? now()->format('H:i')) }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hora Fin</label>
                            <input type="time" name="hora_fin" value="{{ old('hora_fin', $ciclo->hora_fin ?? now()->addMinutes(45)->format('H:i')) }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Temperatura (°C)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" name="temperatura" value="{{ old('temperatura', $ciclo->temperatura) }}" class="form-control @error('temperatura') is-invalid @enderror" required>
                                <span class="input-group-text">°C</span>
                            </div>
                            @error('temperatura')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Presión (bar)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="presion" value="{{ old('presion', $ciclo->presion) }}" class="form-control @error('presion') is-invalid @enderror" required>
                                <span class="input-group-text">bar</span>
                            </div>
                            @error('presion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">Tiempo Esterilización (min)</label>
                            <div class="input-group">
                                <input type="number" name="tiempo_esterilizacion" value="{{ old('tiempo_esterilizacion', $ciclo->tiempo_esterilizacion) }}" class="form-control @error('tiempo_esterilizacion') is-invalid @enderror" required>
                                <span class="input-group-text">min</span>
                            </div>
                            @error('tiempo_esterilizacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label required">Tipo de Carga</label>
                            <select name="tipo_carga" class="form-select @error('tipo_carga') is-invalid @enderror" required>
                                @foreach ($tiposCarga as $k => $label)
                                    <option value="{{ $k }}" {{ old('tipo_carga', $ciclo->tipo_carga) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('tipo_carga')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observaciones del Ciclo / Incidencias</label>
                            <textarea name="observaciones" rows="2" class="form-control" placeholder="P. ej.: Ciclo de instrumental de cirugía periodontal e implantes">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-teal-lt">
                    <h3 class="card-title"><i class="ti ti-shield-check me-2"></i>Controles y Conformidad</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Indicador Químico (Viraje Clase 4/5)</label>
                        <select name="indicador_quimico" class="form-select @error('indicador_quimico') is-invalid @enderror" required>
                            <option value="CONFORME" {{ old('indicador_quimico', $ciclo->indicador_quimico) === 'CONFORME' ? 'selected' : '' }}>CONFORME (Viraje completo correcto)</option>
                            <option value="NO_CONFORME" {{ old('indicador_quimico', $ciclo->indicador_quimico) === 'NO_CONFORME' ? 'selected' : '' }}>NO CONFORME (Viraje incompleto)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Indicador Biológico (Esporas B. stearothermophilus)</label>
                        <select name="indicador_biologico" class="form-select @error('indicador_biologico') is-invalid @enderror" required>
                            <option value="NEGATIVO" {{ old('indicador_biologico', $ciclo->indicador_biologico) === 'NEGATIVO' ? 'selected' : '' }}>NEGATIVO (Sin crecimiento - Válido)</option>
                            <option value="PENDIENTE" {{ old('indicador_biologico', $ciclo->indicador_biologico) === 'PENDIENTE' ? 'selected' : '' }}>PENDIENTE (En incubación 24h/48h)</option>
                            <option value="POSITIVO" {{ old('indicador_biologico', $ciclo->indicador_biologico) === 'POSITIVO' ? 'selected' : '' }}>POSITIVO (¡Fallo de esterilización!)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Resultado Global del Ciclo</label>
                        <select name="resultado" class="form-select fw-bold @error('resultado') is-invalid @enderror" required>
                            <option value="APROBADO" {{ old('resultado', $ciclo->resultado) === 'APROBADO' ? 'selected' : '' }} class="text-success">APROBADO (Apto para uso clínico)</option>
                            <option value="RECHAZADO" {{ old('resultado', $ciclo->resultado) === 'RECHAZADO' ? 'selected' : '' }} class="text-danger">RECHAZADO (Repetir ciclo)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Cantidad de Paquetes/Sobres</label>
                        <input type="number" name="paquetes_esterilizados" value="{{ old('paquetes_esterilizados', $ciclo->paquetes_esterilizados ?? 10) }}" min="1" max="500" class="form-control" required>
                        <small class="text-secondary">Generará las etiquetas QR correspondientes</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Fecha de Caducidad de Paquetes</label>
                        <input type="date" name="fecha_caducidad_paquetes" value="{{ old('fecha_caducidad_paquetes', $ciclo->fecha_caducidad_paquetes ? $ciclo->fecha_caducidad_paquetes->format('Y-m-d') : now()->addDays(30)->toDateString()) }}" class="form-control" required>
                        <small class="text-secondary">Recomendado: 30 a 60 días según tipo de envoltorio</small>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="ti ti-device-floppy me-2"></i>Guardar Ciclo y Generar QR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
