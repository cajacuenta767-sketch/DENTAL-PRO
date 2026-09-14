<?php

namespace App\Services\Pasarela;

use App\Models\PagoOnline;
use Illuminate\Http\Request;

/** Contrato que cumple cada pasarela de pago (Stripe, simulador local…). */
interface ProveedorPago
{
    /** Nombre con el que se registra el proveedor en el intento y en la ruta del webhook. */
    public function nombre(): string;

    /**
     * Crea la sesión de cobro en la pasarela y devuelve la URL a la que se
     * envía al paciente. Puede fijar `referencia` y `respuesta` en el intento.
     */
    public function crearSesion(PagoOnline $intento, string $urlExito, string $urlCancelacion): string;

    /**
     * Valida la autenticidad de un webhook y lo traduce a un resultado:
     * ['referencia' => ..., 'estado' => 'PAGADO|FALLIDO|CANCELADO', 'cruda' => []].
     * Devuelve null cuando el evento no interesa. Lanza WebhookInvalido si la
     * firma no es válida.
     */
    public function verificarWebhook(Request $request): ?array;
}
