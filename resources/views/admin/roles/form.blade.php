@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', $rol->exists ? 'Editar Rol' : 'Nuevo Rol')

@section('contenido')
<form method="POST" action="{{ $rol->exists ? route('admin.roles.update', $rol) : route('admin.roles.store') }}">
    @csrf
    @if ($rol->exists) @method('PUT') @endif

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <x-campo nombre="name" etiqueta="Nombre del rol" requerido ayuda="Por ejemplo: RECEPCION, ASISTENTE DENTAL.">
                        <input type="text" id="name" name="name" class="form-control"
                               value="{{ old('name', $rol->name) }}"
                               {{ $rol->name === 'SUPER ADMINISTRADOR' ? 'readonly' : '' }} required>
                    </x-campo>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Permisos del rol</h3>
            <div class="ms-auto btn-list">
                <button type="button" class="btn btn-sm btn-outline-primary" data-os-marcar="todos">Marcar todos</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-os-marcar="ninguno">Desmarcar</button>
            </div>
        </div>
        <div class="card-body">
            @if ($rol->name === 'SUPER ADMINISTRADOR')
                <div class="alert alert-warning">
                    <i class="ti ti-alert-triangle me-1"></i>
                    Este rol conserva siempre todos los permisos del sistema, aunque desmarques opciones.
                </div>
            @endif

            @php $esSuperAdmin = auth()->user()->hasRole('SUPER ADMINISTRADOR'); @endphp
            @error('permisos')
                <div class="alert alert-danger"><i class="ti ti-alert-circle me-1"></i>{{ $message }}</div>
            @enderror

            @foreach ($permisosPorGrupo as $grupo => $modulos)
                <h4 class="mt-3 text-uppercase text-secondary small">{{ $grupo }}</h4>
                <div class="row g-3">
                    @foreach ($modulos as $modulo => $permisos)
                        <div class="col-md-6 col-xl-4">
                            <div class="card card-sm h-100">
                                <div class="card-body">
                                    <div class="fw-medium mb-2">
                                        <i class="{{ config("odontosuite.modulos.{$modulo}.icono", 'ti ti-point') }} me-1"></i>
                                        {{ config("odontosuite.modulos.{$modulo}.etiqueta", ucfirst($modulo)) }}
                                    </div>
                                    @foreach ($permisos as $permiso)
                                        @php $bloqueado = ! $esSuperAdmin && ! in_array($permiso->name, $concedibles ?? [], true); @endphp
                                        <label class="form-check {{ $bloqueado ? 'text-secondary' : '' }}"
                                               @if ($bloqueado) title="No puedes conceder un permiso que no tienes" @endif>
                                            <input type="checkbox" name="permisos[]" value="{{ $permiso->name }}"
                                                   class="form-check-input" data-os-permiso
                                                   @if ($bloqueado) disabled title="No puedes conceder un permiso que no tienes" @endif
                                                   {{ in_array($permiso->name, old('permisos', $asignados), true) ? 'checked' : '' }}>
                                            <span class="form-check-label text-capitalize">
                                                {{ str($permiso->name)->after('.')->value() }}
                                                @if ($bloqueado)<i class="ti ti-lock ms-1 small"></i>@endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="card-footer d-flex gap-2">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-link">Cancelar</a>
            <button type="submit" class="btn btn-primary ms-auto">
                <i class="ti ti-device-floppy me-1"></i>{{ $rol->exists ? 'Guardar cambios' : 'Crear rol' }}
            </button>
        </div>
    </div>
</form>

@push('scripts')
<script>
    document.querySelectorAll('[data-os-marcar]').forEach((boton) => {
        boton.addEventListener('click', () => {
            const marcar = boton.dataset.osMarcar === 'todos';
            document.querySelectorAll('[data-os-permiso]:not(:disabled)').forEach((c) => { c.checked = marcar; });
        });
    });
</script>
@endpush
@endsection
