<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CodigoAccesoMail;
use App\Models\Auditoria;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const SESION_2FA = 'acceso.pendiente_2fa';

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

        if (! Auth::validate($datos)) {
            Auditoria::registrar('ACCESO_FALLIDO', null, 'Credenciales incorrectas para '.$datos['email']);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
            ]);
        }

        /** @var Usuario $usuario */
        $usuario = Usuario::where('email', $datos['email'])->firstOrFail();

        if (! $usuario->estaActivo()) {
            throw ValidationException::withMessages([
                'email' => 'Tu cuenta está desactivada. Contacta al administrador de la clínica.',
            ]);
        }

        // Segundo factor: la sesión queda a medias hasta validar el código.
        if ($usuario->dos_factores) {
            $request->session()->put(self::SESION_2FA, [
                'usuario_id' => $usuario->id,
                'remember' => $request->boolean('remember'),
                'expira' => now()->addMinutes(10)->timestamp,
            ]);

            $this->enviarCodigo($usuario);

            return redirect()->route('login.verificar');
        }

        return $this->completarAcceso($request, $usuario, $request->boolean('remember'));
    }

    /** Pantalla para introducir el código recibido por correo. */
    public function verificar(Request $request): View|RedirectResponse
    {
        if (! $this->pendiente($request)) {
            return redirect()->route('login');
        }

        return view('auth.verificar-codigo');
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $pendiente = $this->pendiente($request);

        if (! $pendiente) {
            return redirect()->route('login')->with('error', 'La verificación expiró. Vuelve a iniciar sesión.');
        }

        $request->validate(['codigo' => ['required', 'digits:6']]);

        $usuario = Usuario::findOrFail($pendiente['usuario_id']);

        if (! $usuario->verificarCodigo2fa($request->codigo)) {
            Auditoria::registrar('ACCESO_FALLIDO', $usuario, 'Código de verificación incorrecto');

            throw ValidationException::withMessages(['codigo' => 'El código no es válido o ya venció.']);
        }

        $request->session()->forget(self::SESION_2FA);

        return $this->completarAcceso($request, $usuario, (bool) $pendiente['remember']);
    }

    public function reenviarCodigo(Request $request): RedirectResponse
    {
        $pendiente = $this->pendiente($request);

        if (! $pendiente) {
            return redirect()->route('login');
        }

        $this->enviarCodigo(Usuario::findOrFail($pendiente['usuario_id']));

        return back()->with('status', 'Te enviamos un nuevo código.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auditoria::registrar('SALIDA', $request->user(), 'Cierre de sesión');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('publico.inicio');
    }

    private function completarAcceso(Request $request, Usuario $usuario, bool $remember): RedirectResponse
    {
        Auth::login($usuario, $remember);
        $request->session()->regenerate();

        $usuario->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
        Auditoria::registrar('INGRESO', $usuario, 'Inicio de sesión', usuarioId: $usuario->id);

        return redirect()->intended($usuario->destinoInicial());
    }

    private function enviarCodigo(Usuario $usuario): void
    {
        $codigo = $usuario->generarCodigo2fa();

        Mail::to($usuario->email)->send(new CodigoAccesoMail($usuario, $codigo));
    }

    private function pendiente(Request $request): ?array
    {
        $pendiente = $request->session()->get(self::SESION_2FA);

        if (! $pendiente || $pendiente['expira'] < now()->timestamp) {
            $request->session()->forget(self::SESION_2FA);

            return null;
        }

        return $pendiente;
    }
}
