<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Services\AgendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda) {}

    /**
     * Agenda del día: línea de tiempo de turnos con el saludo y la franja
     * de indicadores. Un doctor ve la suya; el resto del personal ve la
     * clínica completa y puede filtrar por profesional.
     */
    public function index(Request $request): View
    {
        $propio = $request->user()->doctor;
        $doctores = Doctor::activos()->with('especialidad')->orderBy('apellidos')->get();

        $doctorFiltro = match (true) {
            $request->filled('doctor_id') => $doctores->firstWhere('id', (int) $request->doctor_id),
            (bool) $propio => $propio,
            default => null,
        };

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
            'proximosDias' => $this->proximosDias($doctorFiltro),
        ]);
    }

    public function porDoctor(Request $request, Doctor $doctor): RedirectResponse
    {
        return redirect()->route('admin.agenda.index', [
            'doctor_id' => $doctor->id,
            'fecha' => $request->query('fecha'),
        ]);
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
