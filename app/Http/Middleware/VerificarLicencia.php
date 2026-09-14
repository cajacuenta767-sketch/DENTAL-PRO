<?php

namespace App\Http\Middleware;

use App\Services\Control\Licencia;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige una licencia CONTROL válida en el panel, el portal, la reserva
 * pública y la API. Con `control.activo` en false (desarrollo y pruebas)
 * no hace nada. Estado `mora` (vencida, en gracia) deja pasar con un aviso;
 * `suspendida`, `vencida` y `revocada` bloquean.
 */
class VerificarLicencia
{
    public function __construct(private Licencia $licencia) {}

    public function handle(Request $request, Closure $next): Response
    {
        // El estado se calcula una vez por petición.
        $this->licencia->olvidar();

        if (! $this->licencia->activo() || $this->esLibre($request) || ! $this->esProtegida($request)) {
            return $next($request);
        }

        if ($this->licencia->valida()) {
            if ($this->licencia->enMora()) {
                $this->avisarMora($request);
            }

            return $next($request);
        }

        $motivo = $this->licencia->motivo() ?: 'Licencia no válida';
        $codigo = $this->licencia->codigo() ?: 'licencia_invalida';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['ok' => false, 'error' => $motivo, 'codigo' => $codigo], 402);
        }

        return redirect()->route('licencia.mostrar')->with('licencia_motivo', $motivo);
    }

    private function esLibre(Request $request): bool
    {
        return $this->coincide($request, (array) config('control.rutas_libres', []));
    }

    private function esProtegida(Request $request): bool
    {
        return $this->coincide($request, (array) config('control.rutas_protegidas', []));
    }

    /** Compara con el nombre de la ruta (patrón fnmatch) y con la URL (Request::is). */
    private function coincide(Request $request, array $patrones): bool
    {
        $nombre = $request->route()?->getName();

        foreach ($patrones as $patron) {
            if ($nombre !== null && fnmatch($patron, $nombre)) {
                return true;
            }

            if ($request->is(ltrim($patron, '/') ?: '/')) {
                return true;
            }
        }

        return false;
    }

    private function avisarMora(Request $request): void
    {
        $vence = $this->licencia->estado()['payload']['vence_en'] ?? null;
        $aviso = 'La licencia del sistema está vencida y en periodo de gracia'
            .($vence ? ' desde el '.date('d/m/Y', strtotime($vence)) : '')
            .'. Renueva pronto para no perder el acceso.';

        View::share('licenciaAviso', $aviso);

        // Solo para la petición actual; las vistas lo muestran con componentes.alertas.
        if ($request->hasSession() && ! $request->session()->has('aviso')) {
            $request->session()->now('aviso', $aviso);
        }
    }
}
