<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EstudioImagen extends Model
{
    use HasFactory;

    protected $table = 'estudios_imagen';

    protected $fillable = [
        'paciente_id', 'doctor_id', 'cita_id', 'usuario_id', 'tipo', 'titulo',
        'archivo', 'nombre_original', 'mime', 'tamano',
        'piezas_referidas', 'fecha_estudio', 'hallazgos', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_estudio' => 'date',
            'tamano' => 'integer',
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

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->archivo);
    }

    /** ¿El navegador puede mostrarlo en el visor o solo se descarga? */
    public function getEsVisualizableAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
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
