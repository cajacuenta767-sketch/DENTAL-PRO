<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenciaEmitida;
use App\Services\EmisorLicencias;
use App\Services\LicenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Panel del proveedor para emitir códigos de activación y PINes de
 * renovación. Solo existe en la instalación que tiene la clave privada.
 */
class LicenciaEmitidaController extends Controller
{
    public function __construct(private readonly EmisorLicencias $emisor)
    {
        abort_unless($this->emisor->disponible(), 404);
    }

    public function index(): View
    {
        return view('admin.licencias.index', [
            'emitidas' => LicenciaEmitida::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cliente' => ['required', 'string', 'max:120'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'tipo' => ['required', 'in:PRUEBA,COMPLETA'],
            'duracion' => ['required', 'in:dias,fecha,vitalicia'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'hasta' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [], ['cliente' => 'nombre del cliente', 'hasta' => 'fecha límite']);

        try {
            $diaFin = $this->diaFin($datos);
            $emitida = $this->emisor->emitir(
                $datos['cliente'],
                $datos['tipo'] === 'PRUEBA' ? LicenciaService::TIPO_PRUEBA : LicenciaService::TIPO_COMPLETA,
                $diaFin,
                $datos['contacto'] ?? null,
                $datos['notas'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.licencias.index')
            ->with('exito', "Código de activación emitido para {$emitida->cliente}.")
            ->with('codigo_emitido', ['cliente' => $emitida->cliente, 'codigo' => $emitida->ultimo_codigo, 'tipo' => 'activación']);
    }

    public function renovar(Request $request, LicenciaEmitida $emitida): RedirectResponse
    {
        $datos = $request->validate([
            'codigo_instalacion' => ['required', 'string', 'max:12'],
            'duracion' => ['required', 'in:dias,fecha,vitalicia'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'hasta' => ['nullable', 'date'],
        ], [], ['codigo_instalacion' => 'código de instalación', 'hasta' => 'fecha límite']);

        try {
            $pin = $this->emisor->renovar($emitida, $datos['codigo_instalacion'], $this->diaFin($datos));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.licencias.index')
            ->with('exito', "PIN de renovación generado para {$emitida->cliente}.")
            ->with('codigo_emitido', ['cliente' => $emitida->cliente, 'codigo' => $pin, 'tipo' => 'renovación']);
    }

    /** Nuevo código de activación (por ejemplo, si el cliente reinstaló). */
    public function reactivar(Request $request, LicenciaEmitida $emitida): RedirectResponse
    {
        $datos = $request->validate([
            'duracion' => ['required', 'in:dias,fecha,vitalicia'],
            'dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'hasta' => ['nullable', 'date'],
        ]);

        try {
            $codigo = $this->emisor->reactivar($emitida, LicenciaService::TIPO_COMPLETA, $this->diaFin($datos));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.licencias.index')
            ->with('exito', "Nuevo código de activación para {$emitida->cliente}.")
            ->with('codigo_emitido', ['cliente' => $emitida->cliente, 'codigo' => $codigo, 'tipo' => 'activación']);
    }

    public function destroy(LicenciaEmitida $emitida): RedirectResponse
    {
        $emitida->delete();

        return back()->with('aviso', "Se eliminó el registro de {$emitida->cliente}. Ya no podrás emitirle PINes de renovación.");
    }

    private function diaFin(array $datos): int
    {
        return $this->emisor->diaFinDesde(
            dias: $datos['duracion'] === 'dias' ? (int) ($datos['dias'] ?? 0) : null,
            hasta: $datos['duracion'] === 'fecha' ? ($datos['hasta'] ?? null) : null,
            vitalicia: $datos['duracion'] === 'vitalicia',
        );
    }
}
