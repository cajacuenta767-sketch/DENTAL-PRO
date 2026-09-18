<?php

namespace App\Services\FacturacionElectronica;

use App\Models\DocumentoFiscal;

/**
 * Proveedor de pruebas: acepta todo documento y devuelve un sello aleatorio.
 * Sirve para desarrollo y para clínicas sin obligación de transmitir.
 */
class SimuladoDriver implements ProveedorFiscal
{
    public function transmitir(DocumentoFiscal $documento): RespuestaFiscal
    {
        $sello = mb_strtoupper(bin2hex(random_bytes(20)));

        return RespuestaFiscal::aceptada(
            sello: $sello,
            codigo: '000',
            mensaje: 'Documento recibido por el simulador.',
            cruda: [
                'simulado' => true,
                'numero_control' => $documento->numero_control,
                'codigo_generacion' => $documento->codigo_generacion,
                'sello' => $sello,
                'recibido_en' => now()->toIso8601String(),
            ],
        );
    }
}
