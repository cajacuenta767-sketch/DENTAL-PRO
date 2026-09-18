@extends('layouts.admin')

@section('pretitulo', 'Implantología')
@section('titulo', $implante->exists ? 'Editar Implante Pieza '.$implante->posicion_fdi : 'Registrar Implante Dental · '.$paciente->nombre_completo)
@section('subtitulo', 'Trazabilidad y emisión de pasaporte digital de garantía clínica')

@section('acciones')
    <a href="{{ route('admin.implantes.paciente', $paciente) }}" class="btn btn-link">
        <i class="ti ti-arrow-left me-1"></i>Volver a implantes
    </a>
@endsection

@section('contenido')
<form action="{{ $implante->exists ? route('admin.implantes.update', $implante) : route('admin.implantes.store', $paciente) }}" method="POST">
    @csrf
    @if ($implante->exists)
        @method('PUT')
    @endif

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-teal-lt">
                    <h3 class="card-title"><i class="ti ti-needle me-2"></i>Especificaciones del Implante</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">Pieza Dental (FDI)</label>
                            <input type="number" name="posicion_fdi" value="{{ old('posicion_fdi', $implante->posicion_fdi) }}" min="11" max="48" class="form-control @error('posicion_fdi') is-invalid @enderror" placeholder="P. ej.: 16, 21, 36, 46" required>
                            <small class="text-secondary">Nomenclatura FDI (11..48)</small>
                            @error('posicion_fdi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label required">Marca Fabricante</label>
                            <input type="text" name="marca" value="{{ old('marca', $implante->marca) }}" class="form-control @error('marca') is-invalid @enderror" placeholder="Straumann, Nobel Biocare, Neodent, MIS, Zimmer..." required>
                            @error('marca')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Modelo / Línea</label>
                            <input type="text" name="modelo" value="{{ old('modelo', $implante->modelo) }}" class="form-control @error('modelo') is-invalid @enderror" placeholder="BLX, Helix GM, Active, Seven..." required>
                            @error('modelo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">Número de Lote (LOT)</label>
                            <input type="text" name="numero_lote" value="{{ old('numero_lote', $implante->numero_lote) }}" class="form-control @error('numero_lote') is-invalid @enderror" placeholder="Impreso en el blíster estéril" required>
                            @error('numero_lote')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número de Serie (SN)</label>
                            <input type="text" name="numero_serie" value="{{ old('numero_serie', $implante->numero_serie) }}" class="form-control" placeholder="Opcional">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">Diámetro (&Oslash; mm)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="diametro_mm" value="{{ old('diametro_mm', $implante->diametro_mm) }}" class="form-control @error('diametro_mm') is-invalid @enderror" required>
                                <span class="input-group-text">mm</span>
                            </div>
                            @error('diametro_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Longitud (mm)</label>
                            <div class="input-group">
                                <input type="number" step="0.01" name="longitud_mm" value="{{ old('longitud_mm', $implante->longitud_mm) }}" class="form-control @error('longitud_mm') is-invalid @enderror" required>
                                <span class="input-group-text">mm</span>
                            </div>
                            @error('longitud_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Tipo de Conexión</label>
                            <select name="tipo_conexion" class="form-select @error('tipo_conexion') is-invalid @enderror" required>
                                @foreach ($conexiones as $k => $label)
                                    <option value="{{ $k }}" {{ old('tipo_conexion', $implante->tipo_conexion) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('tipo_conexion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Torque de Inserción (Ncm)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" name="torque_insercion_ncm" value="{{ old('torque_insercion_ncm', $implante->torque_insercion_ncm) }}" class="form-control" placeholder="P. ej. 35 o 45">
                                <span class="input-group-text">Ncm</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estabilidad Primaria (ISQ - Ostell)</label>
                            <div class="input-group">
                                <input type="number" name="isq_estabilidad" value="{{ old('isq_estabilidad', $implante->isq_estabilidad) }}" min="1" max="99" class="form-control" placeholder="P. ej. 70 a 82">
                                <span class="input-group-text">ISQ</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Biomaterial / Injerto Óseo</label>
                            <input type="text" name="injerto_oseo" value="{{ old('injerto_oseo', $implante->injerto_oseo) }}" class="form-control" placeholder="P. ej.: Bio-Oss 0.5g / Aloinjerto cortico-esponjoso">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Membrana de Regeneración</label>
                            <input type="text" name="membrana" value="{{ old('membrana', $implante->membrana) }}" class="form-control" placeholder="P. ej.: Bio-Gide 25x25 / Colágeno reabsorbible">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observaciones Quirúrgicas</label>
                            <textarea name="observaciones" rows="3" class="form-control" placeholder="Detalles de la cirugía, tipo de colgajo, lecho óseo tipo D2/D3...">{{ old('observaciones', $implante->observaciones) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="ti ti-calendar me-2"></i>Fechas y Responsable</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Cirujano Implantólogo</label>
                        <select name="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror">
                            <option value="">-- Seleccionar doctor --</option>
                            @foreach ($doctores as $doc)
                                <option value="{{ $doc->id }}" {{ old('doctor_id', $implante->doctor_id) == $doc->id ? 'selected' : '' }}>
                                    {{ $doc->nombre_profesional }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Fecha de Colocación Quirúrgica</label>
                        <input type="date" name="fecha_colocacion" value="{{ old('fecha_colocacion', $implante->fecha_colocacion ? $implante->fecha_colocacion->format('Y-m-d') : now()->toDateString()) }}" class="form-control @error('fecha_colocacion') is-invalid @enderror" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Fecha de Rehabilitación Protésica</label>
                        <input type="date" name="fecha_rehabilitacion" value="{{ old('fecha_rehabilitacion', $implante->fecha_rehabilitacion ? $implante->fecha_rehabilitacion->format('Y-m-d') : '') }}" class="form-control">
                        <small class="text-secondary">Completar al colocar la corona o prótesis definitiva</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Estado del Implante</label>
                        <select name="estado" class="form-select @error('estado') is-invalid @enderror" required>
                            @foreach ($estados as $k => $label)
                                <option value="{{ $k }}" {{ old('estado', $implante->estado) === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-teal btn-lg">
                            <i class="ti ti-device-floppy me-2"></i>{{ $implante->exists ? 'Guardar Cambios' : 'Registrar Implante y Emitir Pasaporte' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
