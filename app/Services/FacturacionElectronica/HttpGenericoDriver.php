<?php

namespace App\Services\FacturacionElectronica;

use App\Models\DocumentoFiscal;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Contrato HTTP genérico: envía el documento como JSON con un token Bearer
 * y espera una respuesta JSON con, al menos, {aceptado, sello, codigo, mensaje}.
 * Cualquier pasarela que hable ese contrato funciona sin tocar código.
 */
class HttpGenericoDriver implements ProveedorFiscal
{
    public const TIMEOUT = 15;

    public function __construct(
        private readonly ?string $endpoint,
        private readonly ?string $token,
    ) {}

    public function transmitir(DocumentoFiscal $documento): RespuestaFiscal
    {
        if (! $this->endpoint) {
            return RespuestaFiscal::rechazada('Falta configurar FACTURACION_ENDPOINT.', 'CONFIG');
        }

        try {
            $respuesta = Http::withToken((string) $this->token)
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post($this->endpoint, $this->cargaUtil($documento));
        } catch (Throwable $e) {
            return RespuestaFiscal::rechazada('No se pudo contactar al proveedor: '.$e->getMessage(), 'CONEXION');
        }

        $cuerpo = (array) ($respuesta->json() ?? []);

        if (! $respuesta->successful()) {
            $mensaje = $cuerpo['mensaje'] ?? $cuerpo['message'] ?? $cuerpo['error'] ?? "HTTP {$respuesta->status()}";

            return RespuestaFiscal::rechazada((string) $mensaje, (string) ($cuerpo['codigo'] ?? $respuesta->status()), $cuerpo);
        }

        $aceptado = (bool) ($cuerpo['aceptado'] ?? true);

        return new RespuestaFiscal(
            aceptado: $aceptado,
            sello: $aceptado ? ($cuerpo['sello'] ?? null) : null,
            codigo: isset($cuerpo['codigo']) ? (string) $cuerpo['codigo'] : null,
            mensaje: $cuerpo['mensaje'] ?? $cuerpo['message'] ?? null,
            cruda: $cuerpo,
        );
    }

    /** Representación JSON del documento que se envía al proveedor. */
    private function cargaUtil(DocumentoFiscal $documento): array
    {
        return [
            'tipo' => $documento->tipo,
            'codigo_tipo' => DocumentoFiscal::CODIGOS_TIPO[$documento->tipo] ?? null,
            'numero_control' => $documento->numero_control,
            'codigo_generacion' => $documento->codigo_generacion,
            'serie' => $documento->serie,
            'correlativo' => $documento->correlativo,
            'fecha_emision' => $documento->fecha_emision?->toIso8601String(),
            'emisor' => $documento->contenido['emisor'] ?? [],
            'receptor' => [
                'nombre' => $documento->receptor_nombre,
                'documento' => $documento->receptor_documento,
                'direccion' => $documento->receptor_direccion,
                'email' => $documento->receptor_email,
            ],
            'lineas' => $documento->contenido['lineas'] ?? [],
            'subtotal' => (float) $documento->subtotal,
            'descuento' => (float) $documento->descuento,
            'iva' => (float) $documento->iva,
            'tasa_iva' => (float) $documento->tasa_iva,
            'total' => (float) $documento->total,
            'referencia' => $documento->documentoReferencia?->numero_control,
        ];
    }
}
