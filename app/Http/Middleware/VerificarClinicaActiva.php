<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarClinicaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if (! $usuario || $usuario->esSuperAdministrador() || ! $usuario->clinica_id) {
            return $next($request);
        }

        $clinica = $usuario->clinica;
        abort_if(! $clinica || $clinica->estado !== 'ACTIVA', 403, 'La clínica está suspendida. Contacta al administrador de la plataforma.');
        abort_if($clinica->vence_en?->isPast(), 403, 'La licencia de la clínica venció. Contacta al administrador de la plataforma.');

        return $next($request);
    }
}
