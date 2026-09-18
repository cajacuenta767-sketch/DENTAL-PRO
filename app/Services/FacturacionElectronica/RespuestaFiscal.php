<?php

namespace App\Services\FacturacionElectronica;

/** Resultado de un intento de transmisión de un documento fiscal. */
final class RespuestaFiscal
{
    public function __construct(
        public readonly bool $aceptado,
        public readonly ?string $sello = null,
        public readonly ?string $codigo = null,
        public readonly ?string $mensaje = null,
        public readonly array $cruda = [],
    ) {}

    public static function aceptada(?string $sello = null, ?string $codigo = null, ?string $mensaje = null, array $cruda = []): self
    {
        return new self(true, $sello, $codigo, $mensaje, $cruda);
    }

    public static function rechazada(?string $mensaje = null, ?string $codigo = null, array $cruda = []): self
    {
        return new self(false, null, $codigo, $mensaje, $cruda);
    }

    public function toArray(): array
    {
        return [
            'aceptado' => $this->aceptado,
            'sello' => $this->sello,
            'codigo' => $this->codigo,
            'mensaje' => $this->mensaje,
            'cruda' => $this->cruda,
        ];
    }
}
