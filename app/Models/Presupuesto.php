<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Presupuesto extends Model
{
    use HasFactory;

    protected $table = 'presupuestos';

    protected $fillable = [
        'codigo', 'paciente_id', 'doctor_id', 'usuario_id', 'odontograma_id',
        'estado', 'subtotal', 'descuento', 'cobertura_seguro', 'total',
        'validez_dias', 'fecha', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'cobertura_seguro' => 'decimal:2',
            'total' => 'decimal:2',
            'validez_dias' => 'integer',
            'fecha' => 'date',
        ];
    }

    public const ESTADOS = [
        'BORRADOR' => 'Borrador',
        'PRESENTADO' => 'Presentado al paciente',
        'APROBADO' => 'Aprobado',
        'EN_EJECUCION' => 'En ejecución',
        'COMPLETADO' => 'Completado',
        'RECHAZADO' => 'Rechazado',
    ];

    public const COLORES = [
        'BORRADOR' => 'secondary',
        'PRESENTADO' => 'azure',
        'APROBADO' => 'primary',
        'EN_EJECUCION' => 'warning',
        'COMPLETADO' => 'success',
        'RECHAZADO' => 'danger',
    ];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function odontograma(): BelongsTo
    {
        return $this->belongsTo(Odontograma::class, 'odontograma_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PresupuestoDetalle::class, 'presupuesto_id')->orderBy('orden')->orderBy('id');
    }

    public static function siguienteCodigo(): string
    {
        $anio = now()->year;

        $ultimo = static::query()
            ->where('codigo', 'like', "PRE-{$anio}-%")
            ->orderByDesc('id')
            ->value('codigo');

        $correlativo = $ultimo ? ((int) substr($ultimo, -5)) + 1 : 1;

        return sprintf('PRE-%d-%05d', $anio, $correlativo);
    }

    /**
     * Recalcula importes desde las líneas y aplica la cobertura de la
     * aseguradora del paciente sobre el neto ya descontado.
     */
    public function recalcular(): void
    {
        $this->subtotal = (float) $this->detalles()->where('estado', '!=', 'ANULADO')->sum('subtotal');

        $neto = max(0, round((float) $this->subtotal - (float) $this->descuento, 2));

        $aseguradora = $this->paciente?->aseguradora;
        $this->cobertura_seguro = $aseguradora ? $aseguradora->cobertura($neto) : 0;

        $this->total = max(0, round($neto - (float) $this->cobertura_seguro, 2));

        $this->save();
    }

    /** Avanza el estado según cuántas líneas se han ejecutado. */
    public function sincronizarEstado(): void
    {
        if (in_array($this->estado, ['BORRADOR', 'PRESENTADO', 'RECHAZADO'], true)) {
            return;
        }

        $vigentes = $this->detalles()->where('estado', '!=', 'ANULADO');
        $total = (clone $vigentes)->count();
        $ejecutados = (clone $vigentes)->where('estado', 'EJECUTADO')->count();

        $this->estado = match (true) {
            $total === 0 => $this->estado,
            $ejecutados === $total => 'COMPLETADO',
            $ejecutados > 0 => 'EN_EJECUCION',
            default => 'APROBADO',
        };

        $this->save();
    }

    public function getColorEstadoAttribute(): string
    {
        return self::COLORES[$this->estado] ?? 'secondary';
    }

    public function getEstadoLegibleAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    public function getVenceElAttribute(): Carbon
    {
        return $this->fecha->copy()->addDays((int) $this->validez_dias);
    }

    public function getAvanceAttribute(): int
    {
        $vigentes = $this->detalles->where('estado', '!=', 'ANULADO');

        if ($vigentes->isEmpty()) {
            return 0;
        }

        return (int) round($vigentes->where('estado', 'EJECUTADO')->count() / $vigentes->count() * 100);
    }

    /** Un presupuesto aprobado ya no admite cambios en sus líneas. */
    public function getEsEditableAttribute(): bool
    {
        return in_array($this->estado, ['BORRADOR', 'PRESENTADO'], true);
    }
}
