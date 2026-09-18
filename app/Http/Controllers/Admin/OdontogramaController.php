<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Rules\CitaDelPaciente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OdontogramaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        return view('admin.odontogramas.index', [
            'paciente' => $paciente,
            'odontogramas' => $paciente->odontogramas()
                ->with(['doctor', 'cita.tratamiento'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        $tipo = $request->query('tipo') === 'INFANTIL' ? 'INFANTIL' : 'ADULTO';

        return view('admin.odontogramas.form', [
            'paciente' => $paciente,
            'odontograma' => new Odontograma([
                'tipo' => $tipo,
                'fecha' => now()->toDateString(),
                'doctor_id' => $request->user()->doctor?->id,
                'cita_id' => $request->query('cita_id'),
                'piezas' => $this->piezasEnBlanco($tipo),
            ]),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $paciente->id)->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request, $paciente);
        $datos['paciente_id'] = $paciente->id;

        Odontograma::create($datos);

        return redirect()->route('admin.odontogramas.index', $paciente)
            ->with('exito', 'El odontograma fue registrado.');
    }

    public function edit(Request $request, Odontograma $odontograma): View
    {
        // Cambiar la dentición desde el formulario recarga con ?tipo=… y
        // repinta el mapa en blanco de esa numeración sin tocar lo guardado.
        if (in_array($request->query('tipo'), ['ADULTO', 'INFANTIL'], true)
            && $request->query('tipo') !== $odontograma->tipo) {
            $odontograma->tipo = $request->query('tipo');
            $odontograma->piezas = $this->piezasEnBlanco($odontograma->tipo);
        }

        return view('admin.odontogramas.form', [
            'paciente' => $odontograma->paciente,
            'odontograma' => $odontograma,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'citas' => Cita::where('paciente_id', $odontograma->paciente_id)
                ->with('tratamiento')->orderByDesc('fecha')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, Odontograma $odontograma): RedirectResponse
    {
        $odontograma->update($this->validar($request, $odontograma->paciente));

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

    private function validar(Request $request, Paciente $paciente): array
    {
        $datos = $request->validate([
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'cita_id' => ['nullable', 'exists:citas,id', new CitaDelPaciente($paciente->id)],
            'tipo' => ['required', 'in:ADULTO,INFANTIL'],
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

            $limpio[(string) $numero] = [
                'estado' => in_array($estado, $validos, true) ? $estado : 'sano',
                'caras' => $caras,
                'nota' => isset($pieza['nota']) ? mb_substr((string) $pieza['nota'], 0, 255) : null,
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
            ]])
            ->all();
    }
}
