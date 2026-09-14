<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DocumentoClinico extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'documentos_clinicos';

    protected $fillable = [
        'folio', 'paciente_id', 'doctor_id', 'cita_id', 'usuario_id',
        'tipo', 'titulo', 'contenido', 'indicaciones',
        'vigencia_dias', 'fecha_emision', 'estado', 'firma_archivo', 'firmado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'vigencia_dias' => 'integer',
            'firmado_en' => 'datetime',
        ];
    }

    public const TIPOS = [
        'RECETA' => 'Receta médica',
        'CERTIFICADO' => 'Certificado odontológico',
        'ORDEN_LABORATORIO' => 'Orden de laboratorio',
        'CONSENTIMIENTO' => 'Consentimiento informado',
        'REFERENCIA' => 'Hoja de referencia',
        'CONSTANCIA' => 'Constancia de atención',
    ];

    /** Prefijo del folio por tipo de documento. */
    public const PREFIJOS = [
        'RECETA' => 'REC',
        'CERTIFICADO' => 'CER',
        'ORDEN_LABORATORIO' => 'ORD',
        'CONSENTIMIENTO' => 'CON',
        'REFERENCIA' => 'REF',
        'CONSTANCIA' => 'CST',
    ];

    public const ICONOS = [
        'RECETA' => 'ti ti-prescription',
        'CERTIFICADO' => 'ti ti-certificate',
        'ORDEN_LABORATORIO' => 'ti ti-flask',
        'CONSENTIMIENTO' => 'ti ti-writing-sign',
        'REFERENCIA' => 'ti ti-arrow-right-circle',
        'CONSTANCIA' => 'ti ti-file-check',
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

    /** Correlativo por tipo: REC-2026-00001. */
    public static function siguienteFolio(string $tipo): string
    {
        $prefijo = self::PREFIJOS[$tipo] ?? 'DOC';
        $anio = now()->year;

        $correlativo = Secuencia::siguiente("documento-{$prefijo}-{$anio}", function () use ($prefijo, $anio) {
            $ultimo = static::withTrashed()
                ->where('folio', 'like', "{$prefijo}-{$anio}-%")
                ->orderByDesc('folio')
                ->value('folio');

            return $ultimo ? (int) substr($ultimo, -5) : 0;
        });

        return sprintf('%s-%d-%05d', $prefijo, $anio, $correlativo);
    }

    /** Disco privado donde se guardan las firmas. */
    public const DISCO_FIRMAS = 'local';

    public function getEstaFirmadoAttribute(): bool
    {
        return filled($this->firma_archivo) && $this->firmado_en !== null;
    }

    /** Firma como data URI para incrustarla en el PDF. */
    public function getFirmaDataUriAttribute(): ?string
    {
        if (! $this->esta_firmado || ! Storage::disk(self::DISCO_FIRMAS)->exists($this->firma_archivo)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::disk(self::DISCO_FIRMAS)->get($this->firma_archivo));
    }

    public function getTipoLegibleAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getIconoAttribute(): string
    {
        return self::ICONOS[$this->tipo] ?? 'ti ti-file-text';
    }

    public function getVenceElAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->vigencia_dias ? $this->fecha_emision->copy()->addDays($this->vigencia_dias) : null;
    }

    public function getEstaVigenteAttribute(): bool
    {
        return $this->estado === 'EMITIDO'
            && ($this->vence_el === null || $this->vence_el->isFuture());
    }
}
