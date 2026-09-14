<?php

namespace App\Services\Pasarela;

use App\Models\PagoOnline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout: crea una sesión de pago alojada por Stripe y confirma el
 * cobro por webhook (checkout.session.completed). El retorno del navegador
 * solo muestra el estado; nunca marca nada como pagado.
 */
class StripeDriver implements ProveedorPago
{
    public const API = 'https://api.stripe.com/v1';

    /** Tolerancia de la marca de tiempo del webhook, como recomienda Stripe. */
    public const TOLERANCIA_SEGUNDOS = 300;

    /** Monedas sin decimales en Stripe (se envían sin multiplicar por 100). */
    private const SIN_DECIMALES = ['jpy', 'krw', 'clp', 'vnd', 'pyg', 'xaf', 'xof', 'ugx', 'rwf', 'gnf', 'bif', 'djf', 'kmf', 'mga', 'vuv', 'xpf'];

    public function __construct(
        private readonly ?string $secreto,
        private readonly ?string $secretoWebhook,
    ) {}

    public function nombre(): string
    {
        return 'stripe';
    }

    public function crearSesion(PagoOnline $intento, string $urlExito, string $urlCancelacion): string
    {
        if (blank($this->secreto)) {
            throw new \RuntimeException('Falta configurar STRIPE_SECRET para cobrar con Stripe.');
        }

        $moneda = strtolower((string) $intento->moneda);
        $monto = in_array($moneda, self::SIN_DECIMALES, true)
            ? (int) round((float) $intento->monto)
            : (int) round((float) $intento->monto * 100);

        $pago = $intento->pago;
        $descripcion = 'Pago del recibo '.($pago?->codigo_recibo ?? "#{$intento->pago_id}");

        $respuesta = Http::withToken($this->secreto)
            ->asForm()
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $urlExito,
                'cancel_url' => $urlCancelacion,
                'client_reference_id' => (string) $intento->id,
                'customer_email' => $intento->paciente?->email,
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => $moneda,
                        'unit_amount' => $monto,
                        'product_data' => ['name' => $descripcion],
                    ],
                ]],
                'metadata' => ['pago_online_id' => (string) $intento->id],
                'payment_intent_data' => ['metadata' => ['pago_online_id' => (string) $intento->id]],
            ]);

        if ($respuesta->failed() || blank($respuesta->json('url'))) {
            throw new \RuntimeException('Stripe no aceptó la sesión de pago: '.($respuesta->json('error.message') ?? $respuesta->status()));
        }

        $intento->referencia = (string) $respuesta->json('id');
        $intento->respuesta = ['sesion' => $respuesta->json()];

        return (string) $respuesta->json('url');
    }

    public function verificarWebhook(Request $request): ?array
    {
        $cuerpo = $request->getContent();

        if (! $this->firmaValida((string) $request->header('Stripe-Signature'), $cuerpo)) {
            throw new WebhookInvalido('La firma del webhook de Stripe no es válida.');
        }

        $evento = json_decode($cuerpo, true);

        if (! is_array($evento) || blank($evento['type'] ?? null)) {
            throw new WebhookInvalido('El cuerpo del webhook de Stripe no es un evento.');
        }

        $estado = match ($evento['type']) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => 'PAGADO',
            'checkout.session.async_payment_failed' => 'FALLIDO',
            'checkout.session.expired' => 'CANCELADO',
            default => null,
        };

        if ($estado === null) {
            return null;
        }

        $objeto = $evento['data']['object'] ?? [];

        // Un checkout completado con pago diferido todavía no está cobrado.
        if ($evento['type'] === 'checkout.session.completed' && ($objeto['payment_status'] ?? 'paid') === 'unpaid') {
            return null;
        }

        return [
            'referencia' => (string) ($objeto['id'] ?? ''),
            'pago_online_id' => $objeto['metadata']['pago_online_id'] ?? $objeto['client_reference_id'] ?? null,
            'estado' => $estado,
            'cruda' => $evento,
        ];
    }

    /** Firma "t=…,v1=…": HMAC SHA-256 del "t.payload" con el secreto del endpoint, con tolerancia de tiempo. */
    public function firmaValida(string $cabecera, string $cuerpo, ?int $ahora = null): bool
    {
        if (blank($this->secretoWebhook) || blank($cabecera)) {
            return false;
        }

        $marca = null;
        $firmas = [];

        foreach (explode(',', $cabecera) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, null);

            if ($clave === 't') {
                $marca = (int) $valor;
            } elseif ($clave === 'v1' && $valor !== null) {
                $firmas[] = $valor;
            }
        }

        if (! $marca || ! $firmas) {
            return false;
        }

        if (abs(($ahora ?? time()) - $marca) > self::TOLERANCIA_SEGUNDOS) {
            return false;
        }

        $esperada = hash_hmac('sha256', "{$marca}.{$cuerpo}", $this->secretoWebhook);

        foreach ($firmas as $firma) {
            if (hash_equals($esperada, $firma)) {
                return true;
            }
        }

        return false;
    }
}
