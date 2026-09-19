<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Horario;
use App\Models\Sucursal;
use App\Support\SucursalActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HorarioController extends Controller
{
    public function index(Request $request): View
    {
        $horarios = Horario::query()
            ->with('doctor.especialidad')
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($this->sedeFiltrada($request), fn ($q, $sede) => $q->where('sucursal_id', $sede))
            ->when($request->filled('dia_semana'), fn ($q) => $q->where('dia_semana', $request->dia_semana))
            ->orderBy('doctor_id')
            ->orderByRaw($this->ordenPorDia())
            ->orderBy('hora_inicio')
            ->paginate(20)
            ->withQueryString();

        return view('admin.horarios.index', [
            'horarios' => $horarios,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'dias' => config('odontosuite.dias_semana'),
            'sedes' => Sucursal::orderBy('nombre')->get()->keyBy('id'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.horarios.form', [
            'horario' => new Horario([
                'activo' => true,
                'turno' => 'MAÑANA',
                'doctor_id' => $request->query('doctor_id'),
                'sucursal_id' => SucursalActiva::id(),
            ]),
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'dias' => config('odontosuite.dias_semana'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($this->seSolapa($datos)) {
            return back()->withInput()
                ->with('error', 'Ese doctor ya tiene un horario que se cruza con el rango indicado.');
        }

        Horario::create($datos);

        return redirect()->route('admin.horarios.index')
            ->with('exito', 'El horario fue registrado.');
    }

    public function edit(Horario $horario): View
    {
        return view('admin.horarios.form', [
            'horario' => $horario,
            'doctores' => Doctor::activos()->with('especialidad')->orderBy('apellidos')->get(),
            'dias' => config('odontosuite.dias_semana'),
        ]);
    }

    public function update(Request $request, Horario $horario): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($this->seSolapa($datos, $horario->id)) {
            return back()->withInput()
                ->with('error', 'Ese doctor ya tiene un horario que se cruza con el rango indicado.');
        }

        $horario->update($datos);

        return redirect()->route('admin.horarios.index')
            ->with('exito', 'El horario fue actualizado.');
    }

    public function destroy(Horario $horario): RedirectResponse
    {
        $horario->delete();

        return back()->with('exito', 'El horario fue eliminado.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'doctor_id' => ['required', 'exists:doctores,id'],
            'sucursal_id' => ['nullable', 'exists:sucursales,id'],
            'dia_semana' => ['required', 'in:'.implode(',', Horario::DIAS)],
            'turno' => ['required', 'in:'.implode(',', Horario::TURNOS)],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'doctor_id' => 'doctor',
            'sucursal_id' => 'sede',
            'dia_semana' => 'día de la semana',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
        ]);

        $datos['activo'] = $request->boolean('activo');
        $datos['sucursal_id'] = $datos['sucursal_id'] ?? $this->sedePorDefecto();

        return $datos;
    }

    /** Sin sede elegida se usa la activa; con una sola sede, la principal. */
    private function sedePorDefecto(): ?int
    {
        return SucursalActiva::id() ?? (Sucursal::activas()->count() === 1 ? Sucursal::principal()?->id : null);
    }

    /** La sede activa manda; si se ven todas, aplica el filtro elegido en el listado. */
    private function sedeFiltrada(Request $request): ?int
    {
        return SucursalActiva::id() ?? ($request->filled('sucursal_id') ? (int) $request->sucursal_id : null);
    }

    /**
     * Evita que un doctor tenga dos bloques activos cruzados el mismo día.
     * Un turno desactivado no bloquea; y un bloque nuevo inactivo tampoco.
     */
    private function seSolapa(array $datos, ?int $ignorar = null): bool
    {
        if (isset($datos['activo']) && ! $datos['activo']) {
            return false;
        }

        return Horario::query()
            ->where('doctor_id', $datos['doctor_id'])
            ->where('dia_semana', $datos['dia_semana'])
            ->where('activo', true)
            ->when($ignorar, fn ($q) => $q->where('id', '!=', $ignorar))
            ->where('hora_inicio', '<', $datos['hora_fin'])
            ->where('hora_fin', '>', $datos['hora_inicio'])
            ->exists();
    }

    /** Ordena los días en secuencia natural y no alfabética. */
    private function ordenPorDia(): string
    {
        $casos = collect(Horario::DIAS)
            ->map(fn ($dia, $i) => "WHEN '{$dia}' THEN {$i}")
            ->implode(' ');

        return "CASE dia_semana {$casos} ELSE 7 END";
    }
}
