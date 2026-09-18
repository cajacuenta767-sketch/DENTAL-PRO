<?php

namespace App\Services\Mensajeria;

/** Resultado de un intento de envío por WhatsApp o SMS. */
final class ResultadoEnvio
{
    public function __construct(
        public readonly bool $exito,
        public readonly ?string $idExterno = null,
        public readonly ?string $error = null,
    ) {}

    public static function ok(?string $idExterno = null): self
    {
        return new self(true, $idExterno);
    }

    public static function fallo(string $error): self
    {
        return new self(false, null, $error);
    }

    public function toArray(): array
    {
        return ['exito' => $this->exito, 'id_externo' => $this->idExterno, 'error' => $this->error];
    }
}
