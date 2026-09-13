<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use App\Models\Tratamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TratamientoController extends Controller
{
    public function index(Request $request): View
    {
        $tratamientos = Tratamiento::query()
            ->with('especialidad')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)
                    ->orWhereHas('especialidad', fn ($e) => $e->where('nombre', 'ilike', $t)));
            })
            ->when($request->filled('especialidad_id'), fn ($q) => $q->where('especialidad_id', $request->especialidad_id))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.tratamientos.index', [
            'tratamientos' => $tratamientos,
            'especialidades' => Especialidad::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tratamientos.form', [
            'tratamiento' => new Tratamiento(['activo' => true, 'duracion' => 30, 'precio' => 0]),
            'especialidades' => Especialidad::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tratamiento::create($this->validar($request));

        return redirect()->route('admin.tratamientos.index')
            ->with('exito', 'El tratamiento fue registrado.');
    }

    public function edit(Tratamiento $tratamiento): View
    {
        return view('admin.tratamientos.form', [
            'tratamiento' => $tratamiento,
            'especialidades' => Especialidad::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Tratamiento $tratamiento): RedirectResponse
    {
        $tratamiento->update($this->validar($request));

        return redirect()->route('admin.tratamientos.index')
            ->with('exito', 'El tratamiento fue actualizado.');
    }

    public function destroy(Tratamiento $tratamiento): RedirectResponse
    {
        if ($tratamiento->citas()->exists()) {
            return back()->with('error', 'No puedes eliminar un tratamiento con citas registradas. Desactívalo en su lugar.');
        }

        $tratamiento->delete();

        return back()->with('exito', 'El tratamiento fue eliminado.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'especialidad_id' => ['required', 'exists:especialidades,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'precio' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'duracion' => ['required', 'integer', 'min:5', 'max:600'],
            'activo' => ['nullable', 'boolean'],
        ], [], ['especialidad_id' => 'especialidad', 'duracion' => 'duración']);

        $datos['nombre'] = mb_strtoupper($datos['nombre']);
        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
