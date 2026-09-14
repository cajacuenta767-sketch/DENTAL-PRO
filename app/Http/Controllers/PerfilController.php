<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
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

        if ($usuario->wasChanged('email')) {
            $usuario->sendEmailVerificationNotification();
        }

        return back()->with('exito', 'Tu perfil fue actualizado.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'password_actual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', self::reglaPassword()],
        ], [], ['password_actual' => 'contraseña actual']);

        $request->user()->forceFill([
            'password' => Hash::make($request->password),
            'debe_cambiar_password' => false,
        ])->save();

        Auditoria::registrar('ACTUALIZAR', $request->user(), 'Cambió su contraseña');

        return back()->with('exito', 'Tu contraseña fue cambiada.');
    }

    /** Activa o desactiva el segundo factor por correo. */
    public function dosFactores(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $request->validate(['password_actual' => ['required', 'current_password']], [], ['password_actual' => 'contraseña actual']);

        if (! $usuario->hasVerifiedEmail()) {
            return back()->with('error', 'Verifica tu correo antes de activar el doble factor: el código llegará por esa vía.');
        }

        $activar = $request->boolean('dos_factores');
        $usuario->forceFill(['dos_factores' => $activar, 'codigo_2fa' => null, 'codigo_2fa_expira_en' => null])->save();

        Auditoria::registrar('ACTUALIZAR', $usuario, $activar ? 'Activó el doble factor' : 'Desactivó el doble factor');

        return back()->with('exito', $activar
            ? 'Doble factor activado. A partir de ahora te pediremos un código enviado a tu correo al iniciar sesión.'
            : 'Doble factor desactivado.');
    }

    /** Pantalla de cambio obligatorio para contraseñas temporales. */
    public function cambioObligatorio(Request $request): View|RedirectResponse
    {
        if (! $request->user()->debe_cambiar_password) {
            return redirect($request->user()->destinoInicial());
        }

        return view('auth.password-obligatoria');
    }

    public function guardarCambioObligatorio(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', self::reglaPassword(), 'different:password_actual'],
            'password_actual' => ['required', 'current_password'],
        ], [], ['password_actual' => 'contraseña actual']);

        $request->user()->forceFill([
            'password' => Hash::make($request->password),
            'debe_cambiar_password' => false,
        ])->save();

        Auditoria::registrar('ACTUALIZAR', $request->user(), 'Definió su contraseña en el primer acceso');

        return redirect($request->user()->destinoInicial())
            ->with('exito', 'Contraseña actualizada. ¡Bienvenido!');
    }

    public static function reglaPassword(): Password
    {
        return Password::min(8)->letters()->numbers();
    }
}
