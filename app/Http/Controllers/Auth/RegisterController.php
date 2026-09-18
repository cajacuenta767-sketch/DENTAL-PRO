<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Clinica;
use App\Models\Invitacion;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', ['codigo' => $request->query('codigo')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:usuarios,email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'codigo' => ['nullable', 'string', 'max:80'],
            'clinica_nombre' => ['nullable', 'string', 'max:160'],
        ]);

        $usuario = DB::transaction(function () use ($datos) {
            $invitacion = null;
            if (filled($datos['codigo'] ?? null)) {
                $invitacion = Invitacion::where('codigo_hash', Invitacion::hashCodigo($datos['codigo']))->lockForUpdate()->first();
                if (! $invitacion || ! $invitacion->estaDisponible()) {
                    throw ValidationException::withMessages(['codigo' => 'El código no existe, venció o ya fue utilizado.']);
                }
                if ($invitacion->email && mb_strtolower($invitacion->email) !== mb_strtolower($datos['email'])) {
                    throw ValidationException::withMessages(['codigo' => 'Este código fue emitido para otro correo electrónico.']);
                }
            }

            $clinica = $invitacion?->clinica;
            if ($invitacion?->rol === 'ADMINISTRADOR') {
                if (blank($datos['clinica_nombre'] ?? null)) {
                    throw ValidationException::withMessages(['clinica_nombre' => 'Indica el nombre de tu clínica para activar la cuenta.']);
                }
                $base = Str::slug($datos['clinica_nombre']);
                $clinica = Clinica::create([
                    'nombre' => $datos['clinica_nombre'],
                    'slug' => $base.'-'.Str::lower(Str::random(6)),
                    'plan' => $invitacion->datos['plan'] ?? 'PRUEBA',
                    'vence_en' => now()->addDays((int) ($invitacion->datos['dias_licencia'] ?? 30)),
                ]);
                $invitacion->update(['clinica_id' => $clinica->id]);
            }

            $usuario = Usuario::create([
                'clinica_id' => $clinica?->id,
                'nombre' => $datos['nombre'], 'email' => $datos['email'],
                'telefono' => $datos['telefono'] ?? null, 'password' => $datos['password'],
            ]);
            $usuario->assignRole($invitacion?->rol ?? 'PACIENTE');

            if ($invitacion) {
                $invitacion->update(['usos' => $invitacion->usos + 1, 'usada_por' => $usuario->id, 'usada_en' => now(), 'activa' => $invitacion->usos + 1 < $invitacion->usos_maximos]);
            }
            return $usuario;
        });

        event(new Registered($usuario));
        Auth::login($usuario);

        return redirect()->to($usuario->destinoInicial())
            ->with('exito', '¡Bienvenido a '.config('app.name').'! Confirma tu correo para continuar.');
    }
}
