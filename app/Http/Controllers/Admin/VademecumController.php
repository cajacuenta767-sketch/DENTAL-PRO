<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\MedicamentoVademecum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VademecumController extends Controller
{
    public function index(Request $request): View
    {
        $medicamentos = MedicamentoVademecum::query()
            ->when($request->filled('familia'), fn ($q) => $q->where('familia', $request->familia))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.mb_strtolower($request->q).'%';
                $q->where(function ($sub) use ($t) {
                    $sub->whereRaw('LOWER(principio_activo) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(COALESCE(nombre_comercial, "")) LIKE ?', [$t])
                        ->orWhereRaw('LOWER(concentracion) LIKE ?', [$t]);
                });
            })
            ->orderBy('principio_activo')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vademecum.index', [
            'medicamentos' => $medicamentos,
            'familias' => MedicamentoVademecum::FAMILIAS,
            'total' => MedicamentoVademecum::count(),
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $t = '%'.mb_strtolower($term).'%';

        $resultados = MedicamentoVademecum::activos()
            ->where(function ($sub) use ($t) {
                $sub->whereRaw('LOWER(principio_activo) LIKE ?', [$t])
                    ->orWhereRaw('LOWER(COALESCE(nombre_comercial, "")) LIKE ?', [$t]);
            })
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'principio_activo' => $m->principio_activo,
                'nombre_comercial' => $m->nombre_comercial,
                'nombre_completo' => $m->nombre_completo,
                'presentacion' => $m->presentacion,
                'concentracion' => $m->concentracion,
                'familia' => $m->familia,
                'posologia_adulto' => $m->posologia_adulto,
                'contraindicaciones' => $m->contraindicaciones,
                'advertencias' => $m->advertencias,
            ]);

        return response()->json($resultados);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'principio_activo' => ['required', 'string', 'max:150'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'presentacion' => ['required', 'string', 'max:100'],
            'concentracion' => ['required', 'string', 'max:60'],
            'familia' => ['required', 'in:'.implode(',', array_keys(MedicamentoVademecum::FAMILIAS))],
            'posologia_adulto' => ['nullable', 'string'],
            'posologia_pediatrica' => ['nullable', 'string'],
            'contraindicaciones' => ['nullable', 'string'],
            'advertencias' => ['nullable', 'string'],
        ]);

        $medicamento = MedicamentoVademecum::create($datos);

        Auditoria::registrar('CREAR', $medicamento, "Agregó al vademécum: {$medicamento->nombre_completo}");

        return back()->with('exito', "Medicamento {$medicamento->principio_activo} agregado al vademécum.");
    }

    public function update(Request $request, MedicamentoVademecum $vademecum): RedirectResponse
    {
        $datos = $request->validate([
            'principio_activo' => ['required', 'string', 'max:150'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'presentacion' => ['required', 'string', 'max:100'],
            'concentracion' => ['required', 'string', 'max:60'],
            'familia' => ['required', 'in:'.implode(',', array_keys(MedicamentoVademecum::FAMILIAS))],
            'posologia_adulto' => ['nullable', 'string'],
            'posologia_pediatrica' => ['nullable', 'string'],
            'contraindicaciones' => ['nullable', 'string'],
            'advertencias' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ]);

        $datos['activo'] = $request->boolean('activo', true);
        $vademecum->update($datos);

        Auditoria::registrar('EDITAR', $vademecum, "Actualizó el medicamento: {$vademecum->nombre_completo}");

        return back()->with('exito', "Medicamento {$vademecum->principio_activo} actualizado.");
    }

    public function destroy(MedicamentoVademecum $vademecum): RedirectResponse
    {
        Auditoria::registrar('ELIMINAR', $vademecum, "Eliminó del vademécum: {$vademecum->nombre_completo}");
        $vademecum->delete();

        return back()->with('exito', 'Medicamento eliminado del vademécum.');
    }
}
