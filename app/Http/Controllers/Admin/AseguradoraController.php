<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aseguradora;
use App\Models\Paciente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AseguradoraController extends Controller
{
    public function index(Request $request): View
    {
        $aseguradoras = Aseguradora::query()
            ->withCount('pacientes')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('nombre', 'ilike', $t)->orWhere('codigo', 'ilike', $t));
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('admin.aseguradoras.index', [
            'aseguradoras' => $aseguradoras,
            'totales' => [
                'convenios' => Aseguradora::count(),
                'activas' => Aseguradora::activas()->count(),
                'afiliados' => Paciente::whereNotNull('aseguradora_id')->count(),
                'coberturaMedia' => round((float) Aseguradora::activas()->avg('porcentaje_cobertura'), 1),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.aseguradoras.form', [
            'aseguradora' => new Aseguradora(['activo' => true, 'tipo' => 'PRIVADA', 'porcentaje_cobertura' => 0]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Aseguradora::create($this->validar($request));

        return redirect()->route('admin.aseguradoras.index')
            ->with('exito', 'La aseguradora fue registrada.');
    }

    public function edit(Aseguradora $aseguradora): View
    {
        return view('admin.aseguradoras.form', compact('aseguradora'));
    }

    public function update(Request $request, Aseguradora $aseguradora): RedirectResponse
    {
        $aseguradora->update($this->validar($request, $aseguradora->id));

        return redirect()->route('admin.aseguradoras.index')
            ->with('exito', 'La aseguradora fue actualizada.');
    }

    public function destroy(Aseguradora $aseguradora): RedirectResponse
    {
        if ($aseguradora->pacientes()->exists()) {
            return back()->with('error', 'No puedes eliminar una aseguradora con pacientes afiliados. Desactívala en su lugar.');
        }

        $aseguradora->delete();

        return back()->with('exito', 'La aseguradora fue eliminada.');
    }

    private function validar(Request $request, ?int $ignorar = null): array
    {
        // El nombre se guarda en mayúsculas: se normaliza antes de validar
        // para que «unique» compare contra el valor que acabará en la tabla.
        $request->merge(['nombre' => mb_strtoupper(trim((string) $request->input('nombre')))]);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150', 'unique:aseguradoras,nombre'.($ignorar ? ",{$ignorar}" : '')],
            'codigo' => ['nullable', 'string', 'max:30'],
            'tipo' => ['required', 'in:'.implode(',', Aseguradora::TIPOS)],
            'porcentaje_cobertura' => ['required', 'numeric', 'min:0', 'max:100'],
            'tope_anual' => ['nullable', 'numeric', 'min:0'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'porcentaje_cobertura' => 'porcentaje de cobertura',
            'tope_anual' => 'tope anual',
        ]);

        $datos['activo'] = $request->boolean('activo');

        return $datos;
    }
}
