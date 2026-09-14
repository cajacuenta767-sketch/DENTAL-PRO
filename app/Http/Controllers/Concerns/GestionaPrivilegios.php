<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Usuario;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Nadie concede lo que no tiene: un usuario solo puede asignar los
 * permisos que él mismo posee y los roles cuyos permisos están dentro de
 * los suyos. Así ni "roles.editar" ni "usuarios.editar" sirven para
 * escalar hasta el control total.
 */
trait GestionaPrivilegios
{
    protected function esSuperAdministrador(Usuario $usuario): bool
    {
        return $usuario->hasRole('SUPER ADMINISTRADOR');
    }

    /** @return Collection<int, string> */
    protected function permisosConcedibles(Usuario $usuario): Collection
    {
        return $usuario->getAllPermissions()->pluck('name')->values();
    }

    /** Un rol es asignable/editable si todos sus permisos caben en los del usuario. */
    protected function rolAlAlcance(Usuario $usuario, Role $rol): bool
    {
        if ($this->esSuperAdministrador($usuario)) {
            return true;
        }

        if ($rol->name === 'SUPER ADMINISTRADOR') {
            return false;
        }

        $propios = $this->permisosConcedibles($usuario);

        return $rol->permissions->pluck('name')->every(fn ($p) => $propios->contains($p));
    }

    /** @return Collection<int, Role> */
    protected function rolesAlAlcance(Usuario $usuario): Collection
    {
        return Role::with('permissions')->orderBy('name')->get()
            ->filter(fn (Role $rol) => $this->rolAlAlcance($usuario, $rol))
            ->values();
    }

    /** Permisos solicitados que el usuario no puede otorgar. */
    protected function permisosFueraDeAlcance(Usuario $usuario, array $permisos): array
    {
        if ($this->esSuperAdministrador($usuario)) {
            return [];
        }

        $propios = $this->permisosConcedibles($usuario);

        return array_values(array_filter($permisos, fn ($p) => ! $propios->contains($p)));
    }
}
