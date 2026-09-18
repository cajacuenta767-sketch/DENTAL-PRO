<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.perfil', [
            'usuario' => $request->user(),
            'tokens' => $request->user()->can('api.usar')
                ? $request->user()->tokens()->orderByDesc('created_at')->get()
                : collect(),
        ]);
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

    /**
     * Crea un token personal de la API. Sus capacidades son los permisos
     * que tiene el usuario en este instante; el texto plano se muestra una
     * sola vez en la siguiente carga del perfil.
     */
    public function crearToken(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre_token' => [
                'required', 'string', 'max:60',
                Rule::unique('personal_access_tokens', 'name')
                    ->where('tokenable_type', $usuario->getMorphClass())
                    ->where('tokenable_id', $usuario->getKey()),
            ],
            'expira_en' => ['nullable', 'date', 'after:today'],
        ], [
            'nombre_token.unique' => 'Ya tienes un token con ese nombre.',
        ], [
            'nombre_token' => 'nombre del token',
            'expira_en' => 'fecha de expiración',
        ]);

        $capacidades = $usuario->getAllPermissions()->pluck('name')->sort()->values()->all();
        $expiraEn = filled($datos['expira_en'] ?? null) ? CarbonImmutable::parse($datos['expira_en'])->endOfDay() : null;

        $token = $usuario->createToken($datos['nombre_token'], $capacidades, $expiraEn);

        Auditoria::registrar('CREAR', $usuario, "Creó el token de API {$datos['nombre_token']}");

        return back()
            ->with('exito', "Token «{$datos['nombre_token']}» creado. Cópialo ahora: no volverá a mostrarse.")
            ->with('token_plano', $token->plainTextToken)
            ->with('token_nombre', $datos['nombre_token']);
    }

    /** Revoca un token propio; el de otro usuario responde 404. */
    public function revocarToken(Request $request, int $tokenId): RedirectResponse
    {
        $usuario = $request->user();

        $token = $usuario->tokens()->whereKey($tokenId)->firstOrFail();
        $nombre = $token->name;
        $token->delete();

        Auditoria::registrar('ELIMINAR', $usuario, "Revocó el token de API {$nombre}");

        return back()->with('exito', "El token «{$nombre}» fue revocado. Las integraciones que lo usaban dejarán de funcionar.");
    }

    public static function reglaPassword(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    public function desbloquearSillon(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $usuario = $request->user();

        if (! Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Contraseña incorrecta. Por favor intente nuevamente.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => 'Sillón clínico desbloqueado.',
        ]);
    }
}
