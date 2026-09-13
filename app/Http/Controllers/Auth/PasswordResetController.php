<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as ReglaPassword;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function solicitar(): View
    {
        return view('auth.forgot-password');
    }

    public function enviarEnlace(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $estado = Password::sendResetLink($request->only('email'));

        if ($estado !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($estado)]);
        }

        return back()->with('status', 'Te enviamos un enlace para restablecer tu contraseña.');
    }

    public function formulario(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', ReglaPassword::min(8)],
        ]);

        $estado = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($usuario) use ($request) {
                $usuario->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($usuario));
            }
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($estado)]);
        }

        return redirect()->route('login')->with('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
    }
}
