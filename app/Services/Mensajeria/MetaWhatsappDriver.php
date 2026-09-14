<?php

namespace App\Services\Mensajeria;

use Illuminate\Support\Facades\Http;

/** Envío por la WhatsApp Cloud API de Meta. No ofrece SMS. */
class MetaWhatsappDriver implements ProveedorMensajeria
{
    public const VERSION_API = 'v20.0';

    public function __construct(
        private readonly ?string $token,
        private readonly ?string $phoneId,
    ) {}

    public function enviarWhatsapp(string $numero, string $texto): ResultadoEnvio
    {
        if (! $this->token || ! $this->phoneId) {
            return ResultadoEnvio::fallo('Faltan las credenciales META_WHATSAPP_TOKEN / META_WHATSAPP_PHONE_ID.');
        }

        $respuesta = Http::withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post('https://graph.facebook.com/'.self::VERSION_API."/{$this->phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => ltrim($numero, '+'),
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $texto],
            ]);

        if ($respuesta->successful()) {
            return ResultadoEnvio::ok($respuesta->json('messages.0.id'));
        }

        $mensaje = $respuesta->json('error.message') ?: "HTTP {$respuesta->status()}";

        return ResultadoEnvio::fallo("Meta: {$mensaje}");
    }

    public function enviarSms(string $numero, string $texto): ResultadoEnvio
    {
        return ResultadoEnvio::fallo('El proveedor Meta (WhatsApp Cloud API) no envía SMS.');
    }
}
