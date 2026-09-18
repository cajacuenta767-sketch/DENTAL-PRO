<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Doctor;
use App\Models\Paciente;
use App\Models\TrazadoCefalometrico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CefalometriaController extends Controller
{
    public function index(Paciente $paciente): View
    {
        return view('admin.cefalometrias.index', [
            'paciente' => $paciente,
            'cefalometrias' => $paciente->cefalometrias()
                ->with(['doctor', 'usuario'])
                ->orderByDesc('fecha')->orderByDesc('id')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Paciente $paciente): View
    {
        return view('admin.cefalometrias.form', [
            'paciente' => $paciente,
            'cefalometria' => new TrazadoCefalometrico([
                'fecha' => now()->toDateString(),
                'tipo_analisis' => 'STEINER',
                'doctor_id' => $request->user()->doctor?->id,
            ]),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'puntosClave' => TrazadoCefalometrico::PUNTOS_CLAVE,
            'diagnosticos' => TrazadoCefalometrico::DIAGNOSTICOS,
            'patrones' => TrazadoCefalometrico::PATRONES,
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('imagen_radiografia')) {
            $datos['imagen_radiografia'] = $request->file('imagen_radiografia')->store('cefalometrias', 'public');
        }

        // Si se ingresaron puntos pero faltan medidas, computar automáticamente
        $puntos = $datos['puntos'] ?? [];
        if (!empty($puntos)) {
            $analisis = TrazadoCefalometrico::computarAnalisis($puntos);
            $datos['medidas'] = array_merge($analisis['medidas'], $datos['medidas'] ?? []);
            if (empty($datos['diagnostico_esqueletico'])) {
                $datos['diagnostico_esqueletico'] = $analisis['diagnostico'];
            }
            if (empty($datos['patron_crecimiento'])) {
                $datos['patron_crecimiento'] = $analisis['patron'];
            }
        }

        $datos['paciente_id'] = $paciente->id;
        $datos['usuario_id'] = $request->user()->id;

        $cefalometria = TrazadoCefalometrico::create($datos);

        Auditoria::registrar('CREAR', $cefalometria, "Registró trazado cefalométrico para {$paciente->nombre_completo} [{$cefalometria->diagnostico_esqueletico}]");

        return redirect()->route('admin.cefalometrias.index', $paciente)
            ->with('exito', "Trazado cefalométrico registrado con éxito.");
    }

    public function show(TrazadoCefalometrico $cefalometria): View
    {
        $cefalometria->load(['paciente', 'doctor', 'usuario']);

        return view('admin.cefalometrias.show', [
            'cefalometria' => $cefalometria,
            'paciente' => $cefalometria->paciente,
            'puntosClave' => TrazadoCefalometrico::PUNTOS_CLAVE,
            'diagnosticos' => TrazadoCefalometrico::DIAGNOSTICOS,
            'patrones' => TrazadoCefalometrico::PATRONES,
        ]);
    }

    public function edit(TrazadoCefalometrico $cefalometria): View
    {
        return view('admin.cefalometrias.form', [
            'paciente' => $cefalometria->paciente,
            'cefalometria' => $cefalometria,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'puntosClave' => TrazadoCefalometrico::PUNTOS_CLAVE,
            'diagnosticos' => TrazadoCefalometrico::DIAGNOSTICOS,
            'patrones' => TrazadoCefalometrico::PATRONES,
        ]);
    }

    public function update(Request $request, TrazadoCefalometrico $cefalometria): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('imagen_radiografia')) {
            if ($cefalometria->imagen_radiografia && Storage::disk('public')->exists($cefalometria->imagen_radiografia)) {
                Storage::disk('public')->delete($cefalometria->imagen_radiografia);
            }
            $datos['imagen_radiografia'] = $request->file('imagen_radiografia')->store('cefalometrias', 'public');
        }

        $puntos = $datos['puntos'] ?? [];
        if (!empty($puntos)) {
            $analisis = TrazadoCefalometrico::computarAnalisis($puntos);
            $datos['medidas'] = array_merge($analisis['medidas'], $datos['medidas'] ?? []);
            if (empty($datos['diagnostico_esqueletico'])) {
                $datos['diagnostico_esqueletico'] = $analisis['diagnostico'];
            }
            if (empty($datos['patron_crecimiento'])) {
                $datos['patron_crecimiento'] = $analisis['patron'];
            }
        }

        $cefalometria->update($datos);

        Auditoria::registrar('EDITAR', $cefalometria, "Actualizó trazado cefalométrico ID {$cefalometria->id}");

        return redirect()->route('admin.cefalometrias.index', $cefalometria->paciente)
            ->with('exito', "Trazado cefalométrico actualizado.");
    }

    public function destroy(TrazadoCefalometrico $cefalometria): RedirectResponse
    {
        $paciente = $cefalometria->paciente;

        if ($cefalometria->imagen_radiografia && Storage::disk('public')->exists($cefalometria->imagen_radiografia)) {
            Storage::disk('public')->delete($cefalometria->imagen_radiografia);
        }

        Auditoria::registrar('ELIMINAR', $cefalometria, "Eliminó trazado cefalométrico ID {$cefalometria->id}");
        $cefalometria->delete();

        return redirect()->route('admin.cefalometrias.index', $paciente)
            ->with('exito', "Trazado cefalométrico eliminado.");
    }

    public function pdf(TrazadoCefalometrico $cefalometria): Response
    {
        $cefalometria->load(['paciente', 'doctor', 'usuario']);

        return Pdf::loadView('pdf.cefalometria', [
            'cefalometria' => $cefalometria,
            'clinica' => Ajuste::actual(),
            'diagnosticos' => TrazadoCefalometrico::DIAGNOSTICOS,
            'patrones' => TrazadoCefalometrico::PATRONES,
        ])->setPaper('letter')
            ->stream("cefalometria-{$cefalometria->paciente->numero_documento}-{$cefalometria->fecha->format('Ymd')}.pdf");
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'fecha' => ['required', 'date'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'tipo_analisis' => ['required', 'string', 'max:30'],
            'diagnostico_esqueletico' => ['nullable', 'string', 'max:50'],
            'patron_crecimiento' => ['nullable', 'string', 'max:50'],
            'imagen_radiografia' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:10240'],
            'interpretacion' => ['nullable', 'string', 'max:3000'],
            'plan_tratamiento' => ['nullable', 'string', 'max:3000'],
            'puntos' => ['nullable', 'array'],
            'medidas' => ['nullable', 'array'],
            'medidas.SNA' => ['nullable', 'numeric', 'between:50,120'],
            'medidas.SNB' => ['nullable', 'numeric', 'between:50,120'],
            'medidas.ANB' => ['nullable', 'numeric', 'between:-20,30'],
            'medidas.GoGn_SN' => ['nullable', 'numeric', 'between:10,60'],
            'medidas.UI_NA_deg' => ['nullable', 'numeric', 'between:0,60'],
            'medidas.UI_NA_mm' => ['nullable', 'numeric', 'between:-10,30'],
            'medidas.LI_NB_deg' => ['nullable', 'numeric', 'between:0,60'],
            'medidas.LI_NB_mm' => ['nullable', 'numeric', 'between:-10,30'],
        ]);
    }
}
