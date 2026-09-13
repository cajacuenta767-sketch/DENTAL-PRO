<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmacionMail;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ]),
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
            'horasLibres' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $cita = Cita::create($datos);

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
            'horasLibres' => $this->agenda->horasLibres($cita->doctor, $cita->fecha->toDateString(), $cita->id),
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

        $cita->update($datos);

        return back()->with('exito', "La cita {$cita->token} pasó a {$cita->estado_legible}.");
    }

    public function reenviarCorreo(Cita $cita): RedirectResponse
    {
        if (blank($cita->paciente->email)) {
            return back()->with('error', 'El paciente no tiene un correo registrado.');
        }

        Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita));

        return back()->with('exito', 'Se reenvió la confirmación al correo del paciente.');
    }

    /** Endpoint consultado por el formulario al elegir doctor y fecha. */
    public function horasDisponibles(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'fecha' => ['required', 'date'],
            'cita_id' => ['nullable', 'exists:citas,id'],
        ]);

        $doctor = Doctor::findOrFail($datos['doctor_id']);

        return response()->json([
            'dia' => $this->agenda->nombreDia($datos['fecha']),
            'horas' => $this->agenda->horasLibres($doctor, $datos['fecha'], $datos['cita_id'] ?? null),
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

        if (! $this->agenda->horaValida($doctor, $datos['fecha'], $datos['hora'])) {
            throw ValidationException::withMessages([
                'hora' => 'El doctor no atiende ese día a esa hora. Revisa sus horarios configurados.',
            ]);
        }

        $ocupado = Cita::query()
            ->where('doctor_id', $datos['doctor_id'])
            ->whereDate('fecha', $datos['fecha'])
            ->where('hora', $datos['hora'])
            ->vigentes()
            ->when($cita, fn ($q) => $q->where('id', '!=', $cita->id))
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages([
                'hora' => 'Ese cupo ya está tomado por otra cita. Elige otro horario.',
            ]);
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
