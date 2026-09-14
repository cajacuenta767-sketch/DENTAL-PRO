<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PagoComprobanteMail;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Tratamiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PagoController extends Controller
{
    public function index(Request $request): View
    {
        $consulta = Pago::query()
            ->with(['paciente', 'doctor', 'cajero'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('codigo_recibo', 'ilike', $t)
                    ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                        ->orWhere('apellidos', 'ilike', $t)
                        ->orWhere('numero_documento', 'ilike', $t)));
            })
            ->when($request->filled('metodo'), fn ($q) => $q->where('metodo_pago', $request->metodo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha_pago', $request->fecha));

        return view('admin.pagos.index', [
            'pagos' => (clone $consulta)->orderByDesc('fecha_pago')->paginate(15)->withQueryString(),
            'totales' => $this->totalesCaja(),
        ]);
    }

    public function create(Request $request): View
    {
        $cita = $request->filled('cita_id')
            ? Cita::with(['paciente', 'doctor', 'tratamiento'])->find($request->cita_id)
            : null;

        return view('admin.pagos.form', [
            'pago' => new Pago([
                'metodo_pago' => 'EFECTIVO',
                'estado' => 'PENDIENTE',
                'fecha_pago' => now(),
                'paciente_id' => $cita?->paciente_id ?? $request->query('paciente_id'),
                'doctor_id' => $cita?->doctor_id,
                'cita_id' => $cita?->id,
            ]),
            'cita' => $cita,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(),
            'detallesPrevios' => $cita ? [[
                'tratamiento_id' => $cita->tratamiento_id,
                'descripcion' => $cita->tratamiento->nombre,
                'cantidad' => 1,
                'precio_unitario' => (float) $cita->tratamiento->precio,
            ]] : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $pago = DB::transaction(function () use ($datos, $request) {
            $pago = Pago::create([
                'codigo_recibo' => Pago::siguienteCodigo(),
                'paciente_id' => $datos['paciente_id'],
                'doctor_id' => $datos['doctor_id'] ?? null,
                'cita_id' => $datos['cita_id'] ?? null,
                'usuario_id' => $request->user()->id,
                'monto_pagado' => $datos['monto_pagado'],
                'metodo_pago' => $datos['metodo_pago'],
                'notas' => $datos['notas'] ?? null,
                'fecha_pago' => $datos['fecha_pago'],
            ]);

            $this->guardarDetalles($pago, $datos['detalles']);
            $pago->recalcular();

            return $pago;
        });

        return redirect()->route('admin.pagos.show', $pago)
            ->with('exito', "Recibo {$pago->codigo_recibo} registrado.");
    }

    public function show(Pago $pago): View
    {
        $pago->load(['paciente', 'doctor', 'cita.tratamiento', 'cajero', 'detalles.tratamiento']);

        return view('admin.pagos.show', compact('pago'));
    }

    public function edit(Pago $pago): View
    {
        if ($pago->estado === 'ANULADO') {
            abort(403, 'Un recibo anulado no puede editarse.');
        }

        $pago->load('detalles');

        return view('admin.pagos.form', [
            'pago' => $pago,
            'cita' => $pago->cita,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(),
            'detallesPrevios' => $pago->detalles->map(fn ($d) => [
                'tratamiento_id' => $d->tratamiento_id,
                'descripcion' => $d->descripcion,
                'cantidad' => $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
            ])->all(),
        ]);
    }

    public function update(Request $request, Pago $pago): RedirectResponse
    {
        if ($pago->estado === 'ANULADO') {
            return back()->with('error', 'Un recibo anulado no puede editarse.');
        }

        $datos = $this->validar($request);

        DB::transaction(function () use ($pago, $datos) {
            $pago->update([
                'paciente_id' => $datos['paciente_id'],
                'doctor_id' => $datos['doctor_id'] ?? null,
                'cita_id' => $datos['cita_id'] ?? null,
                'monto_pagado' => $datos['monto_pagado'],
                'metodo_pago' => $datos['metodo_pago'],
                'notas' => $datos['notas'] ?? null,
                'fecha_pago' => $datos['fecha_pago'],
            ]);

            $pago->detalles()->delete();
            $this->guardarDetalles($pago, $datos['detalles']);
            $pago->recalcular();
        });

        return redirect()->route('admin.pagos.show', $pago)
            ->with('exito', "Recibo {$pago->codigo_recibo} actualizado.");
    }

    public function destroy(Pago $pago): RedirectResponse
    {
        if ($pago->tiene_documentos_fiscales) {
            return back()->with('error', 'Este recibo tiene documentos fiscales asociados. Anúlalo en lugar de eliminarlo.');
        }

        if ((float) $pago->monto_pagado > 0 && $pago->estado !== 'ANULADO') {
            return back()->with('error', 'Un recibo con dinero cobrado no se elimina: anúlalo para conservar el rastro en caja.');
        }

        $codigo = $pago->codigo_recibo;
        $pago->delete();

        return redirect()->route('admin.pagos.index')
            ->with('exito', "El recibo {$codigo} fue eliminado.");
    }

    /** Anular conserva el recibo para la auditoría pero lo saca de los totales. */
    public function anular(Request $request, Pago $pago): RedirectResponse
    {
        if ($pago->estado === 'ANULADO') {
            return back()->with('aviso', 'Este recibo ya estaba anulado.');
        }

        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ])['motivo'];

        if ($pago->documentoFiscal()->exists()) {
            return back()->with('error', 'Anula primero el documento fiscal vigente de este recibo.');
        }

        DB::transaction(function () use ($pago, $request, $motivo) {
            $pago->update([
                'estado' => 'ANULADO',
                'notas' => trim($pago->notas."\nANULADO el ".now()->format('d/m/Y H:i').' por '.$request->user()->nombre.': '.$motivo),
            ]);

            // Las líneas del presupuesto vuelven a quedar pendientes de cobro.
            \App\Models\PresupuestoDetalle::where('pago_id', $pago->id)->update(['pago_id' => null]);
        });

        Auditoria::registrar('ANULAR', $pago, "Anuló el recibo {$pago->codigo_recibo}: {$motivo}");

        return back()->with('exito', "El recibo {$pago->codigo_recibo} fue anulado.");
    }

    public function recibo(Pago $pago): Response
    {
        $pago->load(['paciente', 'doctor', 'cajero', 'detalles']);

        return Pdf::loadView('pdf.recibo', [
            'pago' => $pago,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream($pago->codigo_recibo.'.pdf');
    }

    public function enviarRecibo(Pago $pago): RedirectResponse
    {
        if (blank($pago->paciente->email)) {
            return back()->with('error', 'El paciente no tiene un correo registrado.');
        }

        $pago->load(['paciente', 'doctor', 'cajero', 'detalles']);

        $pdf = Pdf::loadView('pdf.recibo', [
            'pago' => $pago,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')->output();

        try {
            Mail::to($pago->paciente->email)->send(new PagoComprobanteMail($pago, $pdf));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No pudimos enviar el comprobante. Revisa la configuración de correo del servidor.');
        }

        return back()->with('exito', 'El comprobante fue enviado al correo del paciente.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'metodo_pago' => ['required', 'in:'.implode(',', Pago::METODOS)],
            'monto_pagado' => ['required', 'numeric', 'min:0'],
            'fecha_pago' => ['required', 'date'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.tratamiento_id' => ['nullable', 'exists:tratamientos,id'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ], [], [
            'paciente_id' => 'paciente',
            'metodo_pago' => 'método de pago',
            'monto_pagado' => 'monto pagado',
            'fecha_pago' => 'fecha del pago',
            'detalles' => 'detalle del recibo',
        ]);

        $total = round(collect($datos['detalles'])->sum(fn ($d) => $d['cantidad'] * $d['precio_unitario']), 2);

        // Nunca se registra más dinero del que vale el recibo.
        if ((float) $datos['monto_pagado'] > $total + 0.005) {
            throw ValidationException::withMessages([
                'monto_pagado' => "El monto pagado ({$datos['monto_pagado']}) supera el total del recibo ({$total}).",
            ]);
        }

        return $datos;
    }

    private function guardarDetalles(Pago $pago, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            $pago->detalles()->create([
                'tratamiento_id' => $detalle['tratamiento_id'] ?: null,
                'descripcion' => $detalle['descripcion'],
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio_unitario'],
                'subtotal' => round($detalle['cantidad'] * $detalle['precio_unitario'], 2),
            ]);
        }
    }

    /** Indicadores de la cabecera de caja. */
    private function totalesCaja(): array
    {
        $vigentes = Pago::query()->vigentes();

        return [
            'recaudado' => (float) (clone $vigentes)->sum('monto_pagado'),
            'efectivo' => (float) (clone $vigentes)->where('metodo_pago', 'EFECTIVO')->sum('monto_pagado'),
            'digital' => (float) (clone $vigentes)->whereIn('metodo_pago', ['TARJETA', 'QR', 'TRANSFERENCIA'])->sum('monto_pagado'),
            'saldos' => (float) (clone $vigentes)->sum('monto_saldo'),
        ];
    }
}
