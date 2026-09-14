<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CitaResource;
use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Citas vía API. Aplica exactamente las reglas del panel: la hora debe
 * caer en un turno del doctor y la cita completa (según la duración del
 * tratamiento) no puede cruzarse con otra vigente.
 */
class CitaApiController extends Controller implements HasMiddleware
{
    public function __construct(private readonly AgendaService $agenda) {}

    public static function middleware(): array
    {
        return [new Middleware(self::forzarJson(...))];
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request, 'citas.ver');

        $request->validate([
            'fecha' => ['nullable', 'date'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'doctor_id' => ['nullable', 'integer'],
            'paciente_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:'.implode(',', Cita::ESTADOS)],
        ]);

        $citas = Cita::query()
            ->with(['paciente', 'doctor.especialidad', 'tratamiento'])
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->fecha))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha', '<=', $request->hasta))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($request->filled('paciente_id'), fn ($q) => $q->where('paciente_id', $request->paciente_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderBy('fecha')->orderBy('hora')
            ->paginate($this->porPagina($request))
            ->withQueryString();

        return CitaResource::collection($citas);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request, 'citas.crear');

        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'tratamiento_id' => ['required', 'exists:tratamientos,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'estado' => ['nullable', 'in:'.implode(',', Cita::ESTADOS)],
            'motivo' => ['nullable', 'string', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'tratamiento_id' => 'tratamiento',
        ]);

        $datos['estado'] = $datos['estado'] ?? 'PENDIENTE';
        $datos['origen'] = 'RECEPCION';
        $datos['sucursal_id'] = $request->user()->sucursal_id;

        $doctor = Doctor::findOrFail($datos['doctor_id']);
        $duracion = (int) (Tratamiento::find($datos['tratamiento_id'])?->duracion ?: $this->agenda->intervalo());

        if (! $this->agenda->horaValida($doctor, $datos['fecha'], $datos['hora'], $duracion)) {
            throw ValidationException::withMessages([
                'hora' => "El doctor no atiende ese día a esa hora o el tratamiento ({$duracion} min) no cabe en su turno. Revisa sus horarios configurados.",
            ]);
        }

        // Las canceladas no cuentan; las demás bloquean toda su duración.
        if ($datos['estado'] !== 'CANCELADA') {
            $conflicto = $this->agenda->conflicto($doctor, $datos['fecha'], $datos['hora'], $duracion);

            if ($conflicto) {
                throw ValidationException::withMessages([
                    'hora' => "Ese horario se cruza con la cita {$conflicto->token} (".substr((string) $conflicto->hora, 0, 5).', '.$conflicto->duracion_minutos.' min). Elige otro.',
                ]);
            }
        }

        $cita = Cita::create($datos)->load(['paciente', 'doctor.especialidad', 'tratamiento']);

        $this->notificarPaciente($cita);

        return (new CitaResource($cita))->response()->setStatusCode(201);
    }

    public function show(Request $request, Cita $cita): CitaResource
    {
        $this->autorizar($request, 'citas.ver');

        return new CitaResource($cita->load(['paciente', 'doctor.especialidad', 'tratamiento']));
    }

    /** Cambia el estado con las mismas reglas que el listado del panel. */
    public function estado(Request $request, Cita $cita): CitaResource|JsonResponse
    {
        $this->autorizar($request, 'citas.editar');

        $datos = $request->validate([
            'estado' => ['required', 'in:'.implode(',', Cita::ESTADOS)],
        ]);

        if ($cita->estado === 'COMPLETADA' && $datos['estado'] !== 'COMPLETADA') {
            return response()->json([
                'message' => 'Una cita completada ya no puede cambiar de estado.',
                'errors' => ['estado' => ['Una cita completada ya no puede cambiar de estado.']],
            ], 422);
        }

        // Reactivar una cita cancelada exige que su cupo siga libre.
        if ($cita->estado === 'CANCELADA' && $datos['estado'] !== 'CANCELADA') {
            $conflicto = $this->agenda->conflicto(
                $cita->doctor, $cita->fecha->toDateString(), substr((string) $cita->hora, 0, 5),
                $cita->tratamiento?->duracion, $cita->id
            );

            if ($conflicto) {
                $mensaje = "El cupo ya fue tomado por la cita {$conflicto->token}. Reprograma esta cita en otro horario.";

                return response()->json(['message' => $mensaje, 'errors' => ['estado' => [$mensaje]]], 422);
            }
        }

        $cita->update($datos);

        return new CitaResource($cita->load(['paciente', 'doctor.especialidad', 'tratamiento']));
    }

    /** Envía la confirmación al paciente; un fallo de correo no anula la cita. */
    private function notificarPaciente(Cita $cita): void
    {
        if (blank($cita->paciente?->email)) {
            return;
        }

        try {
            Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));
        } catch (\Throwable $e) {
            report($e);
        }
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

    private function porPagina(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }
}
