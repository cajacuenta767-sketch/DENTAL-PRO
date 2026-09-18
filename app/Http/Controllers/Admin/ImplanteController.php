<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Doctor;
use App\Models\ImplantePaciente;
use App\Models\Paciente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ImplanteController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'marca' => ['nullable', 'string'],
            'estado' => ['nullable', 'string'],
            'buscar' => ['nullable', 'string'],
        ]);

        $query = ImplantePaciente::query()
            ->with(['paciente', 'doctor'])
            ->when($request->filled('marca'), fn ($q) => $q->where('marca', 'like', "%{$request->marca}%"))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('buscar'), function ($q) use ($request) {
                $b = "%{$request->buscar}%";
                $q->where('numero_lote', 'like', $b)
                  ->orWhere('modelo', 'like', $b)
                  ->orWhereHas('paciente', fn ($p) => $p->where('nombres', 'like', $b)->orWhere('apellidos', 'like', $b));
            })
            ->orderByDesc('fecha_colocacion')->orderByDesc('id');

        return view('admin.implantes.index', [
            'implantes' => $query->paginate(15)->withQueryString(),
            'estados' => ImplantePaciente::ESTADOS,
        ]);
    }

    public function paciente(Paciente $paciente): View
    {
        return view('admin.implantes.paciente', [
            'paciente' => $paciente,
            'implantes' => $paciente->implantes()->with('doctor')->orderByDesc('fecha_colocacion')->get(),
            'estados' => ImplantePaciente::ESTADOS,
        ]);
    }

    public function create(Paciente $paciente): View
    {
        return view('admin.implantes.form', [
            'paciente' => $paciente,
            'implante' => new ImplantePaciente([
                'fecha_colocacion' => now()->toDateString(),
                'tipo_conexion' => 'CONO_MORSE',
                'estado' => 'COLOCADO',
                'posicion_fdi' => 16,
                'diametro_mm' => 4.10,
                'longitud_mm' => 10.00,
            ]),
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'estados' => ImplantePaciente::ESTADOS,
            'conexiones' => ImplantePaciente::CONEXIONES,
        ]);
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['paciente_id'] = $paciente->id;
        $datos['qr_pasaporte_token'] = Str::random(32);

        $implante = ImplantePaciente::create($datos);

        Auditoria::registrar('CREAR', $implante, "Registró implante {$implante->marca} {$implante->modelo} posición {$implante->posicion_fdi} para {$paciente->nombre_completo}");

        return redirect()->route('admin.implantes.paciente', $paciente)
            ->with('exito', "Implante pieza {$implante->posicion_fdi} registrado y pasaporte digital emitido.");
    }

    public function edit(ImplantePaciente $implante): View
    {
        return view('admin.implantes.form', [
            'paciente' => $implante->paciente,
            'implante' => $implante,
            'doctores' => Doctor::activos()->orderBy('apellidos')->get(),
            'estados' => ImplantePaciente::ESTADOS,
            'conexiones' => ImplantePaciente::CONEXIONES,
        ]);
    }

    public function update(Request $request, ImplantePaciente $implante): RedirectResponse
    {
        $datos = $this->validar($request);
        $implante->update($datos);

        Auditoria::registrar('EDITAR', $implante, "Actualizó implante posición {$implante->posicion_fdi}");

        return redirect()->route('admin.implantes.paciente', $implante->paciente)
            ->with('exito', "Datos del implante actualizados.");
    }

    public function destroy(ImplantePaciente $implante): RedirectResponse
    {
        $paciente = $implante->paciente;
        $fdi = $implante->posicion_fdi;

        Auditoria::registrar('ELIMINAR', $implante, "Eliminó implante posición {$fdi}");
        $implante->delete();

        return redirect()->route('admin.implantes.paciente', $paciente)
            ->with('exito', "Registro de implante pieza {$fdi} eliminado.");
    }

    public function pasaporte(ImplantePaciente $implante): Response
    {
        $implante->load(['paciente', 'doctor']);
        $qrDataUri = app(\App\Services\QrService::class)->dataUri(
            url("/admin/implantes/{$implante->id}/pasaporte"),
            140
        );

        return Pdf::loadView('pdf.pasaporte-implante', [
            'implante' => $implante,
            'clinica' => Ajuste::actual(),
            'estados' => ImplantePaciente::ESTADOS,
            'conexiones' => ImplantePaciente::CONEXIONES,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('a5', 'landscape')
            ->stream("pasaporte-implante-{$implante->posicion_fdi}-{$implante->paciente->numero_documento}.pdf");
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'posicion_fdi' => ['required', 'integer', 'between:11,48'],
            'marca' => ['required', 'string', 'max:100'],
            'modelo' => ['required', 'string', 'max:100'],
            'numero_lote' => ['required', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:100'],
            'diametro_mm' => ['required', 'numeric', 'between:2.0,7.0'],
            'longitud_mm' => ['required', 'numeric', 'between:4.0,25.0'],
            'tipo_conexion' => ['required', 'string', 'in:' . implode(',', array_keys(ImplantePaciente::CONEXIONES))],
            'torque_insercion_ncm' => ['nullable', 'numeric', 'between:10,90'],
            'isq_estabilidad' => ['nullable', 'integer', 'between:20,99'],
            'injerto_oseo' => ['nullable', 'string', 'max:150'],
            'membrana' => ['nullable', 'string', 'max:150'],
            'fecha_colocacion' => ['required', 'date'],
            'fecha_rehabilitacion' => ['nullable', 'date'],
            'doctor_id' => ['nullable', 'exists:doctores,id'],
            'estado' => ['required', 'string', 'in:' . implode(',', array_keys(ImplantePaciente::ESTADOS))],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
