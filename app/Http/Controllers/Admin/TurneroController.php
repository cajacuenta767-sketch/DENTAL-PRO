<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Support\SucursalActiva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TurneroController extends Controller
{
    /** Pantalla TV completa para la sala de espera */
    public function pantalla(): View
    {
        return view('admin.turnero.pantalla', [
            'clinica' => Ajuste::actual(),
        ]);
    }

    /** Endpoint de llamada para médicos / recepción */
    public function llamar(Request $request, Cita $cita): JsonResponse|RedirectResponse
    {
        $request->validate([
            'consultorio' => ['required', 'string', 'max:50'],
        ]);

        $cita->update([
            'llamado_en' => now(),
            'consultorio' => $request->consultorio,
            'estado' => $cita->estado === 'PENDIENTE' || $cita->estado === 'CONFIRMADA' ? 'EN_CURSO' : $cita->estado,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'mensaje' => "Paciente llamado a {$cita->consultorio}.",
                'cita' => $cita,
            ]);
        }

        return back()->with('exito', "Turno llamado para {$cita->paciente->nombre_completo} a {$cita->consultorio}.");
    }

    /** Polling de turnos activos para el televisor */
    public function datos(): JsonResponse
    {
        $sucursalActiva = SucursalActiva::id();
        $hoy = now()->toDateString();

        $llamados = Cita::query()
            ->with(['paciente', 'doctor'])
            ->whereDate('fecha', $hoy)
            ->whereNotNull('llamado_en')
            ->when($sucursalActiva, fn ($q) => $q->where('sucursal_id', $sucursalActiva))
            ->orderByDesc('llamado_en')
            ->limit(6)
            ->get();

        $actual = $llamados->first();
        $recientes = $llamados->slice(1);

        return response()->json([
            'actual' => $actual ? [
                'id' => $actual->id,
                'paciente' => $actual->paciente->nombre_completo,
                'consultorio' => $actual->consultorio ?? 'Consultorio Principal',
                'doctor' => $actual->doctor?->nombre_profesional ?? 'Dr. en turno',
                'hora' => $actual->llamado_en->format('H:i:s'),
                'token' => $actual->token,
            ] : null,
            'recientes' => $recientes->map(fn ($c) => [
                'paciente' => $c->paciente->nombre_completo,
                'consultorio' => $c->consultorio ?? 'Consultorio',
                'hora' => $c->llamado_en->format('H:i'),
            ])->values(),
        ]);
    }
}
