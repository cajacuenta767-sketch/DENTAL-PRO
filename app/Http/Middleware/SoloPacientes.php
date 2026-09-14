<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El portal es para cuentas con rol PACIENTE. */
class SoloPacientes
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->hasRole('PACIENTE'), 403, 'El portal es exclusivo para pacientes.');

        return $next($request);
    }
}
