<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invitacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvitacionController extends Controller
{
    private function clinica(Request $request): int
    {
        abort_unless($request->user()?->clinica_id && $request->user()->hasAnyRole(['ADMINISTRADOR', 'SUPER ADMINISTRADOR']), 403);

        return (int) $request->user()->clinica_id;
    }

    public function index(Request $request): View
    {
        $clinicaId = $this->clinica($request);

        return view('admin.invitaciones.index', [
            'invitaciones' => Invitacion::with('usuario')->where('clinica_id', $clinicaId)
                ->whereIn('rol', ['DOCTOR', 'RECEPCION', 'PACIENTE'])->latest()->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $clinicaId = $this->clinica($request);
        $datos = $request->validate([
            'email' => ['nullable', 'email', 'max:150'],
            'rol' => ['required', Rule::in(['DOCTOR', 'RECEPCION', 'PACIENTE'])],
            'dias' => ['required', 'integer', 'min:1', 'max:90'],
        ]);
        $codigo = Invitacion::generarCodigo($datos['rol'] === 'PACIENTE' ? 'PAC' : 'EQUIPO');
        $invitacion = Invitacion::create([
            'clinica_id' => $clinicaId,
            'creada_por' => $request->user()->id,
            'email' => $datos['email'] ?: null,
            'rol' => $datos['rol'],
            'codigo_hash' => Invitacion::hashCodigo($codigo),
            'codigo_cifrado' => $codigo,
            'codigo_visible' => '••••-'.substr($codigo, -4),
            'vence_en' => now()->addDays((int) $datos['dias']),
        ]);

        if ($invitacion->email) {
            Mail::raw("Te invitaron a {$request->user()->clinica->nombre} en OdontoSuite. Regístrate en ".route('register', ['codigo' => $codigo])."\n\nCódigo: {$codigo}",
                fn ($mensaje) => $mensaje->to($invitacion->email)->subject('Invitación a '.$request->user()->clinica->nombre));
        }

        return back()->with('exito', 'Invitación creada correctamente.')->with('codigo_generado', $codigo);
    }

    public function revocar(Request $request, Invitacion $invitacion): RedirectResponse
    {
        $clinicaId = $this->clinica($request);
        abort_unless((int) $invitacion->clinica_id === $clinicaId, 404);
        $invitacion->update(['activa' => false]);

        return back()->with('exito', 'La invitación fue revocada.');
    }
}
