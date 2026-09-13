<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolController extends Controller
{
    /** Roles que el sistema necesita para funcionar y no pueden borrarse. */
    private const PROTEGIDOS = ['SUPER ADMINISTRADOR'];

    public function index(Request $request): View
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($request->filled('buscar'), fn ($q) => $q->where('name', 'ilike', '%'.$request->buscar.'%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.form', [
            'rol' => new Role,
            'permisosPorGrupo' => $this->permisosPorGrupo(),
            'asignados' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('exito', "El rol {$rol->name} fue creado.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.form', [
            'rol' => $role,
            'permisosPorGrupo' => $this->permisosPorGrupo(),
            'asignados' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $datos = $this->validar($request, $role->id);

        // El super administrador conserva siempre todos los permisos.
        if (in_array($role->name, self::PROTEGIDOS, true)) {
            $role->syncPermissions(Permission::all());

            return redirect()->route('admin.roles.index')
                ->with('aviso', 'El rol SUPER ADMINISTRADOR siempre conserva todos los permisos.');
        }

        $role->update(['name' => $datos['name']]);
        $role->syncPermissions($datos['permisos'] ?? []);

        return redirect()->route('admin.roles.index')
            ->with('exito', "El rol {$role->name} fue actualizado.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::PROTEGIDOS, true)) {
            return back()->with('error', 'El rol SUPER ADMINISTRADOR no se puede eliminar.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'No puedes eliminar un rol que todavía tiene usuarios asignados.');
        }

        $nombre = $role->name;
        $role->delete();

        return back()->with('exito', "El rol {$nombre} fue eliminado.");
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'.($ignorar ? ",{$ignorar}" : '')],
            'permisos' => ['array'],
            'permisos.*' => ['string', 'exists:permissions,name'],
        ], [], ['name' => 'nombre del rol']);
    }

    /** Agrupa los permisos por el grupo declarado en config/odontosuite.php. */
    private function permisosPorGrupo(): array
    {
        $modulos = config('odontosuite.modulos');

        return Permission::orderBy('name')->get()
            ->groupBy(function (Permission $permiso) use ($modulos) {
                $modulo = str($permiso->name)->before('.')->value();

                return $modulos[$modulo]['grupo'] ?? 'Otros';
            })
            ->map(fn ($permisos) => $permisos->groupBy(fn ($p) => str($p->name)->before('.')->value()))
            ->all();
    }
}
