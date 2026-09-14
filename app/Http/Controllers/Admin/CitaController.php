<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\ListaEspera;
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

class CitaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda) {}

    public function index(Request $request): View
    {
        $citas = Cita::query()
            ->with(['paciente', 'doctor.especialidad', 'tratamiento', 'pagos'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('token', 'ilike', $t)
                    ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                        ->orWhere('apellidos', 'ilike', $t)
                        ->orWhere('numero_documento', 'ilike', $t)));
            })
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->fecha))
            ->orderByDesc('fecha')->orderByDesc('hora')
            ->paginate(15)
            ->withQueryString();

        return view('admin.citas.index', [
            'citas' => $citas,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'totales' => [
                'hoy' => Cita::delDia()->vigentes()->count(),
                'pendientes' => Cita::where('estado', 'PENDIENTE')->count(),
                'confirmadas' => Cita::where('estado', 'CONFIRMADA')->count(),
                'completadas' => Cita::where('estado', 'COMPLETADA')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.citas.form', [
            'cita' => new Cita([
                'estado' => 'PENDIENTE',
                'origen' => 'RECEPCION',
                'fecha' => now()->toDateString(),
                'paciente_id' => $request->query('paciente_id'),
                'doctor_id' => $request->query('doctor_id'),
                'tratamiento_id' => $request->query('tratamiento_id'),
                'fecha' => $request->query('fecha', now()->toDateString()),
                'hora' => $request->query('hora'),
            ]),
            'listaEsperaId' => $request->query('lista_espera_id'),
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
            'horasLibres' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $cita = DB::transaction(function () use ($request, $datos) {
            $cita = Cita::create($datos);

            // Si vino desde la lista de espera, cierra esa entrada.
            if ($request->filled('lista_espera_id')) {
                ListaEspera::whereKey($request->integer('lista_espera_id'))
                    ->where('paciente_id', $cita->paciente_id)
                    ->update(['estado' => 'AGENDADO', 'cita_id' => $cita->id]);
            }

            return $cita;
        });

        $aviso = $this->notificarPaciente($cita);

        return redirect()->route('admin.citas.show', $cita)
            ->with('exito', "Cita registrada con el código {$cita->token}.")
            ->with('aviso', $aviso);
    }

    public function show(Cita $cita): View
    {
        $cita->load(['paciente', 'doctor.especialidad', 'tratamiento', 'historial', 'odontograma', 'pagos.detalles']);

        return view('admin.citas.show', compact('cita'));
    }

    public function edit(Cita $cita): View
    {
        return view('admin.citas.form', [
            'cita' => $cita,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
            'horasLibres' => $this->agenda->horasLibres($cita->doctor, $cita->fecha->toDateString(), $cita->id, $cita->tratamiento?->duracion),
            'listaEsperaId' => null,
        ]);
    }

    public function update(Request $request, Cita $cita): RedirectResponse
    {
        $cita->update($this->validar($request, $cita));

        return redirect()->route('admin.citas.show', $cita)
            ->with('exito', 'La cita fue actualizada.');
    }

    public function destroy(Cita $cita): RedirectResponse
    {
        if ($cita->pagos()->vigentes()->exists()) {
            return back()->with('error', 'No puedes eliminar una cita con recibos activos. Anula primero el recibo.');
        }

        $token = $cita->token;
        $cita->delete();

        return redirect()->route('admin.citas.index')
            ->with('exito', "La cita {$token} fue eliminada.");
    }

    /** Avanza la cita por su flujo de estados desde el listado. */
    public function cambiarEstado(Request $request, Cita $cita): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:'.implode(',', Cita::ESTADOS)],
        ]);

        if ($cita->estado === 'COMPLETADA' && $datos['estado'] !== 'COMPLETADA') {
            return back()->with('error', 'Una cita completada ya no puede cambiar de estado.');
        }

        // Reactivar una cita cancelada exige que su cupo siga libre.
        if ($cita->estado === 'CANCELADA' && $datos['estado'] !== 'CANCELADA') {
            $conflicto = $this->agenda->conflicto(
                $cita->doctor, $cita->fecha->toDateString(), substr((string) $cita->hora, 0, 5),
                $cita->tratamiento?->duracion, $cita->id
            );

            if ($conflicto) {
                return back()->with('error', "El cupo ya fue tomado por la cita {$conflicto->token}. Reprograma esta cita en otro horario.");
            }
        }

        $cita->update($datos);

        return back()->with('exito', "La cita {$cita->token} pasó a {$cita->estado_legible}.");
    }

    public function reenviarCorreo(Cita $cita): RedirectResponse
    {
        if (blank($cita->paciente->email)) {
            return back()->with('error', 'El paciente no tiene un correo registrado.');
        }

        try {
            Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No pudimos enviar el correo. Revisa la configuración de correo del servidor.');
        }

        return back()->with('exito', 'Se reenvió la confirmación al correo del paciente.');
    }

    /** Endpoint consultado por el formulario al elegir doctor y fecha. */
    public function horasDisponibles(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'fecha' => ['required', 'date'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
        ]);

        $doctor = Doctor::findOrFail($datos['doctor_id']);
        $duracion = isset($datos['tratamiento_id']) ? Tratamiento::find($datos['tratamiento_id'])?->duracion : null;

        return response()->json([
            'dia' => $this->agenda->nombreDia($datos['fecha']),
            'horas' => $this->agenda->horasLibres($doctor, $datos['fecha'], $datos['cita_id'] ?? null, $duracion),
        ]);
    }

    private function validar(Request $request, ?Cita $cita = null): array
    {
        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'tratamiento_id' => ['required', 'exists:tratamientos,id'],
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'estado' => ['required', 'in:'.implode(',', Cita::ESTADOS)],
            'origen' => ['required', 'in:RECEPCION,ONLINE'],
            'motivo' => ['nullable', 'string', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'tratamiento_id' => 'tratamiento',
        ]);

        $doctor = Doctor::findOrFail($datos['doctor_id']);
        $duracion = (int) (Tratamiento::find($datos['tratamiento_id'])?->duracion ?: $this->agenda->intervalo());

        if (! $this->agenda->horaValida($doctor, $datos['fecha'], $datos['hora'], $duracion)) {
            throw ValidationException::withMessages([
                'hora' => "El doctor no atiende ese día a esa hora o el tratamiento ({$duracion} min) no cabe en su turno. Revisa sus horarios configurados.",
            ]);
        }

        // Las canceladas no cuentan; las demás bloquean toda su duración.
        if ($datos['estado'] !== 'CANCELADA') {
            $conflicto = $this->agenda->conflicto($doctor, $datos['fecha'], $datos['hora'], $duracion, $cita?->id);

            if ($conflicto) {
                throw ValidationException::withMessages([
                    'hora' => "Ese horario se cruza con la cita {$conflicto->token} (".substr((string) $conflicto->hora, 0, 5).', '.$conflicto->duracion_minutos.' min). Elige otro.',
                ]);
            }
        }

        return $datos;
    }

    /** Envía la confirmación y devuelve un aviso cuando no fue posible. */
    private function notificarPaciente(Cita $cita): ?string
    {
        if (blank($cita->paciente->email)) {
            return 'La cita se guardó, pero el paciente no tiene correo registrado para enviarle la confirmación.';
        }

        try {
            Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));
        } catch (\Throwable $e) {
            report($e);

            return 'La cita se guardó, pero no pudimos enviar el correo de confirmación.';
        }

        return null;
    }
}
