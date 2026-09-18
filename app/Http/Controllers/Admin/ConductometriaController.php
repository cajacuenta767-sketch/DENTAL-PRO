<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Conductometria;
use App\Models\Doctor;
use App\Models\Paciente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ConductometriaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        return view('admin.conductometrias.index', [
            'paciente' => $paciente,
            'conductometrias' => $paciente->conductometrias()
                ->with(['doctor', 'cita.tratamiento', 'usuario'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        $dienteSugerido = (int) $request->query('diente', 16);

        // Preconfigurar conductos estándar según el diente
        $conductosDefecto = $this->conductosSugeridosParaDiente($dienteSugerido);

        return view('admin.conductometrias.form', [
            'paciente' => $paciente,
            'conductometria' => new Conductometria([
                'fecha' => now()->toDateString(),
                'doctor_id' => $request->user()->doctor?->id,
                'cita_id' => $request->query('cita_id'),
                'diente' => $dienteSugerido,
                'estado' => 'EN_TRATAMIENTO',
                'conductos' => $conductosDefecto,
            ]),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $paciente->citas()->with('tratamiento')->orderByDesc('fecha')->limit(10)->get(),
            'diagnosticosPulpares' => Conductometria::DIAGNOSTICOS_PULPARES,
            'diagnosticosPeriapicales' => Conductometria::DIAGNOSTICOS_PERIAPICALES,
            'estados' => Conductometria::ESTADOS,
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['paciente_id'] = $paciente->id;
        $datos['usuario_id'] = $request->user()->id;

        $conductometria = Conductometria::create($datos);

        Auditoria::registrar('CREAR', $conductometria, "Registró matriz de conductometría pieza {$conductometria->diente} para {$paciente->nombre_completo}");

        return redirect()->route('admin.conductometrias.index', $paciente)
            ->with('exito', "Matriz de conductometría para la pieza {$conductometria->diente} registrada con éxito.");
    }

    public function edit(Conductometria $conductometria): View
    {
        $paciente = $conductometria->paciente;

        return view('admin.conductometrias.form', [
            'paciente' => $paciente,
            'conductometria' => $conductometria,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $paciente->citas()->with('tratamiento')->orderByDesc('fecha')->limit(10)->get(),
            'diagnosticosPulpares' => Conductometria::DIAGNOSTICOS_PULPARES,
            'diagnosticosPeriapicales' => Conductometria::DIAGNOSTICOS_PERIAPICALES,
            'estados' => Conductometria::ESTADOS,
        ]);
    }

    public function update(Request $request, Conductometria $conductometria): RedirectResponse
    {
        $datos = $this->validar($request);
        $conductometria->update($datos);

        Auditoria::registrar('EDITAR', $conductometria, "Actualizó matriz de conductometría pieza {$conductometria->diente}");

        return redirect()->route('admin.conductometrias.index', $conductometria->paciente)
            ->with('exito', "Matriz de conductometría pieza {$conductometria->diente} actualizada.");
    }

    public function destroy(Conductometria $conductometria): RedirectResponse
    {
        $paciente = $conductometria->paciente;
        $diente = $conductometria->diente;

        Auditoria::registrar('ELIMINAR', $conductometria, "Eliminó conductometría pieza {$diente}");
        $conductometria->delete();

        return redirect()->route('admin.conductometrias.index', $paciente)
            ->with('exito', "Conductometría de la pieza {$diente} eliminada.");
    }

    public function pdf(Conductometria $conductometria): Response
    {
        $conductometria->load(['paciente', 'doctor', 'usuario']);

        return Pdf::loadView('pdf.conductometria', [
            'conductometria' => $conductometria,
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter')
            ->stream("conductometria-pieza-{$conductometria->diente}-{$conductometria->paciente->numero_documento}.pdf");
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'diente' => ['required', 'integer', 'between:11,85'],
            'fecha' => ['required', 'date'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'diagnostico_pulpar' => ['nullable', 'string', 'max:100'],
            'diagnostico_periapical' => ['nullable', 'string', 'max:100'],
            'solucion_irrigante' => ['nullable', 'string', 'max:150'],
            'medicacion_intraconducto' => ['nullable', 'string', 'max:150'],
            'cemento_sellador' => ['nullable', 'string', 'max:150'],
            'tecnica_obturacion' => ['nullable', 'string', 'max:150'],
            'estado' => ['required', 'string', 'in:' . implode(',', array_keys(Conductometria::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'conductos' => ['required', 'array', 'min:1'],
            'conductos.*.nombre' => ['required', 'string', 'max:50'],
            'conductos.*.referencia' => ['nullable', 'string', 'max:100'],
            'conductos.*.longitud_aparente' => ['nullable', 'numeric', 'min:0', 'max:45'],
            'conductos.*.longitud_trabajo' => ['nullable', 'numeric', 'min:0', 'max:45'],
            'conductos.*.lima_apical' => ['nullable', 'string', 'max:50'],
            'conductos.*.tecnica' => ['nullable', 'string', 'max:100'],
            'conductos.*.observaciones' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function conductosSugeridosParaDiente(int $diente): array
    {
        // Si es molar superior (16, 17, 18, 26, 27, 28)
        if (in_array($diente, [16, 17, 18, 26, 27, 28])) {
            return [
                ['nombre' => 'MV1 (Mesio-vestibular 1)', 'referencia' => 'Cúspide MV', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'MV2 (Mesio-vestibular 2)', 'referencia' => 'Cúspide MV', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '20.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'DV (Disto-vestibular)', 'referencia' => 'Cúspide DV', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'P (Palatino)', 'referencia' => 'Cúspide Palatina', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '35.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
            ];
        }

        // Si es molar inferior (36, 37, 38, 46, 47, 48)
        if (in_array($diente, [36, 37, 38, 46, 47, 48])) {
            return [
                ['nombre' => 'MV (Mesio-vestibular)', 'referencia' => 'Cúspide MV', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'ML (Mesio-lingual)', 'referencia' => 'Cúspide ML', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'D (Distal)', 'referencia' => 'Cúspide Distal', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '35.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
            ];
        }

        // Premolares superiores (14, 15, 24, 25)
        if (in_array($diente, [14, 15, 24, 25])) {
            return [
                ['nombre' => 'Vestibular', 'referencia' => 'Cúspide V', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
                ['nombre' => 'Palatino', 'referencia' => 'Cúspide P', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '25.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
            ];
        }

        // Por defecto uniradicular (Incisivos, Caninos, Premolares inf)
        return [
            ['nombre' => 'Conducto Principal', 'referencia' => 'Borde Incisal / Cúspide', 'longitud_aparente' => null, 'longitud_trabajo' => null, 'lima_apical' => '30.04', 'tecnica' => 'Rotatoria', 'observaciones' => ''],
        ];
    }
}
