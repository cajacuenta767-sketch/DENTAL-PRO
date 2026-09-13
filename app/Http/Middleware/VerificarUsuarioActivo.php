<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expulsa de inmediato a los usuarios que fueron desactivados desde el
 * módulo de Usuarios sin esperar a que expire su sesión.
 */
class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->estaActivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tu cuenta está desactivada. Contacta al administrador de la clínica.');
        }

        return $next($request);
    }
}
