<?php

namespace App\Services\Pasarela;

use App\Models\PagoOnline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pasarela de desarrollo y pruebas: no cobra nada. Envía al paciente a una
 * página local con los botones "Pagar" y "Cancelar", que llevan a las rutas
 * de retorno firmadas; el retorno con éxito marca el intento como pagado.
 */
class SimuladoDriver implements ProveedorPago
{
    public function nombre(): string
    {
        return 'simulado';
    }

    public function crearSesion(PagoOnline $intento, string $urlExito, string $urlCancelacion): string
    {
        $intento->referencia = 'SIM-'.Str::upper(Str::random(12));
        $intento->respuesta = [
            'simulado' => true,
            'url_exito' => $urlExito,
            'url_cancelacion' => $urlCancelacion,
        ];

        return route('portal.pagos.online', $intento);
    }

    /** El simulador no recibe webhooks: todo se resuelve en el retorno firmado. */
    public function verificarWebhook(Request $request): ?array
    {
        throw new WebhookInvalido('El proveedor simulado no recibe webhooks.');
    }
}
