<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToClinica;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrdenLaboratorio extends Model
{
    use Auditable, BelongsToClinica, HasFactory, SoftDeletes;

    protected $table = 'ordenes_laboratorio';

    protected $fillable = [
        'clinica_id',
        'folio',
        'paciente_id',
        'doctor_id',
        'laboratorio_id',
        'cita_id',
        'tratamiento_id',
        'tipo_trabajo',
        'color_vita',
        'piezas_dentales',
        'fecha_envio',
        'fecha_prometida',
        'fecha_entrega',
        'costo_laboratorio',
        'precio_paciente',
        'estado',
        'notas_tecnicas',
        'adjunto_archivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'date',
            'fecha_prometida' => 'date',
            'fecha_entrega' => 'date',
            'costo_laboratorio' => 'float',
            'precio_paciente' => 'float',
        ];
    }

    public const ESTADOS = [
        'ENVIADO' => 'Enviado al laboratorio',
        'EN_PROCESO' => 'En elaboración',
        'PRUEBA' => 'Prueba en clínica',
        'TERMINADO' => 'Terminado por el protésico',
        'ENTREGADO' => 'Instalado al paciente',
        'AJUSTE' => 'En ajuste / repetición',
    ];

    public const COLORES_ESTADO = [
        'ENVIADO' => 'info',
        'EN_PROCESO' => 'warning',
        'PRUEBA' => 'primary',
        'TERMINADO' => 'teal',
        'ENTREGADO' => 'success',
        'AJUSTE' => 'danger',
    ];

    public const COLORES_VITA = [
        'A1', 'A2', 'A3', 'A3.5', 'A4',
        'B1', 'B2', 'B3', 'B4',
        'C1', 'C2', 'C3', 'C4',
        'D2', 'D3', 'D4',
        'BL1', 'BL2', 'BL3', 'BL4',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $orden) {
            if (empty($orden->folio)) {
                $prefijo = 'LAB-' . date('Ym') . '-';
                $ultimo = static::withTrashed()->where('folio', 'like', $prefijo . '%')->count() + 1;
                $orden->folio = $prefijo . str_pad((string) $ultimo, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function laboratorio(): BelongsTo
    {
        return $this->belongsTo(LaboratorioDental::class, 'laboratorio_id');
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function tratamiento(): BelongsTo
    {
        return $this->belongsTo(Tratamiento::class, 'tratamiento_id');
    }

    public function getColorBadgeAttribute(): string
    {
        return self::COLORES_ESTADO[$this->estado] ?? 'secondary';
    }

    public function getEstadoLegibleAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getEstaVencidaAttribute(): bool
    {
        return !in_array($this->estado, ['TERMINADO', 'ENTREGADO'], true) && $this->fecha_prometida->isPast();
    }

    public function getColorGuiaAttribute(): ?string
    {
        return $this->color_vita;
    }

    public function getDientesArrayAttribute(): array
    {
        if (empty($this->piezas_dentales)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $this->piezas_dentales))));
    }

    public function getDiasRestantesAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->fecha_prometida->startOfDay(), false);
    }
}
