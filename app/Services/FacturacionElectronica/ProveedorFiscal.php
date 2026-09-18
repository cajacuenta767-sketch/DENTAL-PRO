<?php

namespace App\Services\FacturacionElectronica;

use App\Models\DocumentoFiscal;

/**
 * Contrato de los proveedores de transmisión fiscal. Cada administración
 * tributaria (o pasarela intermedia) se implementa como un driver más.
 */
interface ProveedorFiscal
{
    public function transmitir(DocumentoFiscal $documento): RespuestaFiscal;
}
