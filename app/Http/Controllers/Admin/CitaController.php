<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CitaConfirmacionMail;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\ListaEspera;
use App\Models\Paciente;
use App\Models\Tratamiento;
use App\Services\AgendaService;
use App\Support\SucursalActiva;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        $datos['sucursal_id'] = SucursalActiva::id();

        $serie = $request->validate([
            'repetir' => ['nullable', 'in:semanal,quincenal,mensual'],
            'repeticiones' => ['nullable', 'integer', 'min:1', 'max:52'],
        ]);

        $repetir = $serie['repetir'] ?? null;
        $repeticiones = $repetir ? (int) ($serie['repeticiones'] ?? 0) : 0;
        $omitidas = [];
        $creadas = 0;

        $cita = DB::transaction(function () use ($request, $datos, $repetir, $repeticiones, &$omitidas, &$creadas) {
            if ($repeticiones > 0) {
                $datos['serie_id'] = (string) Str::uuid();
            }

            $cita = Cita::create($datos);

            // Si vino desde la lista de espera, cierra esa entrada.
            if ($request->filled('lista_espera_id')) {
                ListaEspera::whereKey($request->integer('lista_espera_id'))
                    ->where('paciente_id', $cita->paciente_id)
                    ->update(['estado' => 'AGENDADO', 'cita_id' => $cita->id]);
            }

            if ($repeticiones > 0) {
                [$creadas, $omitidas] = $this->crearSerie($cita, $datos, $repetir, $repeticiones);
            }

            return $cita;
        });

        $avisos = array_filter([
            $this->notificarPaciente($cita),
            $omitidas === [] ? null : sprintf(
                'No se pudieron crear %d %s: %s.',
                count($omitidas), count($omitidas) === 1 ? 'cita' : 'citas', implode(', ', $omitidas)
            ),
        ]);

        $exito = "Cita registrada con el código {$cita->token}.";

        if ($creadas > 0) {
            $exito .= " Se agendaron {$creadas} citas más de la serie.";
        }

        return redirect()->route('admin.citas.show', $cita)
            ->with('exito', $exito)
            ->with('aviso', $avisos === [] ? null : implode(' ', $avisos));
    }

    /**
     * Genera las citas siguientes de una serie recurrente a la misma hora.
     * Las que no caben en el turno o chocan con otra cita se omiten y se
     * devuelven para avisar a recepción.
     *
     * @return array{0: int, 1: array<int, string>}
     */
    private function crearSerie(Cita $original, array $datos, string $repetir, int $repeticiones): array
    {
        $doctor = $original->doctor;
        $duracion = $original->duracion_minutos;
        $base = CarbonImmutable::parse($datos['fecha']);
        $creadas = 0;
        $omitidas = [];

        for ($i = 1; $i <= $repeticiones; $i++) {
            $fecha = match ($repetir) {
                'semanal' => $base->addWeeks($i),
                'quincenal' => $base->addWeeks(2 * $i),
                'mensual' => $base->addMonthsNoOverflow($i),
            };

            $fechaTexto = $fecha->toDateString();

            if (! $this->agenda->horaValida($doctor, $fechaTexto, $datos['hora'], $duracion)) {
                $omitidas[] = $fecha->format('d/m').' (fuera de horario)';

                continue;
            }

            if ($this->agenda->conflicto($doctor, $fechaTexto, $datos['hora'], $duracion)) {
                $omitidas[] = $fecha->format('d/m').' (cupo ocupado)';

                continue;
            }

            Cita::create(array_merge($datos, ['fecha' => $fechaTexto]));
            $creadas++;
        }

        return [$creadas, $omitidas];
    }

    public function show(Cita $cita): View
    {
        $cita->load(['paciente', 'doctor.especialidad', 'tratamiento', 'historial', 'odontograma', 'pagos.detalles']);

        $serie = $cita->serie_id
            ? Cita::where('serie_id', $cita->serie_id)->orderBy('fecha')->orderBy('hora')->get()
            : collect();

        return view('admin.citas.show', compact('cita', 'serie'));
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

    /**
     * Mueve la cita a otro día y hora desde la agenda semanal (arrastrar y
     * soltar). Responde JSON: 200 con la nueva posición o 422 con el motivo.
     */
    public function reprogramar(Request $request, Cita $cita): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
        ]);

        if (in_array($cita->estado, ['COMPLETADA', 'CANCELADA'], true)) {
            return response()->json([
                'message' => "Una cita {$cita->estado_legible} no se puede reprogramar.",
            ], 422);
        }

        $fecha = CarbonImmutable::parse($datos['fecha'])->toDateString();
        $duracion = $cita->duracion_minutos;

        if (! $this->agenda->horaValida($cita->doctor, $fecha, $datos['hora'], $duracion)) {
            return response()->json([
                'message' => "El doctor no atiende ese día a esa hora o la cita ({$duracion} min) no cabe en su turno.",
            ], 422);
        }

        $conflicto = $this->agenda->conflicto($cita->doctor, $fecha, $datos['hora'], $duracion, $cita->id);

        if ($conflicto) {
            return response()->json([
                'message' => "Ese horario se cruza con la cita {$conflicto->token} (".substr((string) $conflicto->hora, 0, 5).', '.$conflicto->duracion_minutos.' min).',
            ], 422);
        }

        $anterior = $cita->fecha_hora;
        $nuevo = CarbonImmutable::parse($fecha)->format('d/m/Y').' '.$datos['hora'];
        $nota = "Reprogramada de {$anterior} a {$nuevo} por {$request->user()->nombre}";

        $cita->fecha = $fecha;
        $cita->hora = $datos['hora'];
        $cita->observacion = mb_substr(trim(($cita->observacion ? $cita->observacion."\n" : '').$nota), 0, 1000);
        $cita->save();

        Auditoria::registrar('ACTUALIZAR', $cita, $nota, ['de' => $anterior, 'a' => $nuevo]);

        return response()->json([
            'ok' => true,
            'fecha' => $fecha,
            'hora' => $datos['hora'],
            'fecha_hora' => $cita->fresh()->fecha_hora,
        ]);
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
