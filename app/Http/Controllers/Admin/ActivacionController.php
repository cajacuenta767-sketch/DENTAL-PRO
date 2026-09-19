<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinica;
use App\Models\Invitacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ActivacionController extends Controller
{
    private function autorizar(Request $request): void
    {
        abort_unless($request->user()?->esSuperAdministrador(), 403);
    }

    public function index(Request $request): View
    {
        $this->autorizar($request);

        return view('admin.activaciones.index', [
            'activaciones' => Invitacion::with(['creador', 'usuario', 'clinica'])
                ->where('rol', 'ADMINISTRADOR')->latest()->paginate(15),
            'clinicas' => Clinica::withCount('usuarios')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizar($request);
        $datos = $request->validate([
            'email' => ['nullable', 'email', 'max:150'],
            'plan' => ['required', 'string', 'max:60'],
            'dias' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $codigo = Invitacion::generarCodigo('ADMIN');
        $activacion = Invitacion::create([
            'creada_por' => $request->user()->id,
            'email' => $datos['email'] ?: null,
            'rol' => 'ADMINISTRADOR',
            'codigo_hash' => Invitacion::hashCodigo($codigo),
            'codigo_cifrado' => $codigo,
            'codigo_visible' => '••••-'.substr($codigo, -4),
            'vence_en' => now()->addDays((int) $datos['dias']),
            'datos' => ['plan' => mb_strtoupper($datos['plan']), 'dias_licencia' => (int) $datos['dias']],
        ]);

        if ($activacion->email) {
            Mail::raw('Has recibido una activación de OdontoSuite. Regístrate en '.route('register', ['codigo' => $codigo])."\n\nCódigo: {$codigo}\nVence: ".$activacion->vence_en->format('d/m/Y H:i'),
                fn ($mensaje) => $mensaje->to($activacion->email)->subject('Activación de tu clínica en OdontoSuite'));
        }

        return back()->with('exito', 'Código creado correctamente.')
            ->with('codigo_generado', $codigo);
    }

    public function revocar(Request $request, Invitacion $invitacion): RedirectResponse
    {
        $this->autorizar($request);
        abort_unless($invitacion->rol === 'ADMINISTRADOR', 404);
        $invitacion->update(['activa' => false]);

        return back()->with('exito', 'La activación fue revocada.');
    }

    public function cambiarEstado(Request $request, Clinica $clinica): RedirectResponse
    {
        $this->autorizar($request);
        $clinica->update(['estado' => $clinica->estado === 'ACTIVA' ? 'SUSPENDIDA' : 'ACTIVA']);

        return back()->with('exito', 'Estado de la clínica actualizado.');
    }
}
