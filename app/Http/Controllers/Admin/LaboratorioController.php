<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\Doctor;
use App\Models\LaboratorioDental;
use App\Models\OrdenLaboratorio;
use App\Models\Paciente;
use App\Models\Tratamiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class LaboratorioController extends Controller
{
    public function index(Request $request): View
    {
        $vista = $request->query('vista', 'kanban'); // 'kanban' o 'tabla'

        $ordenes = OrdenLaboratorio::query()
            ->with(['paciente', 'doctor', 'laboratorio', 'tratamiento'])
            ->when($request->filled('laboratorio_id'), fn ($q) => $q->where('laboratorio_id', $request->laboratorio_id))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . mb_strtolower($request->q) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(folio) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(tipo_trabajo) LIKE ?', [$term])
                        ->orWhereHas('paciente', fn ($p) => $p->whereRaw('LOWER(nombres) LIKE ?', [$term])->orWhereRaw('LOWER(apellidos) LIKE ?', [$term]));
                });
            })
            ->orderBy('fecha_prometida')
            ->get();

        $resumen = [
            'total' => $ordenes->count(),
            'activas' => $ordenes->whereNotIn('estado', ['ENTREGADO'])->count(),
            'en_proceso' => $ordenes->where('estado', 'EN_PROCESO')->count(),
            'prueba' => $ordenes->where('estado', 'PRUEBA')->count(),
            'terminadas' => $ordenes->where('estado', 'TERMINADO')->count(),
            'vencidas' => $ordenes->filter(fn ($o) => $o->esta_vencida)->count(),
        ];

        $porEstado = [];
        foreach (array_keys(OrdenLaboratorio::ESTADOS) as $estado) {
            $porEstado[$estado] = $ordenes->where('estado', $estado)->values();
        }

        return view('admin.laboratorio.index', [
            'ordenes' => $ordenes,
            'porEstado' => $porEstado,
            'vista' => $vista,
            'resumen' => $resumen,
            'laboratorios' => LaboratorioDental::activos()->orderBy('nombre')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'estados' => OrdenLaboratorio::ESTADOS,
            'coloresEstado' => OrdenLaboratorio::COLORES_ESTADO,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.laboratorio.create', [
            'laboratorios' => LaboratorioDental::activos()->orderBy('nombre')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'paciente' => $request->filled('paciente_id') ? Paciente::find($request->paciente_id) : null,
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(),
            'coloresVita' => OrdenLaboratorio::COLORES_VITA,
            'estados' => OrdenLaboratorio::ESTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'laboratorio_id' => ['required', 'exists:laboratorios_dentales,id'],
            'tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
            'tipo_trabajo' => ['required', 'string', 'max:150'],
            'color_vita' => ['nullable', 'string', 'max:20'],
            'piezas_dentales' => ['nullable', 'string', 'max:100'],
            'fecha_envio' => ['required', 'date'],
            'fecha_prometida' => ['required', 'date', 'after_or_equal:fecha_envio'],
            'costo_laboratorio' => ['nullable', 'numeric', 'min:0'],
            'precio_paciente' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:' . implode(',', array_keys(OrdenLaboratorio::ESTADOS))],
            'notas_tecnicas' => ['nullable', 'string', 'max:2000'],
        ]);

        $orden = OrdenLaboratorio::create($datos);

        Auditoria::registrar('CREAR', $orden, "Creó la orden de laboratorio {$orden->folio} ({$orden->tipo_trabajo})");

        return redirect()->route('admin.laboratorio.show', $orden)
            ->with('exito', "Orden de laboratorio {$orden->folio} registrada con éxito.");
    }

    public function show(OrdenLaboratorio $orden): View
    {
        $orden->load(['paciente', 'doctor', 'laboratorio', 'tratamiento', 'cita']);

        return view('admin.laboratorio.show', [
            'orden' => $orden,
            'estados' => OrdenLaboratorio::ESTADOS,
            'coloresEstado' => OrdenLaboratorio::COLORES_ESTADO,
        ]);
    }

    public function edit(OrdenLaboratorio $orden): View
    {
        return view('admin.laboratorio.edit', [
            'orden' => $orden,
            'laboratorios' => LaboratorioDental::activos()->orderBy('nombre')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(),
            'coloresVita' => OrdenLaboratorio::COLORES_VITA,
            'estados' => OrdenLaboratorio::ESTADOS,
        ]);
    }

    public function update(Request $request, OrdenLaboratorio $orden): RedirectResponse
    {
        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'laboratorio_id' => ['required', 'exists:laboratorios_dentales,id'],
            'tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
            'tipo_trabajo' => ['required', 'string', 'max:150'],
            'color_vita' => ['nullable', 'string', 'max:20'],
            'piezas_dentales' => ['nullable', 'string', 'max:100'],
            'fecha_envio' => ['required', 'date'],
            'fecha_prometida' => ['required', 'date'],
            'fecha_entrega' => ['nullable', 'date'],
            'costo_laboratorio' => ['nullable', 'numeric', 'min:0'],
            'precio_paciente' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'in:' . implode(',', array_keys(OrdenLaboratorio::ESTADOS))],
            'notas_tecnicas' => ['nullable', 'string', 'max:2000'],
        ]);

        $orden->update($datos);

        Auditoria::registrar('EDITAR', $orden, "Actualizó la orden de laboratorio {$orden->folio}");

        return redirect()->route('admin.laboratorio.show', $orden)
            ->with('exito', "Orden de laboratorio {$orden->folio} actualizada.");
    }

    public function cambiarEstado(Request $request, OrdenLaboratorio $orden): JsonResponse|RedirectResponse
    {
        $request->validate([
            'estado' => ['required', 'in:' . implode(',', array_keys(OrdenLaboratorio::ESTADOS))],
        ]);

        $orden->estado = $request->estado;
        if ($request->estado === 'ENTREGADO' && empty($orden->fecha_entrega)) {
            $orden->fecha_entrega = now()->toDateString();
        }
        $orden->save();

        Auditoria::registrar('EDITAR', $orden, "Cambió el estado de la orden {$orden->folio} a {$orden->estado}");

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'estado' => $orden->estado,
                'estado_legible' => $orden->estado_legible,
                'color' => $orden->color_badge,
            ]);
        }

        return back()->with('exito', "Estado actualizado a {$orden->estado_legible}.");
    }

    public function destroy(OrdenLaboratorio $orden): RedirectResponse
    {
        Auditoria::registrar('ELIMINAR', $orden, "Eliminó la orden de laboratorio {$orden->folio}");

        $orden->delete();

        return redirect()->route('admin.laboratorio.index')
            ->with('exito', "Orden {$orden->folio} eliminada correctamente.");
    }

    public function ticketPdf(OrdenLaboratorio $orden): Response
    {
        $orden->load(['paciente', 'doctor', 'laboratorio', 'tratamiento']);

        return Pdf::loadView('pdf.orden-laboratorio', [
            'orden' => $orden,
            'clinica' => \App\Models\Ajuste::actual(),
        ])->setPaper('a5', 'portrait')
            ->download("orden-laboratorio-{$orden->folio}.pdf");
    }

    // --- Catálogo de laboratorios asociados ---
    public function catalogo(): View
    {
        return view('admin.laboratorio.catalogo', [
            'laboratorios' => LaboratorioDental::withCount('ordenes')->orderBy('nombre')->get(),
        ]);
    }

    public function catalogoStore(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'especialidades' => ['nullable', 'string', 'max:255'],
        ]);

        $lab = LaboratorioDental::create($datos);

        Auditoria::registrar('CREAR', $lab, "Registró el laboratorio protésico {$lab->nombre}");

        return back()->with('exito', "Laboratorio {$lab->nombre} guardado.");
    }

    public function catalogoUpdate(Request $request, LaboratorioDental $laboratorio): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'especialidades' => ['nullable', 'string', 'max:255'],
            'activo' => ['boolean'],
        ]);

        $datos['activo'] = $request->boolean('activo', true);
        $laboratorio->update($datos);

        Auditoria::registrar('EDITAR', $laboratorio, "Actualizó el laboratorio protésico {$laboratorio->nombre}");

        return back()->with('exito', "Laboratorio {$laboratorio->nombre} actualizado.");
    }

    public function catalogoDestroy(LaboratorioDental $laboratorio): RedirectResponse
    {
        if ($laboratorio->ordenes()->exists()) {
            return back()->with('error', "No se puede eliminar el laboratorio porque tiene órdenes de trabajo registradas.");
        }

        Auditoria::registrar('ELIMINAR', $laboratorio, "Eliminó el laboratorio {$laboratorio->nombre}");
        $laboratorio->delete();

        return back()->with('exito', "Laboratorio eliminado correctamente.");
    }
}