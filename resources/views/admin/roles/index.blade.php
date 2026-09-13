@extends('layouts.admin')

@section('pretitulo', 'Configuración')
@section('titulo', 'Roles y Permisos')

@section('acciones')
    @can('roles.crear')
        <a href="{{ route('admin.roles.create') }}" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i>Nuevo Rol
        </a>
    @endcan
@endsection

@section('contenido')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Listado de Roles</h3>
        <div class="ms-auto">
            <form method="GET" class="d-flex gap-2">
                <input type="search" name="buscar" value="{{ request('buscar') }}" class="form-control form-control-sm"
                       placeholder="Buscar rol...">
                <button class="btn btn-sm btn-primary"><i class="ti ti-search"></i></button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th style="width: 3rem;">#</th>
                    <th>Rol</th>
                    <th class="text-center">Permisos</th>
                    <th class="text-center">Usuarios</th>
                    <th class="w-1">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $rol)
                    <tr>
                        <td class="text-secondary">{{ $loop->iteration + ($roles->currentPage() - 1) * $roles->perPage() }}</td>
                        <td>
                            <span class="badge bg-primary-lt"><i class="ti ti-shield-lock me-1"></i>{{ $rol->name }}</span>
                            @if ($rol->name === 'SUPER ADMINISTRADOR')
                                <span class="badge bg-yellow-lt ms-1">Protegido</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $rol->permissions_count }}</td>
                        <td class="text-center">{{ $rol->users_count }}</td>
                        <td>
                            <div class="btn-list flex-nowrap">
                                @can('roles.editar')
                                    <a href="{{ route('admin.roles.edit', $rol) }}" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                @endcan
                                @can('roles.eliminar')
                                    <form method="POST" action="{{ route('admin.roles.destroy', $rol) }}"
                                          data-confirmar="¿Eliminar el rol {{ $rol->name }}?">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="ti ti-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-vacio icono="ti ti-shield-off" titulo="Sin roles registrados"
                                     texto="Crea el primer rol para empezar a repartir permisos." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($roles->hasPages())
        <div class="card-footer">{{ $roles->links() }}</div>
    @endif
</div>
@endsection
