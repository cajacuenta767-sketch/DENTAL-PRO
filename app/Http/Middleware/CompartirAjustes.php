<?php

namespace App\Http\Middleware;

use App\Models\Ajuste;
use App\Models\Cita;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pone a disposición de todas las vistas la configuración de la clínica y,
 * para usuarios autenticados, la agenda del día que alimenta la campana
 * de notificaciones del navbar.
 */
class CompartirAjustes
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('ajustes', Ajuste::actual());

        $agendaHoy = collect();

        if (Auth::check()) {
            $agendaHoy = Cita::with(['paciente:id,nombres,apellidos', 'tratamiento:id,nombre'])
                ->delDia()
                ->vigentes()
                ->orderBy('hora')
                ->limit(6)
                ->get();
        }

        View::share('agendaHoy', $agendaHoy);
        View::share('citasHoy', Auth::check() ? Cita::delDia()->vigentes()->count() : 0);

        return $next($request);
    }
}
