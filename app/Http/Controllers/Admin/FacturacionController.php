<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\DocumentoFiscal;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FacturacionController extends Controller
{
    public function index(Request $request): View
    {
        $documentos = DocumentoFiscal::query()
            ->with(['pago.paciente', 'emisor'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('numero_control', 'ilike', $t)
                    ->orWhere('receptor_nombre', 'ilike', $t)
                    ->orWhere('receptor_documento', 'ilike', $t));
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('fecha_emision', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('fecha_emision', '<=', $request->hasta))
            ->orderByDesc('fecha_emision')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $emitidos = DocumentoFiscal::ventas()->where('estado', 'EMITIDO');

        return view('admin.facturacion.index', [
            'documentos' => $documentos,
            'pendientes' => Pago::vigentes()
                ->whereDoesntHave('documentoFiscal')
                ->with('paciente')
                ->orderByDesc('fecha_pago')
                ->limit(10)
                ->get(),
            'totales' => [
                'emitidos' => (clone $emitidos)->count(),
                'facturado' => (float) (clone $emitidos)->sum('total'),
                'iva' => (float) (clone $emitidos)->sum('iva'),
                'anulados' => DocumentoFiscal::where('estado', 'ANULADO')->count(),
                'notasCredito' => DocumentoFiscal::where('tipo', 'NOTA_CREDITO')->where('estado', 'EMITIDO')->count(),
            ],
        ]);
    }

    /** Formulario de emisión sobre un recibo de caja. */
    public function create(Request $request): View
    {
        $pago = Pago::with(['paciente.aseguradora', 'detalles', 'doctor'])
            ->vigentes()
            ->findOrFail($request->query('pago_id'));

        abort_if($pago->documentoFiscal()->exists(), 409, 'Este recibo ya tiene un documento fiscal vigente.');

        $ajustes = Ajuste::actual();
        $desglose = DocumentoFiscal::desglosarIva((float) $pago->monto_total, (float) $ajustes->facturacion_tasa_iva);

        return view('admin.facturacion.form', [
            'pago' => $pago,
            'ajustes' => $ajustes,
            'desglose' => $desglose,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'pago_id' => ['required', 'exists:pagos,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(DocumentoFiscal::TIPOS))],
            'receptor_nombre' => ['required', 'string', 'max:200'],
            'receptor_documento' => ['nullable', 'string', 'max:40'],
            'receptor_direccion' => ['nullable', 'string', 'max:255'],
            'receptor_email' => ['nullable', 'email', 'max:150'],
        ], [], [
            'receptor_nombre' => 'nombre del receptor',
            'receptor_documento' => 'documento del receptor',
        ]);

        if (! in_array($datos['tipo'], ['FACTURA', 'CREDITO_FISCAL'], true)) {
            return back()->with('error', 'Las notas de crédito y débito se generan al anular un documento emitido.');
        }

        $ajustes = Ajuste::actual();

        $documento = DB::transaction(function () use ($datos, $ajustes, $request) {
            // Bloquea el recibo: dos pestañas no pueden emitir dos facturas.
            $pago = Pago::with('detalles')->vigentes()->lockForUpdate()->findOrFail($datos['pago_id']);

            if ($pago->documentoFiscal()->exists()) {
                throw ValidationException::withMessages(['pago_id' => 'Este recibo ya tiene un documento fiscal vigente.']);
            }

            $serie = $ajustes->facturacion_serie ?: 'A';
            $tasa = (float) $ajustes->facturacion_tasa_iva;
            $correlativo = DocumentoFiscal::siguienteCorrelativo($datos['tipo'], $serie);
            $desglose = DocumentoFiscal::desglosarIva((float) $pago->monto_total, $tasa);

            return DocumentoFiscal::create($datos + [
                'usuario_id' => $request->user()->id,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'numero_control' => DocumentoFiscal::componerNumeroControl($datos['tipo'], $serie, $correlativo),
                'codigo_generacion' => DocumentoFiscal::nuevoCodigoGeneracion(),
                'subtotal' => $desglose['subtotal'],
                'iva' => $desglose['iva'],
                'total' => (float) $pago->monto_total,
                'tasa_iva' => $tasa,
                'estado' => 'EMITIDO',
                'sello_recepcion' => mb_strtoupper(bin2hex(random_bytes(16))),
                'fecha_emision' => now(),
                // Snapshot: el documento fiscal no debe cambiar si luego se edita el recibo.
                'contenido' => [
                    'recibo' => $pago->codigo_recibo,
                    'emisor' => [
                        'nombre' => $ajustes->nombre,
                        'nit' => $ajustes->nit,
                        'direccion' => $ajustes->direccion,
                    ],
                    'lineas' => $pago->detalles->map(fn ($d) => [
                        'descripcion' => $d->descripcion,
                        'cantidad' => $d->cantidad,
                        'precio_unitario' => (float) $d->precio_unitario,
                        'subtotal' => (float) $d->subtotal,
                    ])->all(),
                ],
            ]);
        });

        return redirect()->route('admin.facturacion.show', $documento)
            ->with('exito', "Documento {$documento->numero_control} emitido.");
    }

    public function show(DocumentoFiscal $documento): View
    {
        $documento->load(['pago.paciente', 'pago.detalles', 'emisor', 'documentoReferencia', 'notas']);

        return view('admin.facturacion.show', compact('documento'));
    }

    public function anular(Request $request, DocumentoFiscal $documento): RedirectResponse
    {
        if ($documento->estado === 'ANULADO') {
            return back()->with('aviso', 'Este documento ya estaba anulado.');
        }

        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
        ])['motivo'];

        if ($documento->es_nota) {
            return back()->with('error', 'Una nota de crédito no se anula: emite el documento corregido.');
        }

        $nota = DB::transaction(function () use ($documento, $request, $motivo) {
            $documento->update([
                'estado' => 'ANULADO',
                'motivo_anulacion' => $motivo.' · Anulado el '.now()->format('d/m/Y H:i').' por '.$request->user()->nombre,
            ]);

            // La anulación se documenta con una nota de crédito por el mismo importe.
            $serie = $documento->serie;
            $correlativo = DocumentoFiscal::siguienteCorrelativo('NOTA_CREDITO', $serie);

            return DocumentoFiscal::create([
                'pago_id' => $documento->pago_id,
                'usuario_id' => $request->user()->id,
                'documento_referencia_id' => $documento->id,
                'tipo' => 'NOTA_CREDITO',
                'serie' => $serie,
                'correlativo' => $correlativo,
                'numero_control' => DocumentoFiscal::componerNumeroControl('NOTA_CREDITO', $serie, $correlativo),
                'codigo_generacion' => DocumentoFiscal::nuevoCodigoGeneracion(),
                'receptor_nombre' => $documento->receptor_nombre,
                'receptor_documento' => $documento->receptor_documento,
                'receptor_direccion' => $documento->receptor_direccion,
                'receptor_email' => $documento->receptor_email,
                'subtotal' => $documento->subtotal,
                'descuento' => $documento->descuento,
                'iva' => $documento->iva,
                'total' => $documento->total,
                'tasa_iva' => $documento->tasa_iva,
                'estado' => 'EMITIDO',
                'sello_recepcion' => mb_strtoupper(bin2hex(random_bytes(16))),
                'fecha_emision' => now(),
                'contenido' => ($documento->contenido ?? []) + [
                    'referencia' => $documento->numero_control,
                    'motivo' => $motivo,
                ],
            ]);
        });

        Auditoria::registrar('ANULAR', $documento, "Anuló {$documento->numero_control} con la nota {$nota->numero_control}: {$motivo}");

        return back()->with('exito', "El documento {$documento->numero_control} fue anulado y se emitió la nota de crédito {$nota->numero_control}.");
    }

    public function pdf(DocumentoFiscal $documento): Response
    {
        $documento->load(['pago.paciente', 'pago.detalles']);

        return Pdf::loadView('pdf.documento-fiscal', [
            'documento' => $documento,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream($documento->numero_control.'.pdf');
    }
}
