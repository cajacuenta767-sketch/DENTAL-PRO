<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Models\Tratamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OdontogramaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        $odontogramas = $paciente->odontogramas()
            ->with(['doctor', 'cita.tratamiento'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(10);

        // Para cada odontograma de la página, el inmediatamente anterior de la
        // misma dentición: sirve para desplegar la comparación visual.
        $historico = $paciente->odontogramas()
            ->orderByDesc('fecha')->orderByDesc('id')
            ->get(['id', 'tipo', 'fecha', 'piezas']);

        $anteriores = [];

        foreach ($odontogramas as $odontograma) {
            $anteriores[$odontograma->id] = $historico->first(fn (Odontograma $otro) => $otro->tipo === $odontograma->tipo
                && $otro->id !== $odontograma->id
                && ($otro->fecha->lt($odontograma->fecha)
                    || ($otro->fecha->eq($odontograma->fecha) && $otro->id < $odontograma->id)));
        }

        return view('admin.odontogramas.index', [
            'paciente' => $paciente,
            'odontogramas' => $odontogramas,
            'anteriores' => $anteriores,
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        $tipo = in_array($request->query('tipo'), ['INFANTIL', 'MIXTO'], true) ? $request->query('tipo') : 'ADULTO';

        // El último odontograma del paciente sirve de punto de partida y de
        // referencia para marcar qué cambió desde entonces.
        $anterior = $paciente->odontogramas()
            ->where('tipo', $tipo)
            ->orderByDesc('fecha')->orderByDesc('id')
            ->first();

        $piezas = $request->query('desde') === 'ultimo' && $anterior
            ? $anterior->piezas
            : $this->piezasEnBlanco($tipo);

        return view('admin.odontogramas.form', [
            'paciente' => $paciente,
            'odontograma' => new Odontograma([
                'tipo' => $tipo,
                'fecha' => now()->toDateString(),
                'doctor_id' => $request->user()->doctor?->id,
                'cita_id' => $request->query('cita_id'),
                'piezas' => $piezas,
            ]),
            'anterior' => $anterior,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $paciente->id)->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(['id', 'nombre', 'precio']),
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['paciente_id'] = $paciente->id;

        Odontograma::create($datos);

        return redirect()->route('admin.odontogramas.index', $paciente)
            ->with('exito', 'El odontograma fue registrado.');
    }

    public function edit(Request $request, Odontograma $odontograma): View
    {
        // Cambiar la dentición desde el formulario recarga con ?tipo=… y
        // repinta el mapa en blanco de esa numeración sin tocar lo guardado.
        if (in_array($request->query('tipo'), ['ADULTO', 'INFANTIL', 'MIXTO'], true)
            && $request->query('tipo') !== $odontograma->tipo) {
            $odontograma->tipo = $request->query('tipo');
            $odontograma->piezas = $this->piezasEnBlanco($odontograma->tipo);
        }

        $anterior = $odontograma->paciente->odontogramas()
            ->where('tipo', $odontograma->tipo)
            ->where('id', '!=', $odontograma->id)
            ->where(fn ($q) => $q->where('fecha', '<', $odontograma->fecha)
                ->orWhere(fn ($r) => $r->where('fecha', $odontograma->fecha)->where('id', '<', $odontograma->id)))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->first();

        return view('admin.odontogramas.form', [
            'paciente' => $odontograma->paciente,
            'odontograma' => $odontograma,
            'anterior' => $anterior,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $odontograma->paciente_id)
                ->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
            'tratamientos' => Tratamiento::activos()->orderBy('nombre')->get(['id', 'nombre', 'precio']),
        ]);
    }

    public function update(Request $request, Odontograma $odontograma): RedirectResponse
    {
        $odontograma->update($this->validar($request));

        return redirect()->route('admin.odontogramas.index', $odontograma->paciente)
            ->with('exito', 'El odontograma fue actualizado.');
    }

    public function destroy(Odontograma $odontograma): RedirectResponse
    {
        $paciente = $odontograma->paciente;
        $odontograma->delete();

        return redirect()->route('admin.odontogramas.index', $paciente)
            ->with('exito', 'El odontograma fue eliminado.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id'],
            'tipo' => ['required', 'in:ADULTO,INFANTIL,MIXTO'],
            'escala_frankl' => ['nullable', 'integer', 'between:1,4'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'piezas' => ['required', 'string'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [], ['doctor_id' => 'doctor', 'piezas' => 'registro de piezas']);

        $datos['piezas'] = $this->sanearPiezas($datos['piezas'], $datos['tipo']);

        return $datos;
    }

    /**
     * El formulario envía el mapa de piezas como JSON. Lo normalizamos contra
     * la numeración FDI y el catálogo de hallazgos para no guardar basura.
     */
    private function sanearPiezas(string $json, string $tipo): array
    {
        $recibido = json_decode($json, true);
        $recibido = is_array($recibido) ? $recibido : [];

        $validos = array_keys(Odontograma::ESTADOS);
        $limpio = [];

        foreach (collect(Odontograma::cuadrantes($tipo))->flatten() as $numero) {
            $pieza = $recibido[(string) $numero] ?? [];

            $estado = $pieza['estado'] ?? 'sano';
            $caras = [];

            foreach (Odontograma::CARAS as $cara) {
                $valor = $pieza['caras'][$cara] ?? 'sano';
                $caras[$cara] = in_array($valor, $validos, true) ? $valor : 'sano';
            }

            $movilidad = (int) ($pieza['movilidad'] ?? 0);

            $limpio[(string) $numero] = [
                'estado' => in_array($estado, $validos, true) ? $estado : 'sano',
                'caras' => $caras,
                'nota' => isset($pieza['nota']) && $pieza['nota'] !== '' ? mb_substr((string) $pieza['nota'], 0, 255) : null,
                'movilidad' => array_key_exists($movilidad, Odontograma::MOVILIDAD) ? $movilidad : 0,
                'urgente' => ! empty($pieza['urgente']),
            ];
        }

        return $limpio;
    }

    private function piezasEnBlanco(string $tipo): array
    {
        return collect(Odontograma::cuadrantes($tipo))->flatten()
            ->mapWithKeys(fn ($numero) => [(string) $numero => [
                'estado' => 'sano',
                'caras' => array_fill_keys(Odontograma::CARAS, 'sano'),
                'nota' => null,
                'movilidad' => 0,
                'urgente' => false,
            ]])
            ->all();
    }
}
