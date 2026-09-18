<?php

namespace App\Services\Mensajeria;

/**
 * Contrato de los proveedores de mensajería. Los números llegan ya
 * normalizados en formato E.164 (+591...).
 */
interface ProveedorMensajeria
{
    public function enviarWhatsapp(string $numero, string $texto): ResultadoEnvio;

    public function enviarSms(string $numero, string $texto): ResultadoEnvio;
}
