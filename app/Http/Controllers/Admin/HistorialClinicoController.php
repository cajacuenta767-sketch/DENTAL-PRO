<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\HistorialClinico;
use App\Models\Paciente;
use App\Rules\CitaDelPaciente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class HistorialClinicoController extends Controller
{
    public function index(Paciente $paciente): View
    {
        return view('admin.historiales.index', [
            'paciente' => $paciente,
            'historiales' => $paciente->historiales()
                ->with(['doctor.especialidad', 'cita.tratamiento'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        return view('admin.historiales.form', [
            'paciente' => $paciente,
            'historial' => new HistorialClinico([
                'fecha' => now()->toDateString(),
                'cita_id' => $request->query('cita_id'),
                'doctor_id' => $request->user()->doctor?->id,
            ]),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $this->citasDisponibles($paciente),
            'plantillas' => config('evolucion.plantillas', []),
            'anestesicos' => config('evolucion.anestesicos', []),
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request, $paciente);
        $datos['paciente_id'] = $paciente->id;

        HistorialClinico::create($datos);

        return redirect()->route('admin.historiales.index', $paciente)
            ->with('exito', 'La consulta fue registrada en la historia clínica.');
    }

    public function edit(HistorialClinico $historial): View
    {
        return view('admin.historiales.form', [
            'paciente' => $historial->paciente,
            'historial' => $historial,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $this->citasDisponibles($historial->paciente, $historial),
            'plantillas' => config('evolucion.plantillas', []),
            'anestesicos' => config('evolucion.anestesicos', []),
        ]);
    }

    public function update(Request $request, HistorialClinico $historial): RedirectResponse
    {
        $historial->update($this->validar($request, $historial->paciente));

        return redirect()->route('admin.historiales.index', $historial->paciente)
            ->with('exito', 'La consulta fue actualizada.');
    }

    public function destroy(HistorialClinico $historial): RedirectResponse
    {
        $paciente = $historial->paciente;
        $historial->delete();

        return redirect()->route('admin.historiales.index', $paciente)
            ->with('exito', 'La consulta fue eliminada de la historia clínica.');
    }

    public function pdf(HistorialClinico $historial): Response
    {
        $historial->load(['paciente', 'doctor.especialidad', 'cita.tratamiento']);

        return Pdf::loadView('pdf.historial', [
            'historial' => $historial,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->download('historia-clinica-'.$historial->paciente->numero_documento.'-'.$historial->fecha->format('Ymd').'.pdf');
    }

    private function validar(Request $request, Paciente $paciente): array
    {
        return $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id', new CitaDelPaciente($paciente->id)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'motivo_consulta' => ['required', 'string', 'max:2000'],
            'sintomas' => ['nullable', 'string', 'max:2000'],
            'diagnostico' => ['required', 'string', 'max:2000'],
            'tratamiento_realizado' => ['nullable', 'string', 'max:2000'],
            'prescripcion_receta' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'plantilla' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('evolucion.plantillas', [])))],
            'anestesia' => ['nullable', 'string', 'max:120'],
            'anestesia_cantidad' => ['nullable', 'string', 'max:40'],
            'medicacion' => ['nullable', 'string', 'max:2000'],
            'proxima_cita_indicaciones' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'doctor_id' => 'doctor',
            'cita_id' => 'cita',
            'motivo_consulta' => 'motivo de consulta',
            'diagnostico' => 'diagnóstico',
            'tratamiento_realizado' => 'tratamiento realizado',
            'prescripcion_receta' => 'prescripción',
            'plantilla' => 'plantilla de nota',
            'anestesia' => 'anestésico',
            'anestesia_cantidad' => 'cantidad de anestésico',
            'medicacion' => 'medicación',
            'proxima_cita_indicaciones' => 'indicaciones para la próxima cita',
        ]);
    }

    /** Citas del paciente que todavía no tienen una consulta enlazada. */
    private function citasDisponibles(Paciente $paciente, ?HistorialClinico $actual = null)
    {
        return Cita::query()
            ->where('paciente_id', $paciente->id)
            ->with(['doctor', 'tratamiento'])
            ->where(function ($q) use ($actual) {
                $q->whereDoesntHave('historial');
                if ($actual?->cita_id) {
                    $q->orWhere('id', $actual->cita_id);
                }
            })
            ->orderByDesc('fecha')
            ->limit(30)
            ->get();
    }
}
