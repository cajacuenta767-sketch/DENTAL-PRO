<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\EstudioImagen;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstudioImagenController extends Controller
{
    /** Bandeja general de estudios de toda la clínica. */
    public function index(Request $request): View
    {
        $estudios = EstudioImagen::query()
            ->with(['paciente', 'doctor'])
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $t = '%'.$request->buscar.'%';
                $q->where(fn ($s) => $s->where('titulo', 'ilike', $t)
                    ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'ilike', $t)
                        ->orWhere('apellidos', 'ilike', $t)
                        ->orWhere('numero_documento', 'ilike', $t)));
            })
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
            ->orderByDesc('fecha_estudio')->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.estudios.index', [
            'estudios' => $estudios,
            'totales' => [
                'estudios' => EstudioImagen::count(),
                'panoramicas' => EstudioImagen::where('tipo', 'PANORAMICA')->count(),
                'esteMes' => EstudioImagen::whereBetween('fecha_estudio', [now()->startOfMonth(), now()->endOfMonth()])->count(),
                'pacientes' => EstudioImagen::distinct('paciente_id')->count('paciente_id'),
            ],
        ]);
    }

    /** Galería de un paciente: la pestaña "Panorámicas" de su ficha. */
    public function porPaciente(Request $request, Paciente $paciente): View
    {
        return view('admin.estudios.paciente', [
            'paciente' => $paciente,
            'estudios' => $paciente->estudios()
                ->with('doctor')
                ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->tipo))
                ->orderByDesc('fecha_estudio')->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $paciente = $request->filled('paciente_id') ? Paciente::find($request->paciente_id) : null;

        return view('admin.estudios.form', [
            'estudio' => new EstudioImagen([
                'tipo' => 'PANORAMICA',
                'fecha_estudio' => now()->toDateString(),
                'paciente_id' => $paciente?->id,
                'cita_id' => $request->query('cita_id'),
                'doctor_id' => $request->user()->doctor?->id,
            ]),
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $paciente
                ? Cita::where('paciente_id', $paciente->id)->with('tratamiento')->orderByDesc('fecha')->limit(30)->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request, archivoObligatorio: true);

        $archivo = $request->file('archivo');

        $estudio = EstudioImagen::create($datos + [
            'usuario_id' => $request->user()->id,
            'archivo' => $archivo->store('estudios/'.$datos['paciente_id'], EstudioImagen::DISCO),
            'nombre_original' => $archivo->getClientOriginalName(),
            // MIME detectado del contenido real, no del que declara el navegador.
            'mime' => $archivo->getMimeType(),
            'tamano' => $archivo->getSize(),
        ]);

        return redirect()->route('admin.estudios.paciente', $estudio->paciente_id)
            ->with('exito', 'El estudio fue cargado.');
    }

    public function edit(EstudioImagen $estudio): View
    {
        return view('admin.estudios.form', [
            'estudio' => $estudio,
            'pacientes' => Paciente::activos()->orderBy('apellidos')->get(),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $estudio->paciente_id)
                ->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, EstudioImagen $estudio): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('archivo')) {
            Storage::disk(EstudioImagen::DISCO)->delete($estudio->archivo);

            $archivo = $request->file('archivo');
            $datos += [
                'archivo' => $archivo->store('estudios/'.$datos['paciente_id'], EstudioImagen::DISCO),
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getMimeType(),
                'tamano' => $archivo->getSize(),
            ];
        }

        $estudio->update($datos);

        return redirect()->route('admin.estudios.paciente', $estudio->paciente_id)
            ->with('exito', 'El estudio fue actualizado.');
    }

    public function destroy(EstudioImagen $estudio): RedirectResponse
    {
        $paciente = $estudio->paciente_id;

        // Borrado lógico: el archivo se conserva para poder restaurar el estudio.
        $estudio->delete();

        return redirect()->route('admin.estudios.paciente', $paciente)
            ->with('exito', 'El estudio fue eliminado.');
    }

    /** Muestra el archivo en línea (visor y miniaturas) desde el disco privado. */
    public function ver(EstudioImagen $estudio): Response
    {
        abort_unless($estudio->archivoExiste(), 404, 'El archivo del estudio ya no está disponible.');

        return Storage::disk(EstudioImagen::DISCO)->response($estudio->archivo, null, [
            'Content-Type' => $estudio->mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Entrega el archivo original con su nombre de subida. */
    public function descargar(EstudioImagen $estudio): StreamedResponse
    {
        abort_unless($estudio->archivoExiste(), 404, 'El archivo del estudio ya no está disponible.');

        return Storage::disk(EstudioImagen::DISCO)->download(
            $estudio->archivo,
            $estudio->nombre_original ?: basename($estudio->archivo)
        );
    }

    /** Compara dos estudios (imágenes) del mismo paciente lado a lado. */
    public function comparar(Request $request): View
    {
        $a = EstudioImagen::find($request->integer('a'));
        $b = EstudioImagen::find($request->integer('b'));

        abort_if(! $a || ! $b, 404, 'Debes elegir dos estudios para comparar.');
        abort_if($a->paciente_id !== $b->paciente_id, 404, 'Los estudios deben pertenecer al mismo paciente.');
        abort_unless($a->es_visualizable && $b->es_visualizable, 404, 'Solo se pueden comparar imágenes.');

        $paciente = $a->paciente;

        // Candidatos para los selectores: todas las imágenes del paciente.
        $estudios = $paciente->estudios()
            ->orderByDesc('fecha_estudio')->orderByDesc('id')
            ->get()
            ->filter(fn (EstudioImagen $e) => $e->es_visualizable)
            ->values();

        return view('admin.estudios.comparar', [
            'paciente' => $paciente,
            'a' => $a,
            'b' => $b,
            'estudios' => $estudios,
        ]);
    }

    /** Tipos de anotación que dibuja el visor sobre la imagen. */
    public const TIPOS_ANOTACION = ['lapiz', 'flecha', 'circulo', 'texto'];

    /**
     * Guarda las anotaciones dibujadas en el visor. Las coordenadas llegan
     * relativas a la imagen (0-1) para que escalen con cualquier zoom.
     */
    public function anotaciones(Request $request, EstudioImagen $estudio): JsonResponse
    {
        $coordenada = ['nullable', 'numeric', 'between:-1,2'];

        $datos = $request->validate([
            'anotaciones' => ['present', 'array', 'max:500'],
            'anotaciones.*.tipo' => ['required', 'string', 'in:'.implode(',', self::TIPOS_ANOTACION)],
            'anotaciones.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'anotaciones.*.grosor' => ['required', 'numeric', 'between:1,40'],
            'anotaciones.*.puntos' => ['nullable', 'array', 'max:3000'],
            'anotaciones.*.puntos.*' => ['array', 'size:2'],
            'anotaciones.*.puntos.*.*' => ['numeric', 'between:-1,2'],
            'anotaciones.*.desde' => ['nullable', 'array'],
            'anotaciones.*.desde.x' => $coordenada,
            'anotaciones.*.desde.y' => $coordenada,
            'anotaciones.*.hasta' => ['nullable', 'array'],
            'anotaciones.*.hasta.x' => $coordenada,
            'anotaciones.*.hasta.y' => $coordenada,
            'anotaciones.*.centro' => ['nullable', 'array'],
            'anotaciones.*.centro.x' => $coordenada,
            'anotaciones.*.centro.y' => $coordenada,
            'anotaciones.*.radio' => ['nullable', 'numeric', 'between:0,2'],
            'anotaciones.*.texto' => ['nullable', 'string', 'max:200'],
            'anotaciones.*.x' => $coordenada,
            'anotaciones.*.y' => $coordenada,
        ], [], ['anotaciones' => 'anotaciones']);

        $limpias = [];

        foreach ($datos['anotaciones'] as $a) {
            $item = [
                'tipo' => $a['tipo'],
                'color' => strtolower($a['color']),
                'grosor' => (float) $a['grosor'],
            ];

            $item += match ($a['tipo']) {
                'lapiz' => ['puntos' => array_map(fn ($p) => [(float) $p[0], (float) $p[1]], $a['puntos'] ?? [])],
                'flecha' => [
                    'desde' => ['x' => (float) ($a['desde']['x'] ?? 0), 'y' => (float) ($a['desde']['y'] ?? 0)],
                    'hasta' => ['x' => (float) ($a['hasta']['x'] ?? 0), 'y' => (float) ($a['hasta']['y'] ?? 0)],
                ],
                'circulo' => [
                    'centro' => ['x' => (float) ($a['centro']['x'] ?? 0), 'y' => (float) ($a['centro']['y'] ?? 0)],
                    'radio' => (float) ($a['radio'] ?? 0),
                ],
                'texto' => [
                    'texto' => trim((string) ($a['texto'] ?? '')),
                    'x' => (float) ($a['x'] ?? 0),
                    'y' => (float) ($a['y'] ?? 0),
                ],
            };

            $limpias[] = $item;
        }

        $estudio->update(['anotaciones' => $limpias]);

        return response()->json(['ok' => true, 'total' => count($limpias)]);
    }

    private function validar(Request $request, bool $archivoObligatorio = false): array
    {
        $datos = $request->validate([
            'paciente_id' => ['required', 'exists:pacientes,id'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(EstudioImagen::TIPOS))],
            'titulo' => ['required', 'string', 'max:150'],
            'fecha_estudio' => ['required', 'date', 'before_or_equal:today'],
            'piezas_referidas' => ['nullable', 'string', 'max:255'],
            'hallazgos' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'archivo' => [$archivoObligatorio ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480'],
        ], [], [
            'paciente_id' => 'paciente',
            'fecha_estudio' => 'fecha del estudio',
            'piezas_referidas' => 'piezas referidas',
        ]);

        // El archivo subido se procesa aparte: nunca se guarda su ruta temporal.
        unset($datos['archivo']);

        return $datos;
    }
}
