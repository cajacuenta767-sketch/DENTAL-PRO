<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index(Request $request): View
    {
        $usuarios = Usuario::query()
            ->with('roles')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)->orWhere('email', 'ilike', $t));
            })
            ->when($request->filled('rol'), fn ($q) => $q->role($request->rol))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.form', [
            'usuario' => new Usuario(['estado' => 'activo']),
            'roles' => Role::orderBy('name')->get(),
            'asignados' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'estado' => ['required', 'in:activo,inactivo'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        $usuario = Usuario::create(collect($datos)->except('roles')->all());
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->syncRoles($datos['roles'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('exito', "El usuario {$usuario->nombre} fue creado.");
    }

    public function edit(Usuario $usuario): View
    {
        return view('admin.usuarios.form', [
            'usuario' => $usuario,
            'roles' => Role::orderBy('name')->get(),
            'asignados' => $usuario->roles->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email,'.$usuario->id],
            'telefono' => ['nullable', 'string', 'max:50'],
            'estado' => ['required', 'in:activo,inactivo'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        // Nadie puede desactivarse ni quitarse los roles a sí mismo.
        if ($usuario->is($request->user())) {
            $datos['estado'] = 'activo';
            $datos['roles'] = $usuario->roles->pluck('name')->all();
        }

        $usuario->fill(collect($datos)->except(['roles', 'password'])->all());

        if (filled($datos['password'] ?? null)) {
            $usuario->password = $datos['password'];
        }

        $usuario->save();
        $usuario->syncRoles($datos['roles'] ?? []);

        return redirect()->route('admin.usuarios.index')
            ->with('exito', "El usuario {$usuario->nombre} fue actualizado.");
    }

    public function destroy(Request $request, Usuario $usuario): RedirectResponse
    {
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
}
