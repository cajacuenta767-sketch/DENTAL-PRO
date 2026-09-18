<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Doctor;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Rules\CitaDelPaciente;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\PresupuestoDetalle;
use App\Models\Tratamiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PresupuestoController extends Controller
{
    public function index(Request $request): View
    {
        $presupuestos = Presupuesto::query()
            ->with(['paciente.aseguradora', 'doctor'])
            ->withCount('detalles')
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('codigo', 'ilike', $t)
                    ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                        ->orWhere('apellidos', 'ilike', $t)
                        ->orWhere('numero_documento', 'ilike', $t)));
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->doctor_id))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.presupuestos.index', [
            'presupuestos' => $presupuestos,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'totales' => [
                'abiertos' => Presupuesto::whereIn('estado', ['PRESENTADO', 'APROBADO', 'EN_EJECUCION'])->count(),
                'montoAbierto' => (float) Presupuesto::whereIn('estado', ['PRESENTADO', 'APROBADO', 'EN_EJECUCION'])->sum('total'),
                'aprobados' => Presupuesto::whereIn('estado', ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'])->count(),
                'tasaCierre' => $this->tasaCierre(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $paciente = $request->filled('paciente_id') ? Paciente::with('aseguradora')->find($request->paciente_id) : null;

        return view('admin.presupuestos.form', [
            'presupuesto' => new Presupuesto([
                'estado' => 'BORRADOR',
                'fecha' => now()->toDateString(),
                'validez_dias' => 30,
                'paciente_id' => $paciente?->id,
                'doctor_id' => $request->user()->doctor?->id,
                'odontograma_id' => $request->query('odontograma_id'),
            ]),
            'pacienteActual' => $paciente,
            'pacientes' => Paciente::activos()->with('aseguradora')->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
            'detallesPrevios' => $this->detallesDesdeOdontograma($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $presupuesto = DB::transaction(function () use ($datos, $request) {
            $presupuesto = Presupuesto::create([
                'codigo' => Presupuesto::siguienteCodigo(),
                'paciente_id' => $datos['paciente_id'],
                'doctor_id' => $datos['doctor_id'] ?? null,
                'usuario_id' => $request->user()->id,
                'odontograma_id' => $datos['odontograma_id'] ?? null,
                'estado' => $datos['estado'],
                'descuento' => $datos['descuento'] ?? 0,
                'validez_dias' => $datos['validez_dias'],
                'fecha' => $datos['fecha'],
                'notas' => $datos['notas'] ?? null,
            ]);

            $this->guardarDetalles($presupuesto, $datos['detalles']);
            $presupuesto->recalcular();

            return $presupuesto;
        });

        return redirect()->route('admin.presupuestos.show', $presupuesto)
            ->with('exito', "Presupuesto {$presupuesto->codigo} creado.");
    }

    public function show(Presupuesto $presupuesto): View
    {
        $presupuesto->load([
            'paciente.aseguradora', 'doctor.especialidad', 'odontograma',
            'detalles.tratamiento', 'detalles.cita',
        ]);

        return view('admin.presupuestos.show', [
            'presupuesto' => $presupuesto,
            'pagado' => (float) Pago::where('paciente_id', $presupuesto->paciente_id)->vigentes()->sum('monto_pagado'),
        ]);
    }

    public function edit(Presupuesto $presupuesto): View
    {
        if (! $presupuesto->es_editable) {
            abort(403, 'Un presupuesto aprobado ya no puede editarse. Anúlalo o crea uno nuevo.');
        }

        $presupuesto->load('detalles');

        return view('admin.presupuestos.form', [
            'presupuesto' => $presupuesto,
            'pacienteActual' => $presupuesto->paciente()->with('aseguradora')->first(),
            'pacientes' => Paciente::activos()->with('aseguradora')->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->with('especialidad')->orderBy('nombre')->get(),
            'detallesPrevios' => $presupuesto->detalles->map(fn ($d) => [
                'tratamiento_id' => $d->tratamiento_id,
                'pieza_dental' => $d->pieza_dental,
                'cara' => $d->cara,
                'descripcion' => $d->descripcion,
                'cantidad' => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
            ])->all(),
        ]);
    }

    public function update(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        if (! $presupuesto->es_editable) {
            return back()->with('error', 'Un presupuesto aprobado ya no puede editarse.');
        }

        $datos = $this->validar($request);

        DB::transaction(function () use ($presupuesto, $datos) {
            $presupuesto->update([
                'paciente_id' => $datos['paciente_id'],
                'doctor_id' => $datos['doctor_id'] ?? null,
                'estado' => $datos['estado'],
                'descuento' => $datos['descuento'] ?? 0,
                'validez_dias' => $datos['validez_dias'],
                'fecha' => $datos['fecha'],
                'notas' => $datos['notas'] ?? null,
            ]);

            $presupuesto->detalles()->delete();
            $this->guardarDetalles($presupuesto, $datos['detalles']);
            $presupuesto->recalcular();
        });

        return redirect()->route('admin.presupuestos.show', $presupuesto)
            ->with('exito', "Presupuesto {$presupuesto->codigo} actualizado.");
    }

    public function destroy(Presupuesto $presupuesto): RedirectResponse
    {
        if ($presupuesto->detalles()->where('estado', 'EJECUTADO')->exists()) {
            return back()->with('error', 'No puedes eliminar un presupuesto con tratamientos ya ejecutados.');
        }

        $codigo = $presupuesto->codigo;
        $presupuesto->delete();

        return redirect()->route('admin.presupuestos.index')
            ->with('exito', "El presupuesto {$codigo} fue eliminado.");
    }

    /** Mueve el presupuesto por su flujo: presentado, aprobado o rechazado. */
    public function cambiarEstado(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        $estado = $request->validate([
            'estado' => ['required', 'in:'.implode(',', array_keys(Presupuesto::ESTADOS))],
        ])['estado'];

        if ($presupuesto->estado === 'COMPLETADO' && $estado !== 'COMPLETADO') {
            return back()->with('error', 'Un presupuesto completado ya no cambia de estado.');
        }

        $presupuesto->update(['estado' => $estado]);

        return back()->with('exito', "El presupuesto pasó a {$presupuesto->estado_legible}.");
    }

    /** Marca una línea del plan como ejecutada y avanza el presupuesto. */
    public function ejecutarDetalle(Request $request, PresupuestoDetalle $detalle): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:'.implode(',', PresupuestoDetalle::ESTADOS)],
            'cita_id' => ['nullable', 'exists:citas,id', new CitaDelPaciente($detalle->presupuesto->paciente_id)],
        ]);

        $presupuesto = $detalle->presupuesto;

        if (in_array($presupuesto->estado, ['BORRADOR', 'PRESENTADO'], true)) {
            return back()->with('error', 'Aprueba el presupuesto antes de ejecutar tratamientos.');
        }

        $detalle->update($datos + [
            'fecha_ejecucion' => $datos['estado'] === 'EJECUTADO' ? now()->toDateString() : null,
        ]);

        $presupuesto->recalcular();
        $presupuesto->sincronizarEstado();

        return back()->with('exito', 'El plan de tratamiento fue actualizado.');
    }

    /** Genera el recibo de caja con las líneas ya ejecutadas y sin cobrar. */
    public function facturar(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        if (! in_array($presupuesto->estado, ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'], true)) {
            return back()->with('error', 'Solo puedes cobrar un presupuesto aprobado.');
        }

        $lineas = $presupuesto->detalles()->where('estado', 'EJECUTADO')->get();

        if ($lineas->isEmpty()) {
            return back()->with('error', 'Marca al menos un tratamiento como ejecutado antes de cobrar.');
        }

        $pago = DB::transaction(function () use ($presupuesto, $lineas, $request) {
            $pago = Pago::create([
                'codigo_recibo' => Pago::siguienteCodigo(),
                'paciente_id' => $presupuesto->paciente_id,
                'doctor_id' => $presupuesto->doctor_id,
                'usuario_id' => $request->user()->id,
                'monto_pagado' => 0,
                'metodo_pago' => 'EFECTIVO',
                'fecha_pago' => now(),
                'notas' => "Generado desde el presupuesto {$presupuesto->codigo}.",
            ]);

            foreach ($lineas as $linea) {
                $pago->detalles()->create([
                    'tratamiento_id' => $linea->tratamiento_id,
                    'descripcion' => $linea->descripcion.($linea->ubicacion ? " ({$linea->ubicacion})" : ''),
                    'cantidad' => $linea->cantidad,
                    'precio_unitario' => $linea->precio_unitario,
                    'subtotal' => $linea->subtotal,
                ]);
            }

            $pago->recalcular();

            return $pago;
        });

        return redirect()->route('admin.pagos.edit', $pago)
            ->with('exito', "Recibo {$pago->codigo_recibo} creado desde el presupuesto. Registra el monto cobrado.");
    }

    public function pdf(Presupuesto $presupuesto): Response
    {
        $presupuesto->load(['paciente.aseguradora', 'doctor.especialidad', 'detalles.tratamiento']);

        return Pdf::loadView('pdf.presupuesto', [
            'presupuesto' => $presupuesto,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream($presupuesto->codigo.'.pdf');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'odontograma_id' => ['nullable', 'exists:odontogramas,id'],
            'estado' => ['required', 'in:'.implode(',', array_keys(Presupuesto::ESTADOS))],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'validez_dias' => ['required', 'integer', 'min:1', 'max:365'],
            'fecha' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
            'detalles.*.pieza_dental' => ['nullable', 'string', 'max:10'],
            'detalles.*.cara' => ['nullable', 'string', 'max:20'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ], [], [
            'paciente_id' => 'paciente',
            'validez_dias' => 'validez en días',
            'detalles' => 'plan de tratamiento',
        ]);
    }

    private function guardarDetalles(Presupuesto $presupuesto, array $detalles): void
    {
        foreach (array_values($detalles) as $orden => $detalle) {
            $presupuesto->detalles()->create([
                'tratamiento_id' => $detalle['tratamiento_id'] ?: null,
                'pieza_dental' => $detalle['pieza_dental'] ?: null,
                'cara' => $detalle['cara'] ?: null,
                'descripcion' => $detalle['descripcion'],
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio_unitario'],
                'subtotal' => round($detalle['cantidad'] * $detalle['precio_unitario'], 2),
                'estado' => 'PENDIENTE',
                'orden' => $orden,
            ]);
        }
    }

    /**
     * Precarga el plan con las piezas marcadas en un odontograma: cada
     * hallazgo distinto de sano se convierte en una línea propuesta.
     */
    private function detallesDesdeOdontograma(Request $request): array
    {
        if (! $request->filled('odontograma_id')) {
            return [];
        }

        $odontograma = Odontograma::find($request->odontograma_id);

        if (! $odontograma) {
            return [];
        }

        $sugerencias = [
            'caries' => 'OBTURACIÓN SIMPLE',
            'fractura' => 'OBTURACIÓN COMPUESTA',
            'endodoncia' => 'ENDODONCIA UNIRRADICULAR',
            'corona' => 'CORONA DE ZIRCONIO',
            'extraccion' => 'EXODONCIA SIMPLE',
            'implante' => 'IMPLANTE DENTAL UNITARIO',
            'sellante' => 'SELLANTE DE FOSAS Y FISURAS',
        ];

        $catalogo = Tratamiento::activos()->get()->keyBy('nombre');
        $lineas = [];

        foreach ($odontograma->piezas ?? [] as $numero => $pieza) {
            $hallazgos = collect($pieza['caras'] ?? [])
                ->filter(fn ($v) => isset($sugerencias[$v]))
                ->take(1);

            if (($pieza['estado'] ?? 'sano') !== 'sano' && isset($sugerencias[$pieza['estado']])) {
                $hallazgos = collect([null => $pieza['estado']]);
            }

            foreach ($hallazgos as $cara => $hallazgo) {
                $tratamiento = $catalogo->get($sugerencias[$hallazgo]);

                $lineas[] = [
                    'tratamiento_id' => $tratamiento?->id,
                    'pieza_dental' => (string) $numero,
                    'cara' => $cara,
                    'descripcion' => $sugerencias[$hallazgo],
                    'cantidad' => 1,
                    'precio_unitario' => (float) ($tratamiento?->precio ?? 0),
                ];
            }
        }

        return $lineas;
    }

    /** Porcentaje de presupuestos presentados que terminan aprobados. */
    private function tasaCierre(): float
    {
        $presentados = Presupuesto::whereIn('estado', [
            'PRESENTADO', 'APROBADO', 'EN_EJECUCION', 'COMPLETADO', 'RECHAZADO',
        ])->count();

        if ($presentados === 0) {
            return 0.0;
        }

        $cerrados = Presupuesto::whereIn('estado', ['APROBADO', 'EN_EJECUCION', 'COMPLETADO'])->count();

        return round($cerrados / $presentados * 100, 1);
    }
}
