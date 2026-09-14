<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Especialidad;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(Request $request): View
    {
        $doctores = Doctor::query()
            ->with(['especialidad', 'usuario'])
            ->withCount(['citas', 'horarios'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombres', 'ilike', $t)
                    ->orWhere('apellidos', 'ilike', $t)
                    ->orWhere('numero_documento', 'ilike', $t));
            })
            ->when($request->filled('especialidad_id'), fn ($q) => $q->where('especialidad_id', $request->especialidad_id))
            ->orderBy('apellidos')
            ->paginate(12)
            ->withQueryString();

        return view('admin.doctores.index', [
            'doctores' => $doctores,
            'especialidades' => Especialidad::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.doctores.form', [
            'doctor' => new Doctor(['activo' => true, 'tipo_documento' => 'CI', 'genero' => 'M']),
            'especialidades' => Especialidad::activas()->orderBy('nombre')->get(),
            'usuarios' => $this->usuariosDisponibles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('foto')) {
            $datos['fotografia'] = $request->file('foto')->store('doctores', 'public');
        }

        $doctor = Doctor::create(collect($datos)->except('foto')->all());

        return redirect()->route('admin.doctores.show', $doctor)
            ->with('exito', "El doctor {$doctor->nombre_completo} fue registrado.");
    }

    public function show(Doctor $doctor): View
    {
        $doctor->load(['especialidad', 'usuario', 'horarios' => fn ($q) => $q->orderBy('hora_inicio')]);

        return view('admin.doctores.show', [
            'doctor' => $doctor,
            'proximasCitas' => $doctor->citas()
                ->with(['paciente', 'tratamiento'])
                ->whereDate('fecha', '>=', now()->toDateString())
                ->vigentes()
                ->orderBy('fecha')->orderBy('hora')
                ->limit(10)
                ->get(),
            'atendidas' => $doctor->citas()->where('estado', 'COMPLETADA')->count(),
            'recaudado' => (float) $doctor->pagos()->vigentes()->sum('monto_pagado'),
        ]);
    }

    public function edit(Doctor $doctor): View
    {
        return view('admin.doctores.form', [
            'doctor' => $doctor,
            'especialidades' => Especialidad::orderBy('nombre')->get(),
            'usuarios' => $this->usuariosDisponibles($doctor),
        ]);
    }

    public function update(Request $request, Doctor $doctor): RedirectResponse
    {
        $datos = $this->validar($request, $doctor->id);

        if ($request->hasFile('foto')) {
            if ($doctor->fotografia) {
                Storage::disk('public')->delete($doctor->fotografia);
            }
            $datos['fotografia'] = $request->file('foto')->store('doctores', 'public');
        }

        $doctor->update(collect($datos)->except('foto')->all());

        return redirect()->route('admin.doctores.show', $doctor)
            ->with('exito', "Los datos de {$doctor->nombre_completo} fueron actualizados.");
    }

    public function destroy(Doctor $doctor): RedirectResponse
    {
        if ($doctor->citas()->exists()) {
            return back()->with('error', 'No puedes eliminar un doctor con citas registradas. Desactívalo en su lugar.');
        }

        if ($doctor->fotografia) {
            Storage::disk('public')->delete($doctor->fotografia);
        }

        $nombre = $doctor->nombre_completo;
        $doctor->delete();

        return redirect()->route('admin.doctores.index')
            ->with('exito', "El doctor {$nombre} fue eliminado.");
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $datos = $request->validate([
            'usuario_id' => ['nullable', 'exists:usuarios,id'],
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', 'in:CI,DNI,PASAPORTE,CE'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:doctores,numero_documento'.($ignorar ? ",{$ignorar}" : '')],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero' => ['required', 'in:M,F,O'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'colegiatura' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
            'porcentaje_comision' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'especialidad_id' => 'especialidad',
            'porcentaje_comision' => 'porcentaje de comisión',
            'usuario_id' => 'usuario del sistema',
            'numero_documento' => 'número de documento',
        ]);

        $datos['activo'] = $request->boolean('activo');
        $datos['porcentaje_comision'] = round((float) ($datos['porcentaje_comision'] ?? 0), 2);

        return $datos;
    }

    /** Usuarios con rol DOCTOR que todavía no están enlazados a una ficha. */
    private function usuariosDisponibles(?Doctor $actual = null)
    {
        return Usuario::query()
            ->role('DOCTOR')
            ->where(function ($q) use ($actual) {
                $q->whereDoesntHave('doctor');
                if ($actual?->usuario_id) {
                    $q->orWhere('id', $actual->usuario_id);
                }
            })
            ->orderBy('nombre')
            ->get();
    }
}
