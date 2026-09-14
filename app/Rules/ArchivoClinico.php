<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Archivo adjunto de un estudio: imagen, PDF, documento de oficina, texto o DICOM.
 *
 * Comprueba la extensión declarada y, además, que el contenido real del archivo
 * coincida con un tipo aceptable para esa extensión. Así un ejecutable renombrado
 * como `.jpg` o `.docx` se rechaza aunque el navegador diga otra cosa.
 */
class ArchivoClinico implements ValidationRule
{
    /** Extensión permitida => tipos MIME detectados que se aceptan para ella. */
    public const PERMITIDOS = [
        // Fotografías y radiografías
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'tif' => ['image/tiff'],
        'tiff' => ['image/tiff'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        // Informes y resultados
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'rtf' => ['text/rtf', 'application/rtf', 'text/plain'],
        'txt' => ['text/plain'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        // Imagen médica
        'dcm' => ['application/dicom', 'application/octet-stream'],
        'dicom' => ['application/dicom', 'application/octet-stream'],
    ];

    public static function extensiones(): array
    {
        return array_keys(self::PERMITIDOS);
    }

    /** Lista para el atributo `accept` del campo de archivo. */
    public static function accept(): string
    {
        return implode(',', array_map(fn ($e) => '.'.$e, self::extensiones()));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('El archivo no se pudo cargar.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! array_key_exists($extension, self::PERMITIDOS)) {
            $fail('El archivo debe ser una imagen (JPG, PNG, WEBP, TIFF…), un PDF, un documento (DOC, DOCX, ODT, RTF, TXT, XLS, XLSX) o un DICOM.');

            return;
        }

        $mime = strtolower((string) $value->getMimeType());

        if (! in_array($mime, self::PERMITIDOS[$extension], true)) {
            $fail("El contenido del archivo no corresponde a un .$extension válido.");
        }
    }
}
