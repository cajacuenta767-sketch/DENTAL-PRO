<?php

namespace App\Services\Mensajeria;

use Illuminate\Support\Facades\Http;

/** Envío por la API REST de Twilio (SMS y WhatsApp). */
class TwilioDriver implements ProveedorMensajeria
{
    public function __construct(
        private readonly ?string $sid,
        private readonly ?string $token,
        private readonly ?string $desdeSms,
        private readonly ?string $desdeWhatsapp,
    ) {}

    public function enviarWhatsapp(string $numero, string $texto): ResultadoEnvio
    {
        if (! $this->desdeWhatsapp) {
            return ResultadoEnvio::fallo('Falta configurar TWILIO_DESDE_WHATSAPP.');
        }

        return $this->enviar(
            from: $this->conPrefijoWhatsapp($this->desdeWhatsapp),
            to: $this->conPrefijoWhatsapp($numero),
            texto: $texto,
        );
    }

    public function enviarSms(string $numero, string $texto): ResultadoEnvio
    {
        if (! $this->desdeSms) {
            return ResultadoEnvio::fallo('Falta configurar TWILIO_DESDE_SMS.');
        }

        return $this->enviar(from: $this->desdeSms, to: $numero, texto: $texto);
    }

    private function enviar(string $from, string $to, string $texto): ResultadoEnvio
    {
        if (! $this->sid || ! $this->token) {
            return ResultadoEnvio::fallo('Faltan las credenciales TWILIO_SID / TWILIO_TOKEN.');
        }

        $respuesta = Http::withBasicAuth($this->sid, $this->token)
            ->asForm()
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $texto,
            ]);

        if ($respuesta->successful()) {
            return ResultadoEnvio::ok($respuesta->json('sid'));
        }

        $mensaje = $respuesta->json('message') ?: "HTTP {$respuesta->status()}";

        return ResultadoEnvio::fallo("Twilio: {$mensaje}");
    }

    private function conPrefijoWhatsapp(string $numero): string
    {
        return str_starts_with($numero, 'whatsapp:') ? $numero : 'whatsapp:'.$numero;
    }
}
