<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Genera códigos QR en SVG. Se eligió SVG porque no depende de extensiones
 * de imagen y escala sin perder nitidez al imprimirlo en recepción.
 */
class QrService
{
    public function svg(string $contenido, int $tamano = 300): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($tamano, 1),
            new SvgImageBackEnd
        ));

        return $writer->writeString($contenido);
    }

    /** SVG listo para incrustar en un atributo src. */
    public function dataUri(string $contenido, int $tamano = 300): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($contenido, $tamano));
    }
}
