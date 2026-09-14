<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Models\Periodontograma;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PeriodontogramaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        return view('admin.periodontogramas.index', [
            'paciente' => $paciente,
            'periodontogramas' => $paciente->periodontogramas()
                ->with(['doctor', 'cita.tratamiento'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        // El último registro sirve de punto de partida para un control:
        // se copian sondaje, sangrado y placa y el clínico solo corrige.
        $anterior = $paciente->periodontogramas()
            ->orderByDesc('fecha')->orderByDesc('id')
            ->first();

        $piezas = $request->query('desde') === 'ultimo' && $anterior
            ? $this->sanearPiezas(json_encode($anterior->piezas ?? []))
            : Periodontograma::piezasEnBlanco();

        return view('admin.periodontogramas.form', [
            'paciente' => $paciente,
            'periodontograma' => new Periodontograma([
                'fecha' => now()->toDateString(),
                'doctor_id' => $request->user()->doctor?->id,
                'cita_id' => $request->query('cita_id'),
                'piezas' => $piezas,
            ]),
            'anterior' => $anterior,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $this->citasDe($paciente),
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['paciente_id'] = $paciente->id;
        $datos['usuario_id'] = $request->user()->id;

        Periodontograma::create($datos);

        return redirect()->route('admin.periodontogramas.index', $paciente)
            ->with('exito', 'El periodontograma fue registrado.');
    }

    public function edit(Periodontograma $periodontograma): View
    {
        $anterior = $periodontograma->paciente->periodontogramas()
            ->where('id', '!=', $periodontograma->id)
            ->where(fn ($q) => $q->where('fecha', '<', $periodontograma->fecha)
                ->orWhere(fn ($r) => $r->where('fecha', $periodontograma->fecha)->where('id', '<', $periodontograma->id)))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->first();

        return view('admin.periodontogramas.form', [
            'paciente' => $periodontograma->paciente,
            'periodontograma' => $periodontograma,
            'anterior' => $anterior,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => $this->citasDe($periodontograma->paciente),
        ]);
    }

    public function update(Request $request, Periodontograma $periodontograma): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['usuario_id'] = $request->user()->id;

        $periodontograma->update($datos);

        return redirect()->route('admin.periodontogramas.index', $periodontograma->paciente)
            ->with('exito', 'El periodontograma fue actualizado.');
    }

    public function destroy(Periodontograma $periodontograma): RedirectResponse
    {
        $paciente = $periodontograma->paciente;
        $periodontograma->delete();

        return redirect()->route('admin.periodontogramas.index', $paciente)
            ->with('exito', 'El periodontograma fue eliminado.');
    }

    public function pdf(Periodontograma $periodontograma): Response
    {
        $periodontograma->load(['paciente', 'doctor.especialidad', 'cita.tratamiento']);

        return Pdf::loadView('pdf.periodontograma', [
            'periodontograma' => $periodontograma,
            'indices' => $periodontograma->indices(),
            'diagnostico' => $periodontograma->diagnosticoOrientativo(),
            'clinica' => Ajuste::actual(),
        ])->setPaper('letter', 'landscape')
            ->stream('periodontograma-'.$periodontograma->paciente->numero_documento.'-'.$periodontograma->fecha->format('Ymd').'.pdf');
    }

    private function citasDe(Paciente $paciente)
    {
        return Cita::where('paciente_id', $paciente->id)
            ->with('tratamiento')->orderByDesc('fecha')->limit(30)->get();
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'piezas' => ['required', 'string'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [], ['doctor_id' => 'doctor', 'piezas' => 'registro de sondaje']);

        $datos['piezas'] = $this->sanearPiezas($datos['piezas']);

        return $datos;
    }

    /**
     * El formulario envía el sondaje como JSON. Se normaliza contra la
     * numeración FDI adulta y los rangos clínicos: profundidad 0-15 mm,
     * recesión 0-10 mm, movilidad y furca 0-3. Todo lo demás se descarta.
     */
    private function sanearPiezas(string $json): array
    {
        $recibido = json_decode($json, true);
        $recibido = is_array($recibido) ? $recibido : [];

        $limpio = [];

        foreach (collect(Odontograma::PIEZAS_ADULTO)->flatten() as $numero) {
            $pieza = $recibido[(string) $numero] ?? [];
            $pieza = is_array($pieza) ? $pieza : [];

            $sondaje = [];
            $sangrado = [];
            $placa = [];
            $recesion = [];

            foreach (Periodontograma::SITIOS as $sitio) {
                $sondaje[$sitio] = $this->enteroEnRango($pieza['sondaje'][$sitio] ?? null, 0, 15);
                $recesion[$sitio] = $this->enteroEnRango($pieza['recesion'][$sitio] ?? null, 0, 10);
                $sangrado[$sitio] = $this->booleano($pieza['sangrado'][$sitio] ?? false);
                $placa[$sitio] = $this->booleano($pieza['placa'][$sitio] ?? false);
            }

            $limpio[(string) $numero] = [
                'ausente' => $this->booleano($pieza['ausente'] ?? false),
                'sondaje' => $sondaje,
                'sangrado' => $sangrado,
                'placa' => $placa,
                'recesion' => $recesion,
                'movilidad' => $this->enteroEnRango($pieza['movilidad'] ?? 0, 0, 3) ?? 0,
                'furca' => $this->enteroEnRango($pieza['furca'] ?? 0, 0, 3) ?? 0,
                'nota' => isset($pieza['nota']) && trim((string) $pieza['nota']) !== ''
                    ? mb_substr(trim((string) $pieza['nota']), 0, 255)
                    : null,
            ];
        }

        return $limpio;
    }

    /** Entero dentro del rango o null si viene vacío, no numérico o fuera de rango. */
    private function enteroEnRango(mixed $valor, int $min, int $max): ?int
    {
        if ($valor === null || $valor === '' || is_bool($valor) || is_array($valor)) {
            return null;
        }

        if (! is_numeric($valor)) {
            return null;
        }

        $entero = (int) $valor;

        if ((float) $valor != $entero) {
            return null;
        }

        return $entero >= $min && $entero <= $max ? $entero : null;
    }

    private function booleano(mixed $valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }
}
