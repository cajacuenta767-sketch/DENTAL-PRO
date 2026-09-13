<?php

namespace App\Http\Controllers;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Reserva de turnos sin cuenta, desde el enlace o el QR que la clínica
 * publica. El token de la URL es la única credencial.
 */
class ReservaPublicaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda) {}

    public function formulario(string $token): View
    {
        $ajustes = $this->clinicaAbierta($token);

        return view('publico.reservar', [
            'ajustes' => $ajustes,
            'token' => $token,
            'especialidades' => Especialidad::activas()
                ->whereHas('tratamientos', fn ($q) => $q->where('activo', true))
                ->whereHas('doctores', fn ($q) => $q->where('activo', true))
                ->orderBy('nombre')
                ->get(),
            'minimo' => now()->addHours((int) $ajustes->reservas_minimo_horas)->toDateString(),
            'maximo' => now()->addDays((int) $ajustes->reservas_anticipacion_dias)->toDateString(),
        ]);
    }

    /** Doctores y tratamientos de una especialidad, para los selectores. */
    public function opciones(Request $request, string $token): JsonResponse
    {
        $this->clinicaAbierta($token);

        $especialidad = $request->validate([
            'especialidad_id' => ['required', 'exists:especialidades,id'],
        ])['especialidad_id'];

        return response()->json([
            'doctores' => Doctor::activos()
                ->where('especialidad_id', $especialidad)
                ->orderBy('apellidos')
                ->get()
                ->map(fn ($d) => ['id' => $d->id, 'nombre' => $d->nombre_profesional]),
            'tratamientos' => Tratamiento::activos()
                ->where('especialidad_id', $especialidad)
                ->orderBy('nombre')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'nombre' => $t->nombre,
                    'duracion' => $t->duracion,
                    'precio' => (float) $t->precio,
                ]),
        ]);
    }

    public function horas(Request $request, string $token): JsonResponse
    {
        $ajustes = $this->clinicaAbierta($token);

        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'fecha' => ['required', 'date'],
        ]);

        $doctor = Doctor::activos()->findOrFail($datos['doctor_id']);
        $horas = $this->agenda->horasLibres($doctor, $datos['fecha']);

        return response()->json([
            'dia' => $this->agenda->nombreDia($datos['fecha']),
            'horas' => $this->filtrarPorAnticipacion($horas, $datos['fecha'], $ajustes),
        ]);
    }

    public function reservar(Request $request, string $token): RedirectResponse
    {
        $ajustes = $this->clinicaAbierta($token);

        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', 'in:CI,DNI,PASAPORTE,CE'],
            'numero_documento' => ['required', 'string', 'max:20'],
            'telefono' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'tratamiento_id' => ['required', 'exists:tratamientos,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [], [
            'numero_documento' => 'número de documento',
            'doctor_id' => 'profesional',
            'tratamiento_id' => 'motivo de consulta',
        ]);

        $this->verificarVentana($datos['fecha'], $datos['hora'], $ajustes);

        $doctor = Doctor::activos()->findOrFail($datos['doctor_id']);

        if (! $this->agenda->horaValida($doctor, $datos['fecha'], $datos['hora'])) {
            throw ValidationException::withMessages([
                'hora' => 'Ese profesional no atiende en el horario elegido.',
            ]);
        }

        $cita = DB::transaction(function () use ($datos, $doctor) {
            // Dentro de la transacción para que dos reservas simultáneas
            // no puedan tomar el mismo cupo.
            $ocupado = Cita::where('doctor_id', $doctor->id)
                ->whereDate('fecha', $datos['fecha'])
                ->where('hora', $datos['hora'])
                ->vigentes()
                ->lockForUpdate()
                ->exists();

            if ($ocupado) {
                throw ValidationException::withMessages([
                    'hora' => 'Alguien tomó ese horario mientras completabas el formulario. Elige otro, por favor.',
                ]);
            }

            $paciente = Paciente::firstOrNew(['numero_documento' => $datos['numero_documento']]);

            // A un paciente ya registrado solo le completamos lo que falte.
            $paciente->fill([
                'nombres' => $paciente->exists ? $paciente->nombres : $datos['nombres'],
                'apellidos' => $paciente->exists ? $paciente->apellidos : $datos['apellidos'],
                'tipo_documento' => $datos['tipo_documento'],
                'telefono' => $datos['telefono'] ?: $paciente->telefono,
                'email' => $datos['email'] ?: $paciente->email,
                'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? $paciente->fecha_nacimiento,
                'activo' => true,
            ])->save();

            return Cita::create([
                'paciente_id' => $paciente->id,
                'doctor_id' => $doctor->id,
                'tratamiento_id' => $datos['tratamiento_id'],
                'fecha' => $datos['fecha'],
                'hora' => $datos['hora'],
                'estado' => 'PENDIENTE',
                'origen' => 'ONLINE',
                'motivo' => $datos['motivo'] ?? null,
            ]);
        });

        $this->notificar($cita);

        return redirect()->route('reservas.confirmacion', [$token, $cita->token]);
    }

    public function confirmacion(string $token, string $codigo): View
    {
        $ajustes = $this->clinicaAbierta($token);

        $cita = Cita::with(['paciente', 'doctor.especialidad', 'tratamiento'])
            ->where('token', $codigo)
            ->firstOrFail();

        return view('publico.reserva-confirmada', compact('ajustes', 'cita', 'token'));
    }

    /** Valida el token y que la clínica tenga las reservas abiertas. */
    private function clinicaAbierta(string $token): Ajuste
    {
        $ajustes = Ajuste::actual();

        abort_unless(
            $ajustes->reservas_online && $ajustes->reservas_token && hash_equals($ajustes->reservas_token, $token),
            404
        );

        return $ajustes;
    }

    /** Descarta los cupos que caen dentro de la anticipación mínima. */
    private function filtrarPorAnticipacion(array $horas, string $fecha, Ajuste $ajustes): array
    {
        $limite = now()->addHours((int) $ajustes->reservas_minimo_horas);

        return array_values(array_filter(
            $horas,
            fn ($hora) => \Illuminate\Support\Carbon::parse("{$fecha} {$hora}")->greaterThanOrEqualTo($limite)
        ));
    }

    private function verificarVentana(string $fecha, string $hora, Ajuste $ajustes): void
    {
        $momento = \Illuminate\Support\Carbon::parse("{$fecha} {$hora}");

        if ($momento->lessThan(now()->addHours((int) $ajustes->reservas_minimo_horas))) {
            throw ValidationException::withMessages([
                'fecha' => "Las reservas en línea requieren al menos {$ajustes->reservas_minimo_horas} horas de anticipación.",
            ]);
        }

        if ($momento->greaterThan(now()->addDays((int) $ajustes->reservas_anticipacion_dias))) {
            throw ValidationException::withMessages([
                'fecha' => "Solo puedes reservar hasta {$ajustes->reservas_anticipacion_dias} días por adelantado.",
            ]);
        }
    }

    private function notificar(Cita $cita): void
    {
        if (blank($cita->paciente->email)) {
            return;
        }

        try {
            Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));
        } catch (\Throwable $e) {
            // La reserva ya quedó tomada; el correo no debe hacerla fallar.
            report($e);
        }
    }
}
