<?php

namespace App\Http\Middleware;

use App\Models\Licencia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra el panel y las reservas públicas cuando la instalación no tiene una
 * licencia vigente. La instalación del proveedor (con clave privada) y los
 * entornos con LICENCIA_ACTIVA=false nunca se bloquean.
 */
class VerificarLicencia
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('licencia.activa') || filled(config('licencia.clave_privada'))) {
            return $next($request);
        }

        $licencia = Licencia::actual();

        if ($licencia->estaVigente()) {
            $restantes = $licencia->diasRestantes();

            if (! $licencia->esVitalicia() && $restantes <= (int) config('licencia.aviso_dias')) {
                View::share('licenciaAviso', $restantes === 0
                    ? 'Tu licencia vence hoy.'
                    : "Tu licencia vence en {$restantes} ".($restantes === 1 ? 'día' : 'días').'.');
            }

            return $next($request);
        }

        if (! $request->is('admin', 'admin/*')) {
            abort(503, 'Las reservas en línea no están disponibles por el momento.');
        }

        $mensaje = $licencia->estaActivada()
            ? 'Tu licencia venció el '.$licencia->vence_en->format('d/m/Y').'. Ingresa un PIN de renovación para seguir usando el sistema.'
            : 'Este sistema aún no está activado. Ingresa tu PIN de activación.';

        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje], 403);
        }

        return redirect()->route('licencia.ver')->with('error', $mensaje);
    }
}
