<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Paciente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PacienteController extends Controller
{
    public function index(Request $request): View
    {
        $pacientes = Paciente::query()
            ->withCount('citas')
            ->buscar($request->buscar)
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activo'))
            ->orderBy('apellidos')->orderBy('nombres')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pacientes.index', [
            'pacientes' => $pacientes,
            'totales' => [
                'registrados' => Paciente::count(),
                'activos' => Paciente::activos()->count(),
                'nuevosMes' => Paciente::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'conSaldo' => Paciente::whereHas('pagos', fn ($q) => $q->vigentes()->where('monto_saldo', '>', 0))->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.pacientes.form', [
            'paciente' => new Paciente(['activo' => true, 'tipo_documento' => 'CI', 'genero' => 'M']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('foto')) {
            $datos['fotografia'] = $request->file('foto')->store('pacientes', 'public');
        }

        $paciente = Paciente::create(collect($datos)->except('foto')->all());

        return redirect()->route('admin.pacientes.show', $paciente)
            ->with('exito', "El paciente {$paciente->nombre_completo} fue registrado.");
    }

    public function show(Paciente $paciente): View
    {
        $paciente->load([
            'citas' => fn ($q) => $q->with(['doctor', 'tratamiento'])->orderByDesc('fecha')->orderByDesc('hora')->limit(10),
            'historiales' => fn ($q) => $q->with('doctor')->orderByDesc('fecha')->limit(5),
            'odontogramas' => fn ($q) => $q->orderByDesc('fecha')->limit(3),
            'pagos' => fn ($q) => $q->orderByDesc('fecha_pago')->limit(10),
        ]);

        return view('admin.pacientes.show', [
            'paciente' => $paciente,
            'totalCitas' => $paciente->citas()->count(),
            'totalPagado' => (float) $paciente->pagos()->vigentes()->sum('monto_pagado'),
            'saldo' => $paciente->saldo_pendiente,
        ]);
    }

    public function edit(Paciente $paciente): View
    {
        return view('admin.pacientes.form', compact('paciente'));
    }

    public function update(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request, $paciente->id);

        if ($request->hasFile('foto')) {
            if ($paciente->fotografia) {
                Storage::disk('public')->delete($paciente->fotografia);
            }
            $datos['fotografia'] = $request->file('foto')->store('pacientes', 'public');
        }

        $paciente->update(collect($datos)->except('foto')->all());

        return redirect()->route('admin.pacientes.show', $paciente)
            ->with('exito', 'La ficha del paciente fue actualizada.');
    }

    public function destroy(Paciente $paciente): RedirectResponse
    {
        if ($paciente->pagos()->exists()) {
            return back()->with('error', 'No puedes eliminar un paciente con recibos emitidos. Desactívalo en su lugar.');
        }

        if ($paciente->fotografia) {
            Storage::disk('public')->delete($paciente->fotografia);
        }

        $nombre = $paciente->nombre_completo;
        $paciente->delete();

        return redirect()->route('admin.pacientes.index')
            ->with('exito', "El paciente {$nombre} fue eliminado.");
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:150'],
            'apellidos' => ['required', 'string', 'max:150'],
            'tipo_documento' => ['required', 'in:CI,DNI,PASAPORTE,CE'],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:pacientes,numero_documento'.($ignorar ? ",{$ignorar}" : '')],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'genero' => ['required', 'in:M,F,O'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'grupo_sanguineo' => ['nullable', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'alergias' => ['nullable', 'string', 'max:1000'],
            'enfermedades' => ['nullable', 'string', 'max:1000'],
            'medicamentos' => ['nullable', 'string', 'max:1000'],
            'habitos' => ['nullable', 'string', 'max:1000'],
            'antecedentes' => ['nullable', 'string', 'max:1000'],
            'contacto_emergencia' => ['nullable', 'string', 'max:150'],
            'telefono_emergencia' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'numero_documento' => 'número de documento',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'grupo_sanguineo' => 'grupo sanguíneo',
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
