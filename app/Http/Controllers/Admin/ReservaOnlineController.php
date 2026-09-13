<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Services\QrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservaOnlineController extends Controller
{
    public function __construct(private readonly QrService $qr) {}

    public function edit(): View
    {
        $ajustes = Ajuste::actual();

        // El enlace público solo existe cuando hay token emitido.
        $url = $ajustes->reservas_token
            ? route('reservas.formulario', $ajustes->reservas_token)
            : null;

        return view('admin.reservas.edit', [
            'ajuste' => $ajustes,
            'url' => $url,
            'qr' => $url ? $this->qr->dataUri($url, 260) : null,
            'reservasRecibidas' => Cita::where('origen', 'ONLINE')->count(),
            'ultimasReservas' => Cita::where('origen', 'ONLINE')
                ->with(['paciente', 'doctor', 'tratamiento'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'reservas_anticipacion_dias' => ['required', 'integer', 'min:1', 'max:180'],
            'reservas_minimo_horas' => ['required', 'integer', 'min:0', 'max:168'],
            'reservas_mensaje' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'reservas_anticipacion_dias' => 'anticipación máxima',
            'reservas_minimo_horas' => 'anticipación mínima',
            'reservas_mensaje' => 'mensaje para el paciente',
        ]);

        $ajustes = Ajuste::actual();
        $activar = $request->boolean('reservas_online');

        // Al activar por primera vez se emite el token que forma la URL pública.
        if ($activar && blank($ajustes->reservas_token)) {
            $datos['reservas_token'] = Str::lower(Str::random(32));
        }

        $ajustes->update($datos + ['reservas_online' => $activar]);

        return back()->with('exito', $activar
            ? 'Las reservas en línea están activas. Comparte el enlace o el QR con tus pacientes.'
            : 'Las reservas en línea fueron desactivadas.');
    }

    /** Invalida el enlace anterior emitiendo un token nuevo. */
    public function regenerar(): RedirectResponse
    {
        Ajuste::actual()->update(['reservas_token' => Str::lower(Str::random(32))]);

        return back()->with('aviso', 'Se generó un enlace nuevo. El anterior y su QR dejaron de funcionar.');
    }

    public function descargarQr(): Response
    {
        $ajustes = Ajuste::actual();

        abort_unless($ajustes->reservas_token, 404, 'Aún no hay un enlace público emitido.');

        $svg = $this->qr->svg(route('reservas.formulario', $ajustes->reservas_token), 600);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-reservas-'.Str::slug($ajustes->nombre).'.svg"',
        ]);
    }
}
