<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Horario;
use App\Services\AgendaService;
use App\Support\SucursalActiva;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AgendaController extends Controller
{
    /** Alto en píxeles de cada franja de la cuadrícula semanal. */
    private const ALTO_FRANJA = 44;

    public function __construct(private readonly AgendaService $agenda) {}

    /**
     * Agenda del día: línea de tiempo de turnos con el saludo y la franja
     * de indicadores. Un doctor ve la suya; el resto del personal ve la
     * clínica completa y puede filtrar por profesional.
     */
    public function index(Request $request): View
    {
        ['propio' => $propio, 'doctores' => $doctores, 'doctor' => $doctorFiltro, 'puedeVerTodas' => $puedeVerTodas]
            = $this->contextoDoctor($request);

        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();

        $turnos = Cita::query()
            ->with(['paciente.aseguradora', 'doctor.especialidad', 'tratamiento', 'pagos'])
            ->whereDate('fecha', $fecha)
            ->when($doctorFiltro, fn ($q) => $q->where('doctor_id', $doctorFiltro->id))
            ->orderBy('hora')
            ->get();

        return view('admin.agenda.index', [
            'doctores' => $doctores,
            'doctor' => $doctorFiltro,
            'fecha' => $fecha,
            'esHoy' => $fecha === now()->toDateString(),
            'nombreDia' => $this->agenda->nombreDia($fecha),
            'turnos' => $turnos,
            'saludo' => $this->saludo(),
            'resumen' => [
                'turnos' => $turnos->count(),
                'pacientes' => $turnos->pluck('paciente_id')->unique()->count(),
                'porConfirmar' => $turnos->where('estado', 'PENDIENTE')->count(),
                'profesionales' => $turnos->pluck('doctor_id')->unique()->count(),
                'atendidos' => $turnos->where('estado', 'COMPLETADA')->count(),
            ],
            // Los cupos solo tienen sentido mirando a un profesional concreto.
            'cupos' => $doctorFiltro ? $this->agenda->cupos($doctorFiltro, $fecha) : collect(),
            'esAgendaPropia' => $propio && $doctorFiltro && $propio->is($doctorFiltro),
            'puedeVerTodas' => $puedeVerTodas,
            'proximosDias' => $this->proximosDias($doctorFiltro),
        ]);
    }

    /**
     * Agenda semanal: cuadrícula de días por franjas horarias en la que
     * cada cita es una tarjeta que se puede arrastrar a otro cupo.
     */
    public function semana(Request $request): View
    {
        ['doctores' => $doctores, 'doctor' => $doctorFiltro, 'puedeVerTodas' => $puedeVerTodas]
            = $this->contextoDoctor($request);

        $referencia = CarbonImmutable::parse($request->date('fecha')?->toDateString() ?? now()->toDateString());
        $lunes = $referencia->startOfWeek(CarbonImmutable::MONDAY);

        $horarios = Horario::query()
            ->activos()
            ->when($doctorFiltro, fn ($q) => $q->where('doctor_id', $doctorFiltro->id))
            ->get(['dia_semana', 'hora_inicio', 'hora_fin']);

        $incluyeDomingo = $horarios->contains('dia_semana', 'DOMINGO');
        $dias = collect(range(0, $incluyeDomingo ? 6 : 5))->map(fn (int $i) => $lunes->addDays($i));
        $fin = $dias->last();

        $intervalo = $this->agenda->intervalo();
        [$horaMinima, $horaMaxima] = $this->rangoHorario($horarios, $intervalo);

        $franjas = [];
        for ($cursor = $horaMinima; $cursor < $horaMaxima; $cursor += $intervalo) {
            $franjas[] = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
        }

        $citas = Cita::query()
            ->with(['paciente:id,nombres,apellidos', 'doctor:id,nombres,apellidos,genero', 'tratamiento:id,nombre,duracion'])
            ->whereBetween('fecha', [$lunes->toDateString(), $fin->toDateString()])
            ->when($doctorFiltro, fn ($q) => $q->where('doctor_id', $doctorFiltro->id))
            ->deSucursal(SucursalActiva::id())
            ->orderBy('fecha')->orderBy('hora')
            ->get();

        $porDia = $citas
            ->groupBy(fn (Cita $c) => $c->fecha->toDateString())
            ->map(fn (Collection $delDia) => $this->posicionarCitas($delDia, $horaMinima, $intervalo));

        return view('admin.agenda.semana', [
            'doctores' => $doctores,
            'doctor' => $doctorFiltro,
            'puedeVerTodas' => $puedeVerTodas,
            'fecha' => $referencia->toDateString(),
            'lunes' => $lunes,
            'fin' => $fin,
            'dias' => $dias,
            'franjas' => $franjas,
            'intervalo' => $intervalo,
            'altoFranja' => self::ALTO_FRANJA,
            'horaMinima' => $horaMinima,
            'porDia' => $porDia,
            'totalCitas' => $citas->count(),
            'esSemanaActual' => now()->between($lunes->startOfDay(), $lunes->addDays(6)->endOfDay()),
        ]);
    }

    /**
     * Calendario mensual con el conteo de citas por estado en cada día y
     * las primeras citas de la jornada. Cada celda lleva a la agenda diaria.
     */
    public function mes(Request $request): View
    {
        ['doctores' => $doctores, 'doctor' => $doctorFiltro, 'puedeVerTodas' => $puedeVerTodas]
            = $this->contextoDoctor($request);

        $referencia = CarbonImmutable::parse($request->date('fecha')?->toDateString() ?? now()->toDateString())->startOfMonth();
        $inicioGrilla = $referencia->startOfWeek(CarbonImmutable::MONDAY);
        $finGrilla = $referencia->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $citas = Cita::query()
            ->with(['paciente:id,nombres,apellidos'])
            ->whereBetween('fecha', [$inicioGrilla->toDateString(), $finGrilla->toDateString()])
            ->when($doctorFiltro, fn ($q) => $q->where('doctor_id', $doctorFiltro->id))
            ->deSucursal(SucursalActiva::id())
            ->orderBy('fecha')->orderBy('hora')
            ->get()
            ->groupBy(fn (Cita $c) => $c->fecha->toDateString());

        $semanas = [];
        for ($dia = $inicioGrilla; $dia <= $finGrilla; $dia = $dia->addWeek()) {
            $semanas[] = collect(range(0, 6))->map(function (int $i) use ($dia, $citas, $referencia) {
                $fecha = $dia->addDays($i);
                $delDia = $citas->get($fecha->toDateString(), collect());

                return [
                    'fecha' => $fecha,
                    'delMes' => $fecha->month === $referencia->month,
                    'esHoy' => $fecha->isToday(),
                    'total' => $delDia->count(),
                    'porEstado' => $delDia->countBy('estado')->sortKeys(),
                    'primeras' => $delDia->take(3),
                    'restantes' => max(0, $delDia->count() - 3),
                ];
            });
        }

        return view('admin.agenda.mes', [
            'doctores' => $doctores,
            'doctor' => $doctorFiltro,
            'puedeVerTodas' => $puedeVerTodas,
            'fecha' => $referencia->toDateString(),
            'mes' => $referencia,
            'semanas' => $semanas,
            'totalCitas' => $citas->flatten()->count(),
            'esMesActual' => $referencia->isSameMonth(now()),
        ]);
    }

    public function porDoctor(Request $request, Doctor $doctor): RedirectResponse
    {
        return redirect()->route('admin.agenda.index', [
            'doctor_id' => $doctor->id,
            'fecha' => $request->query('fecha'),
        ]);
    }

    /**
     * Resuelve qué profesional se está mirando. Sin "agenda.todos", un
     * doctor solo mira su propia agenda; el resto elige desde el selector.
     *
     * @return array{propio: ?Doctor, doctores: Collection, doctor: ?Doctor, puedeVerTodas: bool}
     */
    private function contextoDoctor(Request $request): array
    {
        $propio = $request->user()->doctor;
        $doctores = Doctor::activos()->with('especialidad')->orderBy('apellidos')->get();
        $puedeVerTodas = $request->user()->can('agenda.todos') || ! $propio;

        $doctorFiltro = match (true) {
            ! $puedeVerTodas => $propio,
            $request->filled('doctor_id') => $doctores->firstWhere('id', (int) $request->doctor_id),
            (bool) $propio => $propio,
            default => null,
        };

        return [
            'propio' => $propio,
            'doctores' => $doctores,
            'doctor' => $doctorFiltro,
            'puedeVerTodas' => $puedeVerTodas,
        ];
    }

    /**
     * Minutos desde medianoche del primer y último cupo de la semana,
     * redondeados al intervalo. Sin horarios configurados se muestra una
     * jornada estándar para que la cuadrícula no quede vacía.
     *
     * @return array{0: int, 1: int}
     */
    private function rangoHorario(Collection $horarios, int $intervalo): array
    {
        if ($horarios->isEmpty()) {
            return [8 * 60, 18 * 60];
        }

        $minimo = $horarios->min(fn ($h) => $this->minutos((string) $h->hora_inicio));
        $maximo = $horarios->max(fn ($h) => $this->minutos((string) $h->hora_fin));

        $minimo = intdiv($minimo, $intervalo) * $intervalo;
        $maximo = (int) ceil($maximo / $intervalo) * $intervalo;

        return [$minimo, max($maximo, $minimo + $intervalo)];
    }

    private function minutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hora, 0, 5)));

        return $h * 60 + $m;
    }

    /**
     * Calcula la posición vertical, el alto y el carril de cada tarjeta del
     * día. Las citas que se cruzan (varios doctores) se reparten en carriles.
     */
    private function posicionarCitas(Collection $citas, int $horaMinima, int $intervalo): Collection
    {
        $finCarriles = [];

        return $citas->sortBy(fn (Cita $c) => $c->inicio->getTimestamp())->values()->map(function (Cita $cita) use (&$finCarriles, $horaMinima, $intervalo) {
            $inicio = $this->minutos((string) $cita->hora);
            $duracion = $cita->duracion_minutos;

            $carril = null;
            foreach ($finCarriles as $indice => $fin) {
                if ($fin <= $inicio) {
                    $carril = $indice;
                    break;
                }
            }
            $carril ??= count($finCarriles);
            $finCarriles[$carril] = $inicio + $duracion;

            return [
                'cita' => $cita,
                'top' => max(0, ($inicio - $horaMinima) / $intervalo) * self::ALTO_FRANJA,
                'alto' => max(1, $duracion / $intervalo) * self::ALTO_FRANJA,
                'carril' => $carril,
                'carriles' => 1, // se completa abajo
            ];
        })->pipe(function (Collection $posicionadas) use (&$finCarriles) {
            $total = max(1, count($finCarriles));

            return $posicionadas->map(function (array $p) use ($total) {
                $p['carriles'] = $total;

                return $p;
            });
        });
    }

    private function saludo(): string
    {
        return match (true) {
            now()->hour < 12 => 'Buenos días',
            now()->hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    }

    /** Carga de los próximos siete días para el panel lateral. */
    private function proximosDias(?Doctor $doctor)
    {
        return Cita::query()
            ->selectRaw('fecha, COUNT(*) AS total')
            ->whereBetween('fecha', [now()->addDay()->toDateString(), now()->addDays(7)->toDateString()])
            ->vigentes()
            ->when($doctor, fn ($q) => $q->where('doctor_id', $doctor->id))
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get();
    }
}
