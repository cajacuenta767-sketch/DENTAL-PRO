<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RespaldoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class RespaldoController extends Controller
{
    public function __construct(private readonly RespaldoService $respaldos) {}

    public function index(): View
    {
        $lista = $this->respaldos->listar();

        return view('admin.respaldos.index', [
            'respaldos' => $lista,
            'tamanoTotal' => (int) $lista->sum('tamano'),
            'ultimo' => $lista->first(),
            'conservar' => (int) config('filesystems.disks.respaldos.conservar', 14),
            'herramientasDisponibles' => $this->respaldos->herramientasDisponibles(),
        ]);
    }

    public function crear(): RedirectResponse
    {
        try {
            $nombre = $this->respaldos->crear();
        } catch (Throwable $e) {
            return back()->with('error', 'No se pudo crear el respaldo: '.$e->getMessage());
        }

        return back()->with('exito', "Respaldo {$nombre} creado correctamente.");
    }

    public function descargar(string $archivo): StreamedResponse
    {
        abort_unless($this->respaldos->existe($archivo), 404);

        return $this->respaldos->disco()->download($archivo);
    }

    public function eliminar(string $archivo): RedirectResponse
    {
        if (! $this->respaldos->eliminar($archivo)) {
            return back()->with('error', 'El respaldo indicado no existe.');
        }

        return back()->with('exito', "Respaldo {$archivo} eliminado.");
    }
}
