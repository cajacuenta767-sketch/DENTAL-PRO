<?php

namespace App\Services\Mensajeria;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Proveedor de desarrollo y pruebas: escribe el mensaje en el log y responde éxito. */
class LogDriver implements ProveedorMensajeria
{
    public function enviarWhatsapp(string $numero, string $texto): ResultadoEnvio
    {
        return $this->registrar('whatsapp', $numero, $texto);
    }

    public function enviarSms(string $numero, string $texto): ResultadoEnvio
    {
        return $this->registrar('sms', $numero, $texto);
    }

    private function registrar(string $canal, string $numero, string $texto): ResultadoEnvio
    {
        $id = 'log-'.Str::lower(Str::random(12));

        Log::info("Mensajería [{$canal}] (driver log) → {$numero}", ['id' => $id, 'texto' => $texto]);

        return ResultadoEnvio::ok($id);
    }
}
