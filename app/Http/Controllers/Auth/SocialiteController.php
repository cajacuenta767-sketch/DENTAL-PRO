<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
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

        // Una cuenta existente solo se vincula si el proveedor garantiza que
        // el correo es del usuario (o ya estaba vinculada a ese mismo perfil).
        if ($usuario->exists) {
            $yaVinculada = $usuario->proveedor === $proveedor && (string) $usuario->proveedor_id === (string) $perfil->getId();

            if (! $yaVinculada && ! $this->correoVerificadoPorProveedor($proveedor, $perfil)) {
                return redirect()->route('login')
                    ->with('error', 'Ya existe una cuenta con ese correo. Inicia sesión con tu contraseña para vincularla.');
            }

            if (! $yaVinculada && $usuario->accedeAlPanel()) {
                return redirect()->route('login')
                    ->with('error', 'Las cuentas del personal de la clínica ingresan con su contraseña.');
            }
        }

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
        $usuario->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
        Auditoria::registrar('INGRESO', $usuario, 'Inicio de sesión con '.ucfirst($proveedor), usuarioId: $usuario->id);

        return redirect()->intended($usuario->destinoInicial());
    }

    /** Google y GitHub indican si el correo devuelto está verificado. */
    private function correoVerificadoPorProveedor(string $proveedor, $perfil): bool
    {
        $crudo = (array) ($perfil->user ?? []);

        return match ($proveedor) {
            'google' => (bool) ($crudo['email_verified'] ?? $crudo['verified_email'] ?? false),
            // Socialite solo devuelve el correo primario de GitHub cuando está verificado.
            'github' => filled($perfil->getEmail()),
            default => false,
        };
    }

    private function disponible(string $proveedor): bool
    {
        return in_array($proveedor, self::PROVEEDORES, true)
            && filled(config("services.{$proveedor}.client_id"));
    }
}
