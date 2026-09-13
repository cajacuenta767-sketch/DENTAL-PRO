<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\AgendaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function __construct(private readonly AgendaService $agenda) {}

    /**
     * Agenda diaria. Un doctor ve la suya; el resto del personal elige
     * a quién mirar desde el selector.
     */
    public function index(Request $request): View
    {
        $propio = $request->user()->doctor;

        $doctores = Doctor::activos()->with('especialidad')->orderBy('apellidos')->get();

        $doctor = match (true) {
            $request->filled('doctor_id') => $doctores->firstWhere('id', (int) $request->doctor_id),
            (bool) $propio => $propio,
            default => $doctores->first(),
        };

        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();

        return view('admin.agenda.index', [
            'doctores' => $doctores,
            'doctor' => $doctor,
            'fecha' => $fecha,
            'nombreDia' => $this->agenda->nombreDia($fecha),
            'cupos' => $doctor ? $this->agenda->cupos($doctor, $fecha) : collect(),
            'esAgendaPropia' => $propio && $doctor && $propio->is($doctor),
        ]);
    }

    public function porDoctor(Request $request, Doctor $doctor): View
    {
        return $this->index($request->merge(['doctor_id' => $doctor->id]));
    }
}
