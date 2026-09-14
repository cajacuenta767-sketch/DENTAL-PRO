<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Horas libres de un doctor, calculadas por AgendaService como en el panel. */
class AgendaApiController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AgendaService $agenda) {}

    public static function middleware(): array
    {
        return [new Middleware(self::forzarJson(...))];
    }

    public function horas(Request $request): JsonResponse
    {
        $this->autorizar($request, 'citas.ver');

        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'fecha' => ['required', 'date'],
            'tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
        ], [], ['doctor_id' => 'doctor', 'tratamiento_id' => 'tratamiento']);

        $doctor = Doctor::findOrFail($datos['doctor_id']);
        $duracion = isset($datos['tratamiento_id']) ? Tratamiento::find($datos['tratamiento_id'])?->duracion : null;

        return response()->json([
            'fecha' => $datos['fecha'],
            'dia' => $this->agenda->nombreDia($datos['fecha']),
            'intervalo' => $this->agenda->intervalo(),
            'duracion' => (int) ($duracion ?: $this->agenda->intervalo()),
            'horas' => $this->agenda->horasLibres($doctor, $datos['fecha'], null, $duracion),
        ]);
    }

    /** Toda respuesta de la API es JSON aunque el cliente no mande Accept. */
    private static function forzarJson(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }

    /** Exige el permiso del usuario y, si entró con token, que el token lo incluya. */
    private function autorizar(Request $request, string $permiso): void
    {
        $usuario = $request->user();

        abort_unless($usuario->estaActivo(), 403, 'Tu cuenta está inactiva.');
        abort_unless($usuario->can($permiso), 403, "No tienes el permiso {$permiso}.");
        abort_unless(! $usuario->currentAccessToken() || $usuario->tokenCan($permiso), 403, "El token no tiene la capacidad {$permiso}.");
    }
}
