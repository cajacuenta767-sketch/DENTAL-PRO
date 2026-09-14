<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\ListaEspera;
use App\Models\Paciente;
use App\Models\Tratamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListaEsperaController extends Controller
{
    public function index(Request $request): View
    {
        $entradas = ListaEspera::query()
            ->with(['paciente', 'doctor', 'especialidad', 'tratamiento', 'cita'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->whereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                    ->orWhere('apellidos', 'ilike', $t)
                    ->orWhere('numero_documento', 'ilike', $t));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado), fn ($q) => $q->abiertas())
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($request->filled('prioridad'), fn ($q) => $q->where('prioridad', $request->prioridad))
            ->orderByRaw("CASE prioridad WHEN 'ALTA' THEN 0 WHEN 'NORMAL' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.lista-espera.index', [
            'entradas' => $entradas,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'totales' => [
                'esperando' => ListaEspera::where('estado', 'ESPERANDO')->count(),
                'contactados' => ListaEspera::where('estado', 'CONTACTADO')->count(),
                'altaPrioridad' => ListaEspera::abiertas()->where('prioridad', 'ALTA')->count(),
                'agendadosMes' => ListaEspera::where('estado', 'AGENDADO')
                    ->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return $this->formulario(new ListaEspera([
            'paciente_id' => $request->query('paciente_id'),
            'doctor_id' => $request->query('doctor_id'),
            'preferencia_turno' => 'CUALQUIERA',
            'prioridad' => 'NORMAL',
            'estado' => 'ESPERANDO',
            'fecha_desde' => now()->toDateString(),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        ListaEspera::create($datos + ['usuario_id' => $request->user()->id]);

        return redirect()->route('admin.lista-espera.index')
            ->with('exito', 'El paciente fue añadido a la lista de espera.');
    }

    public function edit(ListaEspera $listaEspera): View
    {
        return $this->formulario($listaEspera);
    }

    public function update(Request $request, ListaEspera $listaEspera): RedirectResponse
    {
        $listaEspera->update($this->validar($request));

        return redirect()->route('admin.lista-espera.index')
            ->with('exito', 'La entrada de la lista de espera fue actualizada.');
    }

    public function destroy(ListaEspera $listaEspera): RedirectResponse
    {
        $listaEspera->delete();

        return back()->with('exito', 'La entrada fue eliminada de la lista de espera.');
    }

    /** Cambio rápido de estado desde el listado (contactado / cancelado). */
    public function cambiarEstado(Request $request, ListaEspera $listaEspera): RedirectResponse
    {
        $estado = $request->validate([
            'estado' => ['required', 'in:'.implode(',', array_keys(ListaEspera::ESTADOS))],
        ])['estado'];

        if ($estado === 'AGENDADO' && ! $listaEspera->cita_id) {
            return back()->with('error', 'Para marcarla como agendada crea la cita desde el botón "Agendar".');
        }

        $listaEspera->update(['estado' => $estado]);

        return back()->with('exito', 'La entrada pasó a '.$listaEspera->estado_legible.'.');
    }

    private function formulario(ListaEspera $entrada): View
    {
        return view('admin.lista-espera.form', [
            'entrada' => $entrada,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(['id', 'nombres', 'apellidos', 'numero_documento']),
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'especialidades' => Especialidad::activas()->orderBy('nombre')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
        ]);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'especialidad_id' => ['nullable', 'exists:especialidades,id'],
            'tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'preferencia_turno' => ['required', 'in:'.implode(',', array_keys(ListaEspera::TURNOS))],
            'prioridad' => ['required', 'in:'.implode(',', array_keys(ListaEspera::PRIORIDADES))],
            'estado' => ['required', 'in:ESPERANDO,CONTACTADO,CANCELADO'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'especialidad_id' => 'especialidad',
            'tratamiento_id' => 'tratamiento',
            'fecha_desde' => 'disponible desde',
            'fecha_hasta' => 'disponible hasta',
            'preferencia_turno' => 'turno preferido',
        ]);
    }
}
