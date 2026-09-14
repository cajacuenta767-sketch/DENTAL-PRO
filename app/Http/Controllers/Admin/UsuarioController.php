<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\GestionaPrivilegios;
use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    use GestionaPrivilegios;

    public function index(Request $request): View
    {
        $usuarios = Usuario::query()
            ->with(['roles', 'sucursal'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)->orWhere('email', 'ilike', $t));
            })
            ->when($request->filled('sucursal_id'), fn ($q) => $q->where('sucursal_id', $request->sucursal_id))
            ->when($request->filled('rol'), fn ($q) => $q->role($request->rol))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.usuarios.form', [
            'usuario' => new Usuario(['estado' => 'activo']),
            'roles' => $this->rolesAlAlcance($request->user()),
            'asignados' => [],
            'puedeAsignarSede' => $this->puedeAsignarSede($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        // Sin contraseña indicada se genera una temporal que el usuario debe cambiar al entrar.
        $temporal = filled($datos['password'] ?? null) ? null : Usuario::passwordTemporal();

        $usuario = Usuario::create(collect($datos)->except(['roles', 'password'])->all() + [
            'password' => $temporal ?? $datos['password'],
            'debe_cambiar_password' => true,
        ]);
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->syncRoles($datos['roles'] ?? []);

        Auditoria::registrar('CREAR', $usuario, 'Creó el usuario con roles: '.implode(', ', $datos['roles'] ?? ['ninguno']));

        return redirect()->route('admin.usuarios.index')
            ->with('exito', "El usuario {$usuario->nombre} fue creado.")
            ->with('aviso', $temporal ? "Contraseña temporal de {$usuario->email}: {$temporal} (se pedirá cambiarla en el primer acceso)." : null);
    }

    public function edit(Request $request, Usuario $usuario): View
    {
        $this->comprobarAlcance($request->user(), $usuario);

        return view('admin.usuarios.form', [
            'usuario' => $usuario,
            'roles' => $this->rolesAlAlcance($request->user()),
            'asignados' => $usuario->roles->pluck('name')->all(),
            'puedeAsignarSede' => $this->puedeAsignarSede($request->user()),
        ]);
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $this->comprobarAlcance($request->user(), $usuario);

        $datos = $this->validar($request, $usuario);

        // Nadie puede desactivarse ni quitarse los roles a sí mismo.
        if ($usuario->is($request->user())) {
            $datos['estado'] = 'activo';
            $datos['roles'] = $usuario->roles->pluck('name')->all();
        }

        $usuario->fill(collect($datos)->except(['roles', 'password'])->all());

        if (filled($datos['password'] ?? null)) {
            $usuario->password = $datos['password'];
            // Una clave puesta por un administrador es temporal para el titular.
            $usuario->debe_cambiar_password = ! $usuario->is($request->user());
        }

        $usuario->save();
        $usuario->syncRoles($datos['roles'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('exito', "El usuario {$usuario->nombre} fue actualizado.");
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
        $this->comprobarAlcance($request->user(), $usuario);

        if ($usuario->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        if ($usuario->hasRole('SUPER ADMINISTRADOR') && Usuario::role('SUPER ADMINISTRADOR')->count() <= 1) {
            return back()->with('error', 'Debe existir al menos un super administrador en el sistema.');
        }

        $nombre = $usuario->nombre;
        $usuario->delete();

        return back()->with('exito', "El usuario {$nombre} fue eliminado.");
    }

    private function validar(Request $request, ?Usuario $usuario = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'.($usuario ? ",{$usuario->id}" : '')],
            'telefono' => ['nullable', 'string', 'max:50'],
            'estado' => ['required', 'in:activo,inactivo'],
            'sucursal_id' => ['nullable', 'exists:sucursales,id'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // Ligar una cuenta a una sede es decisión de quien administra las sedes;
        // el resto no toca ese dato (vacío = ve todas las sedes).
        if ($this->puedeAsignarSede($request->user())) {
            $datos['sucursal_id'] = filled($datos['sucursal_id'] ?? null) ? (int) $datos['sucursal_id'] : null;
        } else {
            unset($datos['sucursal_id']);
        }

        $alcance = $this->rolesAlAlcance($request->user())->pluck('name');
        $fuera = collect($datos['roles'] ?? [])->reject(fn ($r) => $alcance->contains($r));

        if ($fuera->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes asignar roles con más permisos que los tuyos: '.$fuera->implode(', ').'.',
            ]);
        }

        return $datos;
    }

    private function puedeAsignarSede(Usuario $actor): bool
    {
        return $this->esSuperAdministrador($actor) || $actor->can('sucursales.editar');
    }

    /** No se administra a alguien con más privilegios que uno mismo. */
    private function comprobarAlcance(Usuario $actor, Usuario $objetivo): void
    {
        if ($actor->is($objetivo) || $this->esSuperAdministrador($actor)) {
            return;
        }

        foreach ($objetivo->roles as $rol) {
            abort_unless($this->rolAlAlcance($actor, $rol), 403, "El usuario tiene el rol {$rol->name}, que excede tus privilegios.");
        }
    }
}
