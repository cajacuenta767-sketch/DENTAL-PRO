<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta con contraseña temporal (creada por un administrador o de
 * demostración) no puede usar el sistema hasta definir una propia.
 */
class ExigirCambioPassword
{
    /** Rutas que siguen accesibles mientras la contraseña está pendiente. */
    private const PERMITIDAS = [
        'password.obligatoria', 'password.obligatoria.guardar', 'logout',
        'verification.notice', 'verification.verify', 'verification.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user();

        if ($usuario && $usuario->debe_cambiar_password && ! $request->routeIs(...self::PERMITIDAS)) {
            return redirect()->route('password.obligatoria');
        }

        return $next($request);
    }
}
