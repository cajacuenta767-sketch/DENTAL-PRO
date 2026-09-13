<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login', [
            'googleActivo' => filled(config('services.google.client_id')),
            'githubActivo' => filled(config('services.github.client_id')),
            'socialActivo' => filled(config('services.google.client_id')) || filled(config('services.github.client_id')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $request->session()->regenerateToken();

        if (! Auth::attempt($datos, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
            ]);
        }

        if (! Auth::user()->estaActivo()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está desactivada. Contacta al administrador de la clínica.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('publico.inicio');
    }
}
