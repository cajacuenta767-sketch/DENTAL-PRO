<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EstudioImagen extends Model
{
    use Auditable, Concerns\BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'estudios_imagen';

    protected $fillable = [
        'paciente_id', 'doctor_id', 'cita_id', 'usuario_id', 'tipo', 'titulo',
        'archivo', 'nombre_original', 'mime', 'tamano',
        'piezas_referidas', 'fecha_estudio', 'hallazgos', 'observaciones', 'anotaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_estudio' => 'date',
            'tamano' => 'integer',
            'anotaciones' => 'array',
        ];
    }

    public const TIPOS = [
        'PANORAMICA' => 'Panorámica',
        'PERIAPICAL' => 'Periapical',
        'BITEWING' => 'Bitewing (aleta de mordida)',
        'OCLUSAL' => 'Oclusal',
        'CBCT' => 'Tomografía CBCT',
        'CEFALOMETRICA' => 'Cefalométrica',
        'FOTO_INTRAORAL' => 'Fotografía intraoral',
        'FOTO_EXTRAORAL' => 'Fotografía extraoral',
        'INFORME' => 'Informe / resultado de laboratorio',
        'DOCUMENTO' => 'Documento externo',
        'OTRO' => 'Otro estudio',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function getTipoLegibleAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /** Disco privado: los estudios solo se sirven a través de rutas autenticadas. */
    public const DISCO = 'local';

    /** URL autenticada del archivo, para el visor y las miniaturas. */
    public function getUrlAttribute(): string
    {
        return route('admin.estudios.ver', $this);
    }

    public function archivoExiste(): bool
    {
        return filled($this->archivo) && Storage::disk(self::DISCO)->exists($this->archivo);
    }

    /** ¿El navegador puede mostrarlo en el visor (con zoom y anotaciones) o solo se descarga? */
    public function getEsVisualizableAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/')
            && ! in_array($this->extension, ['tif', 'tiff', 'heic'], true);
    }

    public function getEsPdfAttribute(): bool
    {
        return $this->mime === 'application/pdf' || $this->extension === 'pdf';
    }

    /** Extensión del archivo original en minúsculas (sin punto). */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo((string) ($this->nombre_original ?: $this->archivo), PATHINFO_EXTENSION));
    }

    /** Familia del archivo para iconos y filtros: imagen, pdf, documento, hoja, dicom u otro. */
    public function getFamiliaAttribute(): string
    {
        return match (true) {
            str_starts_with((string) $this->mime, 'image/') => 'imagen',
            $this->es_pdf => 'pdf',
            in_array($this->extension, ['doc', 'docx', 'odt', 'rtf', 'txt'], true) => 'documento',
            in_array($this->extension, ['xls', 'xlsx'], true) => 'hoja',
            in_array($this->extension, ['dcm', 'dicom'], true) => 'dicom',
            default => 'otro',
        };
    }

    /** Icono Tabler según la familia del archivo. */
    public function getIconoAttribute(): string
    {
        return match ($this->familia) {
            'imagen' => 'ti-photo',
            'pdf' => 'ti-file-type-pdf',
            'documento' => 'ti-file-text',
            'hoja' => 'ti-file-spreadsheet',
            'dicom' => 'ti-radioactive',
            default => 'ti-file',
        };
    }

    public function getTamanoLegibleAttribute(): string
    {
        $bytes = (int) $this->tamano;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
