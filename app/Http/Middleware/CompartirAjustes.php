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
 * para el personal con acceso a la agenda, las citas del día que alimentan
 * la campana de notificaciones del navbar. Un doctor solo ve las suyas.
 */
class CompartirAjustes
{
    public function handle(Request $request, Closure $next): Response
    {
        View::share('ajustes', Ajuste::actual());

        $agendaHoy = collect();
        $citasHoy = 0;
        $usuario = Auth::user();

        if ($usuario && ($usuario->can('citas.ver') || $usuario->can('agenda.ver'))) {
            $propio = $usuario->can('agenda.todos') || $usuario->can('citas.ver') ? null : $usuario->doctor;

            $consulta = Cita::query()
                ->delDia()
                ->vigentes()
                ->when($propio, fn ($q) => $q->where('doctor_id', $propio->id))
                ->when(! $propio && ! $usuario->can('citas.ver') && $usuario->doctor, fn ($q) => $q->where('doctor_id', $usuario->doctor->id));

            $citasHoy = (clone $consulta)->count();
            $agendaHoy = $consulta
                ->with(['paciente:id,nombres,apellidos', 'tratamiento:id,nombre'])
                ->orderBy('hora')
                ->limit(6)
                ->get();
        }

        View::share('agendaHoy', $agendaHoy);
        View::share('citasHoy', $citasHoy);

        return $next($request);
    }
}
