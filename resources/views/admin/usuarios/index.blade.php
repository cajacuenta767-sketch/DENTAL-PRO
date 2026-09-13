@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Usuarios del Sistema')

@section('acciones')
    @can('usuarios.crear')
        <a href="{{ route('admin.usuarios.create') }}" class="btn btn-primary">
            <i class="ti ti-user-plus me-1"></i>Nuevo Usuario
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Usuarios</h3>
    </div>
    <div class="card-body border-bottom py-3">
        <form method="GET" class="row g-2">
            <div class="col-md">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control"
                       placeholder="Escribe el nombre o email">
            </div>
            <div class="col-md-3">
                <select name="rol" class="form-select">
                    <option value="">— Todos los roles —</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol }}" @selected(request('rol') === $rol)>{{ $rol }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary w-100"><i class="ti ti-search me-1"></i>Buscar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th class="text-center">Estado</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td class="text-secondary">{{ $loop->iteration + ($usuarios->currentPage() - 1) * $usuarios->perPage() }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($usuario->avatar)
                                    <span class="avatar avatar-xs" style="background-image: url({{ $usuario->avatar }})"></span>
                                @else
                                    <span class="avatar avatar-xs bg-brand">{{ $usuario->iniciales }}</span>
                                @endif
                                <span>{{ $usuario->nombre }}</span>
                            </div>
                        </td>
                        <td class="text-secondary">{{ $usuario->email }}</td>
                        <td>
                            @forelse ($usuario->roles as $rol)
                                <span class="badge bg-blue-lt">{{ $rol->name }}</span>
                            @empty
                                <span class="text-secondary small">Sin rol</span>
                            @endforelse
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $usuario->estaActivo() ? 'success' : 'secondary' }}-lt">
                                {{ $usuario->estaActivo() ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('usuarios.editar')
                                    <a href="{{ route('admin.usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('usuarios.eliminar')
                                    <form method="POST" action="{{ route('admin.usuarios.destroy', $usuario) }}"
                                          data-confirmar="¿Eliminar al usuario {{ $usuario->nombre }}?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-vacio icono="ti ti-users-off" titulo="Sin usuarios" texto="Ningún usuario coincide con la búsqueda." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($usuarios->hasPages())
        <div class="card-footer">{{ $usuarios->links() }}</div>
    @endif
</div>
@endsection
