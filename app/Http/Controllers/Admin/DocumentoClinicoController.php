<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Rules\CitaDelPaciente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentoClinicoController extends Controller
{
    /** Plantillas que precargan el contenido según el tipo elegido. */
    private const PLANTILLAS = [
        'RECETA' => "Rp/\n\n1. [Medicamento] [concentración] — [presentación]\n   Tomar [dosis] cada [intervalo] horas por [días] días.\n\n2. \n",
        'CERTIFICADO' => "Por medio del presente se hace constar que el paciente recibió atención odontológica en esta clínica en la fecha indicada, y se le recomienda reposo por [N] día(s).",
        'ORDEN_LABORATORIO' => "Se solicita al laboratorio dental la elaboración de:\n\n- Trabajo: \n- Piezas: \n- Color: \n- Material: \n- Fecha de entrega requerida: ",
        'CONSENTIMIENTO' => "El paciente declara haber sido informado sobre el procedimiento a realizar, sus beneficios, riesgos, alternativas y cuidados posteriores, y otorga su consentimiento para su ejecución.",
        'REFERENCIA' => "Se refiere al paciente a la especialidad de [especialidad] por el siguiente motivo:\n\n[Motivo de la referencia]\n\nSe adjunta resumen clínico.",
        'CONSTANCIA' => "Se hace constar que el paciente asistió a consulta odontológica en esta clínica en la fecha y hora indicadas.",
    ];

    public function index(Request $request): View
    {
        $documentos = DocumentoClinico::query()
            ->with(['paciente', 'doctor'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('folio', 'ilike', $t)
                    ->orWhere('titulo', 'ilike', $t)
                    ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                        ->orWhere('apellidos', 'ilike', $t)
                        ->orWhere('numero_documento', 'ilike', $t)));
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->orderByDesc('fecha_emision')->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.documentos.index', [
            'documentos' => $documentos,
            'totales' => [
                'emitidos' => DocumentoClinico::where('estado', 'EMITIDO')->count(),
                'recetas' => DocumentoClinico::where('tipo', 'RECETA')->count(),
                'certificados' => DocumentoClinico::where('tipo', 'CERTIFICADO')->count(),
                'esteMes' => DocumentoClinico::whereBetween('fecha_emision', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ],
        ]);
    }

    /** Pestaña "Recetas y certificados" dentro de la ficha del paciente. */
    public function porPaciente(Paciente $paciente): View
    {
        return view('admin.documentos.paciente', [
            'paciente' => $paciente,
            'documentos' => $paciente->documentos()
                ->with('doctor')
                ->orderByDesc('fecha_emision')->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $paciente = $request->filled('paciente_id') ? Paciente::find($request->paciente_id) : null;
        $tipo = array_key_exists((string) $request->tipo, DocumentoClinico::TIPOS) ? $request->tipo : 'RECETA';

        return view('admin.documentos.form', [
            'documento' => new DocumentoClinico([
                'tipo' => $tipo,
                'titulo' => DocumentoClinico::TIPOS[$tipo],
                'contenido' => self::PLANTILLAS[$tipo] ?? '',
                'fecha_emision' => now()->toDateString(),
                'paciente_id' => $paciente?->id,
                'cita_id' => $request->query('cita_id'),
                'doctor_id' => $request->user()->doctor?->id,
                'estado' => 'EMITIDO',
            ]),
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $paciente
                ? Cita::where('paciente_id', $paciente->id)->with('tratamiento')->orderByDesc('fecha')->limit(30)->get()
                : collect(),
            'plantillas' => self::PLANTILLAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $documento = DocumentoClinico::create($datos + [
            'folio' => DocumentoClinico::siguienteFolio($datos['tipo']),
            'usuario_id' => $request->user()->id,
            'estado' => 'EMITIDO',
        ]);

        return redirect()->route('admin.documentos.pdf', $documento)
            ->with('exito', "Documento {$documento->folio} emitido.");
    }

    public function edit(DocumentoClinico $documento): View
    {
        return view('admin.documentos.form', [
            'documento' => $documento,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $documento->paciente_id)
                ->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
            'plantillas' => self::PLANTILLAS,
        ]);
    }

    public function update(Request $request, DocumentoClinico $documento): RedirectResponse
    {
        if ($documento->estado === 'ANULADO') {
            return back()->with('error', 'Un documento anulado no puede editarse.');
        }

        $documento->update($this->validar($request));

        return redirect()->route('admin.documentos.index')
            ->with('exito', "Documento {$documento->folio} actualizado.");
    }

    public function anular(Request $request, DocumentoClinico $documento): RedirectResponse
    {
        if ($documento->estado === 'ANULADO') {
            return back()->with('aviso', 'Este documento ya estaba anulado.');
        }

        $documento->update(['estado' => 'ANULADO']);

        return back()->with('exito', "Documento {$documento->folio} anulado.");
    }

    public function pdf(DocumentoClinico $documento): Response
    {
        $documento->load(['paciente.aseguradora', 'doctor.especialidad', 'cita.tratamiento']);

        return Pdf::loadView('pdf.documento-clinico', [
            'documento' => $documento,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream($documento->folio.'.pdf');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['required', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id', new CitaDelPaciente($request->input('paciente_id'))],
            'tipo' => ['required', 'in:'.implode(',', array_keys(DocumentoClinico::TIPOS))],
            'titulo' => ['required', 'string', 'max:180'],
            'contenido' => ['required', 'string', 'max:6000'],
            'indicaciones' => ['nullable', 'string', 'max:2000'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
        ], [], [
            'paciente_id' => 'paciente',
            'doctor_id' => 'doctor',
            'vigencia_dias' => 'vigencia en días',
            'fecha_emision' => 'fecha de emisión',
        ]);
    }
}
