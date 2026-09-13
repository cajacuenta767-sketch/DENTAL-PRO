<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.perfil', ['usuario' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuarios,email,'.$usuario->id],
            'telefono' => ['nullable', 'string', 'max:50'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('foto')) {
            if ($usuario->avatar && str_starts_with($usuario->avatar, 'avatares/')) {
                Storage::disk('public')->delete($usuario->avatar);
            }
            $datos['avatar'] = $request->file('foto')->store('avatares', 'public');
        }

        // Cambiar el correo obliga a verificarlo de nuevo.
        if ($datos['email'] !== $usuario->email) {
            $usuario->email_verified_at = null;
        }

        $usuario->fill(collect($datos)->except('foto')->all())->save();

        return back()->with('exito', 'Tu perfil fue actualizado.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], ['password_actual' => 'contraseña actual']);

        $request->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('exito', 'Tu contraseña fue cambiada.');
    }
}
