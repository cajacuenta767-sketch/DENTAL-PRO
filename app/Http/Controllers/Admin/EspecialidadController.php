<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EspecialidadController extends Controller
{
    public function index(Request $request): View
    {
        $especialidades = Especialidad::query()
            ->withCount(['doctores', 'tratamientos'])
            ->when($request->filled('buscar'), fn ($q) => $q->where('nombre', 'ilike', '%'.$request->buscar.'%'))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.especialidades.index', compact('especialidades'));
    }

    public function create(): View
    {
        return view('admin.especialidades.form', ['especialidad' => new Especialidad(['activo' => true, 'color' => '#0d9488'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Especialidad::create($this->validar($request));

        return redirect()->route('admin.especialidades.index')
            ->with('exito', 'La especialidad fue registrada.');
    }

    public function edit(Especialidad $especialidad): View
    {
        return view('admin.especialidades.form', compact('especialidad'));
    }

    public function update(Request $request, Especialidad $especialidad): RedirectResponse
    {
        $especialidad->update($this->validar($request, $especialidad->id));

        return redirect()->route('admin.especialidades.index')
            ->with('exito', 'La especialidad fue actualizada.');
    }

    public function destroy(Especialidad $especialidad): RedirectResponse
    {
        if ($especialidad->doctores()->exists() || $especialidad->tratamientos()->exists()) {
            return back()->with('error', 'No puedes eliminar una especialidad con doctores o tratamientos asociados. Desactívala en su lugar.');
        }

        $especialidad->delete();

        return back()->with('exito', 'La especialidad fue eliminada.');
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        $request->merge(['nombre' => mb_strtoupper(trim((string) $request->input('nombre')))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', 'unique:especialidades,nombre'.($ignorar ? ",{$ignorar}" : '')],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:20'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
