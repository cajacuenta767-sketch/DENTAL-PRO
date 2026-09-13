<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class SocialiteController extends Controller
{
    /** Proveedores habilitados en el sistema. */
    private const PROVEEDORES = ['google', 'github'];

    public function redirect(string $proveedor): SymfonyRedirect|RedirectResponse
    {
        if (! $this->disponible($proveedor)) {
            return redirect()->route('login')
                ->with('error', 'El acceso con '.ucfirst($proveedor).' no está configurado en este servidor.');
        }

        return Socialite::driver($proveedor)->redirect();
    }

    public function callback(string $proveedor): RedirectResponse
    {
        if (! $this->disponible($proveedor)) {
            return redirect()->route('login')
                ->with('error', 'El acceso con '.ucfirst($proveedor).' no está configurado en este servidor.');
        }

        try {
            $perfil = Socialite::driver($proveedor)->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->with('error', 'No pudimos completar el acceso con '.ucfirst($proveedor).'. Intenta de nuevo.');
        }

        if (blank($perfil->getEmail())) {
            return redirect()->route('login')
                ->with('error', 'Tu cuenta de '.ucfirst($proveedor).' no expone un correo electrónico verificable.');
        }

        $usuario = Usuario::firstOrNew(['email' => $perfil->getEmail()]);

        // Primera vez: creamos la cuenta con rol de paciente y correo ya verificado.
        if (! $usuario->exists) {
            $usuario->fill([
                'nombre' => $perfil->getName() ?: $perfil->getNickname() ?: Str::before($perfil->getEmail(), '@'),
                'password' => Str::random(40),
                'estado' => 'activo',
            ])->forceFill(['email_verified_at' => now()]);
        }

        $usuario->forceFill([
            'proveedor' => $proveedor,
            'proveedor_id' => $perfil->getId(),
            'avatar' => $perfil->getAvatar(),
        ])->save();

        if ($usuario->roles->isEmpty()) {
            $usuario->assignRole('PACIENTE');
        }

        if (! $usuario->estaActivo()) {
            return redirect()->route('login')
                ->with('error', 'Tu cuenta está desactivada. Contacta al administrador de la clínica.');
        }

        Auth::login($usuario, remember: true);

        return redirect()->intended(route('admin.home'));
    }

    private function disponible(string $proveedor): bool
    {
        return in_array($proveedor, self::PROVEEDORES, true)
            && filled(config("services.{$proveedor}.client_id"));
    }
}
