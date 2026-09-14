<?php

namespace App\Http\Controllers;

use App\Services\Control\ControlLicencia;
use App\Services\Control\Licencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Pantalla estándar "Licencia" de CONTROL: estado, reactivar, código de
 * emergencia (72 h) e ingreso o cambio de la clave. Ver docs/licencia.md.
 */
class LicenciaController extends Controller
{
    public function __construct(private Licencia $licencia) {}

    public function mostrar(Request $request): View
    {
        $resumen = $this->licencia->resumen();

        return view('licencia', [
            'resumen' => $resumen,
            'motivo' => $request->session()->get('licencia_motivo') ?: $resumen['motivo'],
            'puedeGestionar' => $this->puedeGestionar($request),
            'sinClave' => ! $this->licencia->tieneClave(),
        ]);
    }

    /** Ingresa o cambia la clave de licencia y activa de inmediato. */
    public function clave(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        $request->merge(['clave' => ControlLicencia::normalizar($request->input('clave'))]);

        $datos = $request->validate([
            'clave' => ['required', 'string', 'max:30', 'regex:'.ControlLicencia::FORMATO_CLAVE],
        ], [
            'clave.regex' => 'La clave debe tener el formato CTL-XXXX-XXXX-XXXX-XXXX.',
        ]);

        try {
            $this->licencia->guardarClave($datos['clave']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['clave' => $e->getMessage()]);
        }

        $estado = $this->licencia->verificarAhora();

        return $estado['valido']
            ? redirect()->route('licencia.mostrar')->with('exito', 'Clave registrada y licencia activada en este equipo.')
            : redirect()->route('licencia.mostrar')->with('aviso', 'Clave registrada, pero no se pudo activar: '.$estado['motivo']);
    }

    public function reactivar(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        if (! $this->licencia->tieneClave()) {
            return back()->with('error', 'Primero registra la clave de licencia.');
        }

        $estado = $this->licencia->verificarAhora();

        return $estado['valido']
            ? back()->with('exito', 'Licencia verificada con CONTROL.')
            : back()->with('error', 'No se pudo verificar la licencia: '.$estado['motivo']);
    }

    public function emergencia(Request $request): RedirectResponse
    {
        $this->autorizar($request);

        $datos = $request->validate(['codigo' => ['required', 'string', 'max:4000']]);

        $resultado = $this->licencia->aplicarCodigoEmergencia($datos['codigo']);

        return $resultado['ok']
            ? back()->with('exito', 'Código de emergencia aplicado. El sistema funciona hasta el '.date('d/m/Y H:i', strtotime($resultado['expira_en'])).'.')
            : back()->with('error', $resultado['motivo']);
    }

    /**
     * Gestiona la licencia quien tiene `ajustes.editar` o es SUPER ADMINISTRADOR.
     * Si todavía no hay clave (primer arranque), cualquier usuario autenticado
     * puede registrarla.
     */
    private function puedeGestionar(Request $request): bool
    {
        $usuario = $request->user();

        if (! $usuario) {
            return false;
        }

        return ! $this->licencia->tieneClave()
            || $usuario->hasRole('SUPER ADMINISTRADOR')
            || $usuario->can('ajustes.editar');
    }

    private function autorizar(Request $request): void
    {
        abort_unless($this->puedeGestionar($request), 403, 'No tienes permiso para gestionar la licencia.');
    }
}
