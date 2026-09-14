<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\DocumentoFiscal;
use App\Services\FacturacionElectronica\HttpGenericoDriver;
use App\Services\FacturacionElectronica\ProveedorFiscal;
use App\Services\FacturacionElectronica\RespuestaFiscal;
use App\Services\FacturacionElectronica\SimuladoDriver;
use Throwable;

/**
 * Orquesta la transmisión de documentos fiscales al proveedor configurado en
 * services.facturacion_electronica.proveedor (simulado | http | ninguno) y
 * deja el resultado en el propio documento.
 */
class FacturacionElectronicaService
{
    public const PROVEEDORES = ['simulado', 'http', 'ninguno'];

    public function proveedor(): string
    {
        $proveedor = (string) config('services.facturacion_electronica.proveedor', 'simulado');

        return in_array($proveedor, self::PROVEEDORES, true) ? $proveedor : 'simulado';
    }

    /** ¿Hay un proveedor al que transmitir? */
    public function activa(): bool
    {
        return $this->proveedor() !== 'ninguno';
    }

    public function driver(): ?ProveedorFiscal
    {
        return match ($this->proveedor()) {
            'simulado' => new SimuladoDriver,
            'http' => new HttpGenericoDriver(
                config('services.facturacion_electronica.endpoint'),
                config('services.facturacion_electronica.token'),
            ),
            default => null,
        };
    }

    /**
     * Transmite el documento y persiste el resultado. Devuelve la respuesta
     * del proveedor (o null cuando no aplica transmitir).
     */
    public function transmitir(DocumentoFiscal $documento): ?RespuestaFiscal
    {
        $proveedor = $this->proveedor();
        $driver = $this->driver();

        if ($driver === null) {
            $documento->forceFill([
                'proveedor' => $proveedor,
                'estado_transmision' => 'NO_APLICA',
            ])->save();

            return null;
        }

        $documento->forceFill(['proveedor' => $proveedor, 'estado_transmision' => 'PENDIENTE'])->save();

        try {
            $respuesta = $driver->transmitir($documento);
        } catch (Throwable $e) {
            report($e);
            $respuesta = RespuestaFiscal::rechazada('Error interno al transmitir: '.$e->getMessage(), 'EXCEPCION');
        }

        $datos = [
            'proveedor' => $proveedor,
            'estado_transmision' => $respuesta->aceptado ? 'ACEPTADO' : 'RECHAZADO',
            'respuesta_proveedor' => $respuesta->toArray(),
            'transmitido_en' => now(),
        ];

        if ($respuesta->aceptado && $respuesta->sello) {
            $datos['sello_recepcion'] = $respuesta->sello;
        }

        $documento->forceFill($datos)->save();

        Auditoria::registrar(
            'TRANSMITIR',
            $documento,
            sprintf('Transmisión de %s vía %s: %s%s', $documento->numero_control, $proveedor,
                $datos['estado_transmision'], $respuesta->mensaje ? " · {$respuesta->mensaje}" : '')
        );

        return $respuesta;
    }
}
